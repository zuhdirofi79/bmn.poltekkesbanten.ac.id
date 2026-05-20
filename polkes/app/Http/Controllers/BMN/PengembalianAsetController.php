<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\StorePengembalianAsetRequest;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PengembalianAsetBmn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Modul Pengembalian Aset BMN.
 *
 * Aturan:
 *   - Pengembalian hanya valid bila peminjaman status = "dipinjam".
 *   - Saat pengembalian sukses: status setiap aset kembali ke "aktif".
 *   - Kondisi kembali per item disimpan di peminjaman_aset_bmn_detail.kondisi_kembali
 *     (tabel pengembalian_aset_bmn TIDAK punya kolom kondisi_kembali).
 *   - 1 transaksi peminjaman ↔ 1 record pengembalian (unique).
 *
 * Catatan skema DB production:
 *   - pengembalian_aset_bmn FK utama: peminjaman_aset_bmn_id
 *   - kolom petugas penerima: diterima_by (bukan received_by)
 */
class PengembalianAsetController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        // Daftar peminjaman yang BELUM dikembalikan (status = dipinjam).
        $belumKembaliQuery = PeminjamanAsetBmn::query()
            ->with(['user:id,name,jurusan', 'approver:id,name'])
            ->withCount('details')
            ->where('status', PeminjamanAsetBmn::STATUS_DIPINJAM)
            ->orderByDesc('id');

        // Riwayat pengembalian terbaru.
        $riwayatQuery = PengembalianAsetBmn::query()
            ->with([
                'peminjaman:id,kode_peminjaman,user_id,tanggal_pinjam,rencana_kembali',
                'peminjaman.user:id,name,jurusan',
                'penerima:id,name',
            ])
            ->orderByDesc('id');

        // Scoping: requester (non-admin, non-approver) hanya lihat miliknya.
        if (! $user->hasRole('admin') && ! $user->canApproveBmn()) {
            $belumKembaliQuery->where('user_id', $user->id);
            $riwayatQuery->whereHas('peminjaman', function ($q) use ($user): void {
                $q->where('user_id', $user->id);
            });
        }

        $belumKembali = $belumKembaliQuery->paginate(10, ['*'], 'belum')->appends($request->query());
        $riwayat = $riwayatQuery->paginate(10, ['*'], 'riwayat')->appends($request->query());

        // Admin/approver bisa proses semua. Peminjam-owner bisa proses transaksi miliknya
        // sendiri; di view kita cek per-row dengan $currentUserId.
        $canProcess = $user->hasRole('admin') || $user->canApproveBmn();
        $currentUserId = (int) $user->id;

        return view('bmn.pengembalian.index', compact('belumKembali', 'riwayat', 'canProcess', 'currentUserId'));
    }

    public function create(Request $request, int $peminjaman_id): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        /** @var PeminjamanAsetBmn $peminjaman */
        $peminjaman = PeminjamanAsetBmn::query()
            ->with([
                'user:id,name,jurusan',
                'approver:id,name',
                'details.aset.kategori:id,nama_kategori',
                'details.aset.lokasiMaster:id,nama_lokasi',
            ])
            ->findOrFail($peminjaman_id);

        // Akses:
        //   - approver (admin + can_approve_bmn = 1) ATAU admin biasa, atau
        //   - peminjam pemilik transaksi (upload bukti sendiri).
        $isApprover = $user->hasRole('admin') && (int) ($user->can_approve_bmn ?? 0) === 1;
        $isAdmin = $user->hasRole('admin');
        $isOwner = (int) $peminjaman->user_id === (int) $user->id;
        if (! $isApprover && ! $isAdmin && ! $isOwner) {
            abort(403, 'Anda tidak berhak memproses pengembalian ini.');
        }

        if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_DIPINJAM) {
            abort(404, 'Pengembalian hanya bisa untuk peminjaman berstatus dipinjam.');
        }

        return view('bmn.pengembalian.create', compact('peminjaman'));
    }

    public function store(StorePengembalianAsetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Upload Foto Bukti Pengembalian (opsional). Disimpan ke disk "public"
        // di bmn/pengembalian. Jika transaksi DB gagal, file dihapus lagi.
        $buktiPath = null;
        $buktiOriginalName = null;
        if ($request->hasFile('bukti_foto')) {
            $file = $request->file('bukti_foto');
            $buktiOriginalName = (string) $file->getClientOriginalName();
            $buktiPath = (string) $file->store('bmn/pengembalian', 'public');
        }

        try {
            $pengembalian = DB::transaction(function () use ($data, $user, $buktiPath, $buktiOriginalName): PengembalianAsetBmn {
                /** @var PeminjamanAsetBmn $peminjaman */
                $peminjaman = PeminjamanAsetBmn::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $data['peminjaman_aset_bmn_id']);

                if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_DIPINJAM) {
                    throw new \RuntimeException('Transaksi tidak dalam status dipinjam.');
                }

                if ($peminjaman->pengembalian()->exists()) {
                    throw new \RuntimeException('Transaksi ini sudah pernah dikembalikan.');
                }

                // Penjaga tambahan di layer controller (defense in depth) — ownership
                // sudah dicek di FormRequest::authorize, tapi kita validasi ulang
                // berdasarkan baris yang baru saja di-lock untuk menghindari race.
                $isApprover = $user->hasRole('admin') && (int) ($user->can_approve_bmn ?? 0) === 1;
                $isAdmin = $user->hasRole('admin');
                $isOwner = (int) $peminjaman->user_id === (int) $user->id;
                if (! $isApprover && ! $isAdmin && ! $isOwner) {
                    throw new \RuntimeException('Anda tidak berhak memproses pengembalian ini.');
                }

                $kondisiKembali = isset($data['kondisi_kembali'])
                    ? (string) $data['kondisi_kembali']
                    : null;

                $pengembalian = PengembalianAsetBmn::create([
                    'peminjaman_aset_bmn_id' => $peminjaman->id,
                    'diterima_by' => $user->id,
                    'tanggal_kembali' => $data['tanggal_kembali'],
                    'catatan_pengembalian' => $data['catatan_pengembalian'] ?? null,
                    'bukti_foto_path' => $buktiPath,
                    'bukti_foto_original_name' => $buktiOriginalName,
                ]);

                // Update setiap detail: status_item -> dikembalikan, kondisi_kembali -> input.
                // Gunakan relasi peminjaman_aset_bmn_id (FK utama) bukan alias peminjaman_id.
                $detailUpdate = ['status_item' => 'dikembalikan'];
                if ($kondisiKembali !== null && $kondisiKembali !== '') {
                    $detailUpdate['kondisi_kembali'] = $kondisiKembali;
                }
                $peminjaman->details()->update($detailUpdate);

                $asetIds = $peminjaman->details()->pluck('aset_bmn_id')->filter()->all();

                if ($asetIds !== []) {
                    $update = ['status' => 'aktif'];

                    // Jika user mencatat kondisi baru, propagasi ke kolom kondisi aset master.
                    if ($kondisiKembali !== null && $kondisiKembali !== '') {
                        $update['kondisi'] = $kondisiKembali;
                    }

                    AsetBmn::query()
                        ->whereIn('id', $asetIds)
                        ->lockForUpdate()
                        ->get(['id']);

                    AsetBmn::query()
                        ->whereIn('id', $asetIds)
                        ->update($update);
                }

                $peminjaman->update([
                    'status' => PeminjamanAsetBmn::STATUS_DIKEMBALIKAN,
                ]);

                return $pengembalian;
            });
        } catch (\Throwable $e) {
            // Rollback file upload jika DB gagal supaya tidak ada file yatim.
            if ($buktiPath !== null) {
                try {
                    Storage::disk('public')->delete($buktiPath);
                } catch (\Throwable $ignored) {
                    // best effort; jangan menutupi error utama.
                }
            }

            return back()->withInput()->with('error', 'Gagal menyimpan pengembalian: '.$e->getMessage());
        }

        return redirect()
            ->route('bmn.peminjaman-aset.show', $pengembalian->peminjaman_aset_bmn_id)
            ->with('success', 'Pengembalian berhasil dicatat. Aset kembali aktif.');
    }
}

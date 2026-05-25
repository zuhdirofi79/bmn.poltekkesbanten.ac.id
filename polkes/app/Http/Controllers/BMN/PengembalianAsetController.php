<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\RejectPengembalianAsetRequest;
use App\Http\Requests\BMN\StorePengembalianAsetRequest;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PengembalianAsetBmn;
use App\Services\BMN\BmnPublicVerificationService;
use App\Services\BMN\BmnWaNumberNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Modul Pengembalian Aset BMN.
 */
class PengembalianAsetController extends Controller
{
    public function __construct(
        private readonly BmnPublicVerificationService $publicVerificationService
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $belumKembaliQuery = PeminjamanAsetBmn::query()
            ->with(['user:id,name,jurusan', 'approver:id,name'])
            ->withCount('details')
            ->where('status', PeminjamanAsetBmn::STATUS_DIPINJAM)
            ->orderByDesc('id');

        $riwayatQuery = PengembalianAsetBmn::query()
            ->with([
                'peminjaman:id,kode_peminjaman,user_id,tanggal_pinjam,rencana_kembali,source,public_nama',
                'peminjaman.user:id,name,jurusan',
                'penerima:id,name',
                'adminVerifier:id,name',
            ])
            ->orderByDesc('id');

        if (! $user->hasRole('admin') && ! $user->canApproveBmn()) {
            $belumKembaliQuery->where('user_id', $user->id);
            $riwayatQuery->whereHas('peminjaman', function ($q) use ($user): void {
                $q->where('user_id', $user->id);
            });
        }

        $belumKembali = $belumKembaliQuery->paginate(10, ['*'], 'belum')->appends($request->query());
        $riwayat = $riwayatQuery->paginate(10, ['*'], 'riwayat')->appends($request->query());

        $canProcess = $user->hasRole('admin') || $user->canApproveBmn();
        $canApprove = $user->canApproveBmn();
        $currentUserId = (int) $user->id;

        return view('bmn.pengembalian.index', compact(
            'belumKembali',
            'riwayat',
            'canProcess',
            'canApprove',
            'currentUserId'
        ));
    }

    /**
     * Form pengembalian publik (tanpa login) — input kode peminjaman.
     */
    public function createPublic(Request $request): View
    {
        abort_if($request->user() !== null, 404);

        $peminjaman = null;
        $kode = trim((string) $request->query('kode_peminjaman', ''));
        if ($kode !== '') {
            $peminjaman = PeminjamanAsetBmn::query()
                ->with(['details.aset.kategori:id,nama_kategori', 'details.aset.lokasiMaster:id,nama_lokasi'])
                ->where('kode_peminjaman', $kode)
                ->first();
        }

        return view('bmn.pengembalian.create_public', compact('peminjaman', 'kode'));
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
        $isGuest = $user === null;

        $buktiPath = null;
        $buktiOriginalName = null;
        if ($request->hasFile('bukti_foto')) {
            /** @var UploadedFile $file */
            $file = $request->file('bukti_foto');
            $buktiOriginalName = (string) $file->getClientOriginalName();
            $buktiPath = (string) $file->store('bmn/pengembalian', 'public');
        }

        try {
            if ($isGuest) {
                $pengembalian = $this->storePublicPengembalian($data, $buktiPath, $buktiOriginalName);
            } else {
                $pengembalian = $this->storeLoginPengembalian($data, $user, $buktiPath, $buktiOriginalName);
            }
        } catch (\Throwable $e) {
            if ($buktiPath !== null) {
                try {
                    Storage::disk('public')->delete($buktiPath);
                } catch (\Throwable $ignored) {
                }
            }

            return back()->withInput()->with('error', 'Gagal menyimpan pengembalian: '.$e->getMessage());
        }

        if ($isGuest) {
            $token = $this->publicVerificationService->initializePublicVerification($pengembalian);

            return redirect()
                ->route('bmn.public-verification.show', [
                    'type' => 'pengembalian',
                    'id' => $pengembalian->id,
                    'token' => $token,
                ])
                ->with('success', 'Pengajuan pengembalian tersimpan. Verifikasi Email dan WhatsApp untuk melanjutkan.');
        }

        return redirect()
            ->route('bmn.peminjaman-aset.show', $pengembalian->peminjaman_aset_bmn_id)
            ->with('success', 'Pengembalian berhasil dicatat. Aset kembali aktif.');
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->canApproveBmn(), 403);

        try {
            DB::transaction(function () use ($id, $user): void {
                /** @var PengembalianAsetBmn $pengembalian */
                $pengembalian = PengembalianAsetBmn::query()->lockForUpdate()->findOrFail($id);

                if (! $pengembalian->isPublicSource()) {
                    throw new \RuntimeException('Verifikasi admin hanya untuk pengembalian publik.');
                }

                if (($pengembalian->verification_status ?? '') !== 'verified') {
                    throw new \RuntimeException('Kontak pengaju belum terverifikasi (OTP).');
                }

                if (($pengembalian->admin_status ?? '') !== PengembalianAsetBmn::ADMIN_STATUS_PENDING) {
                    throw new \RuntimeException('Pengembalian tidak lagi menunggu verifikasi admin.');
                }

                /** @var PeminjamanAsetBmn $peminjaman */
                $peminjaman = PeminjamanAsetBmn::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $pengembalian->peminjaman_aset_bmn_id);

                $this->completePengembalian($pengembalian, $peminjaman, null);

                $pengembalian->update([
                    'admin_verified_by' => $user->id,
                    'admin_verified_at' => Carbon::now(),
                    'admin_status' => PengembalianAsetBmn::ADMIN_STATUS_APPROVED,
                    'diterima_by' => $user->id,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal verifikasi pengembalian: '.$e->getMessage());
        }

        return back()->with('success', 'Pengembalian publik diverifikasi. Aset kembali aktif.');
    }

    public function reject(RejectPengembalianAsetRequest $request, int $id): RedirectResponse
    {
        $note = (string) $request->validated()['admin_verification_note'];

        try {
            DB::transaction(function () use ($request, $id, $note): void {
                /** @var PengembalianAsetBmn $pengembalian */
                $pengembalian = PengembalianAsetBmn::query()->lockForUpdate()->findOrFail($id);

                if (! $pengembalian->isPublicSource()) {
                    throw new \RuntimeException('Penolakan admin hanya untuk pengembalian publik.');
                }

                if (($pengembalian->admin_status ?? '') !== PengembalianAsetBmn::ADMIN_STATUS_PENDING) {
                    throw new \RuntimeException('Pengembalian tidak lagi menunggu verifikasi admin.');
                }

                if ($pengembalian->bukti_foto_path) {
                    try {
                        Storage::disk('public')->delete((string) $pengembalian->bukti_foto_path);
                    } catch (\Throwable $ignored) {
                    }
                }

                \Illuminate\Support\Facades\Log::info('BMN public pengembalian rejected', [
                    'pengembalian_id' => $pengembalian->id,
                    'peminjaman_id' => $pengembalian->peminjaman_aset_bmn_id,
                    'rejected_by' => $request->user()->id,
                    'note' => $note,
                ]);

                $pengembalian->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menolak pengembalian: '.$e->getMessage());
        }

        return back()->with('success', 'Pengembalian publik ditolak.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeLoginPengembalian(
        array $data,
        $user,
        ?string $buktiPath,
        ?string $buktiOriginalName
    ): PengembalianAsetBmn {
        return DB::transaction(function () use ($data, $user, $buktiPath, $buktiOriginalName): PengembalianAsetBmn {
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
                'source' => PengembalianAsetBmn::SOURCE_LOGIN,
                'verification_status' => 'not_required',
            ]);

            $this->completePengembalian($pengembalian, $peminjaman, $kondisiKembali);

            return $pengembalian;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storePublicPengembalian(
        array $data,
        ?string $buktiPath,
        ?string $buktiOriginalName
    ): PengembalianAsetBmn {
        return DB::transaction(function () use ($data, $buktiPath, $buktiOriginalName): PengembalianAsetBmn {
            $kode = trim((string) $data['kode_peminjaman']);

            /** @var PeminjamanAsetBmn $peminjaman */
            $peminjaman = PeminjamanAsetBmn::query()
                ->lockForUpdate()
                ->where('kode_peminjaman', $kode)
                ->firstOrFail();

            if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_DIPINJAM) {
                throw new \RuntimeException('Transaksi tidak dalam status dipinjam.');
            }

            if ($peminjaman->pengembalian()->exists()) {
                throw new \RuntimeException('Transaksi ini sudah pernah diajukan pengembalian.');
            }

            return PengembalianAsetBmn::create([
                'peminjaman_aset_bmn_id' => $peminjaman->id,
                'diterima_by' => null,
                'tanggal_kembali' => $data['tanggal_kembali'],
                'catatan_pengembalian' => $data['catatan_pengembalian'] ?? null,
                'bukti_foto_path' => $buktiPath,
                'bukti_foto_original_name' => $buktiOriginalName,
                'source' => PengembalianAsetBmn::SOURCE_PUBLIC,
                'public_nama' => $data['public_nama'],
                'public_no_wa' => BmnWaNumberNormalizer::normalize($data['public_no_wa']),
                'public_email' => $data['public_email'],
                'verification_status' => 'pending',
                'admin_status' => PengembalianAsetBmn::ADMIN_STATUS_PENDING,
            ]);
        });
    }

    private function completePengembalian(
        PengembalianAsetBmn $pengembalian,
        PeminjamanAsetBmn $peminjaman,
        ?string $kondisiKembali
    ): void {
        $detailUpdate = ['status_item' => 'dikembalikan'];
        if ($kondisiKembali !== null && $kondisiKembali !== '') {
            $detailUpdate['kondisi_kembali'] = $kondisiKembali;
        }
        $peminjaman->details()->update($detailUpdate);

        $asetIds = $peminjaman->details()->pluck('aset_bmn_id')->filter()->all();

        if ($asetIds !== []) {
            $update = ['status' => 'aktif'];
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
    }
}

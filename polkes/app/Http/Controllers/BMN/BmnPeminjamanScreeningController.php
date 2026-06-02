<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\StoreBmnPeminjamanScreeningRequest;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\KategoriAset;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PeminjamanAsetBmnDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BmnPeminjamanScreeningController extends Controller
{
    public function show(Request $request, int $id): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isRumahTanggaBmn(), 403);

        /** @var PeminjamanAsetBmn $peminjaman */
        $peminjaman = PeminjamanAsetBmn::query()
            ->with(['details.aset.kategori', 'screener:id,name'])
            ->findOrFail($id);

        abort_unless($peminjaman->isAwaitingScreening() || $peminjaman->approval_stage === PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA, 403);

        $filters = $request->only(['q', 'kategori_id']);

        $occupiedAsetIds = PeminjamanAsetBmnDetail::query()
            ->join('peminjaman_aset_bmn as p', 'p.id', '=', 'peminjaman_aset_bmn_detail.peminjaman_aset_bmn_id')
            ->whereIn('p.status', [
                PeminjamanAsetBmn::STATUS_PENDING,
                PeminjamanAsetBmn::STATUS_APPROVED,
                PeminjamanAsetBmn::STATUS_DIPINJAM,
            ])
            ->where('p.id', '!=', $peminjaman->id)
            ->pluck('peminjaman_aset_bmn_detail.aset_bmn_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $asetList = AsetBmn::query()
            ->with(['kategori:id,nama_kategori', 'lokasiMaster:id,nama_lokasi'])
            ->where('status', 'aktif')
            ->whereNotIn('id', $occupiedAsetIds)
            ->filter($filters)
            ->orderBy('nama_barang')
            ->paginate(15)
            ->appends($request->query());

        $kategoriList = KategoriAset::orderBy('nama_kategori')->get(['id', 'nama_kategori']);

        return view('bmn.peminjaman.screening', compact('peminjaman', 'asetList', 'kategoriList', 'filters'));
    }

    public function store(StoreBmnPeminjamanScreeningRequest $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isRumahTanggaBmn(), 403);

        $data = $request->validated();
        $result = (string) $data['screening_result'];
        $note = trim((string) ($data['screening_note'] ?? ''));

        $fotoPath = null;
        $fotoOriginal = null;
        if ($request->hasFile('kondisi_foto')) {
            /** @var UploadedFile $file */
            $file = $request->file('kondisi_foto');
            $fotoOriginal = (string) $file->getClientOriginalName();
            $fotoPath = $this->storeKondisiFoto($file);
        }

        try {
            DB::transaction(function () use ($id, $user, $data, $result, $note, $fotoPath, $fotoOriginal): void {
                /** @var PeminjamanAsetBmn $peminjaman */
                $peminjaman = PeminjamanAsetBmn::query()->lockForUpdate()->findOrFail($id);

                if (! $peminjaman->isAwaitingScreening()) {
                    throw new \RuntimeException('Pengajuan tidak lagi menunggu screening.');
                }

                $baseUpdate = [
                    'screened_by' => $user->id,
                    'screened_at' => Carbon::now(),
                    'screening_note' => $note !== '' ? $note : null,
                    'kondisi_foto_path' => $fotoPath,
                    'kondisi_foto_original_name' => $fotoOriginal,
                ];

                if ($result === 'tidak_tersedia') {
                    $peminjaman->update(array_merge($baseUpdate, [
                        'screening_status' => PeminjamanAsetBmn::SCREENING_TIDAK_TERSEDIA,
                        'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_REJECTED,
                        'status' => PeminjamanAsetBmn::STATUS_REJECTED,
                        'rejected_by' => $user->id,
                        'rejected_at' => Carbon::now(),
                        'rejected_reason' => $note,
                        'rejected_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA,
                    ]));

                    return;
                }

                if ($result === 'perlu_konfirmasi') {
                    $peminjaman->update(array_merge($baseUpdate, [
                        'screening_status' => PeminjamanAsetBmn::SCREENING_PERLU_KONFIRMASI,
                    ]));

                    return;
                }

                /** @var list<int> $asetIds */
                $asetIds = array_values(array_unique(array_map('intval', $data['aset_ids'] ?? [])));
                $asetList = AsetBmn::query()
                    ->whereIn('id', $asetIds)
                    ->lockForUpdate()
                    ->get(['id', 'kondisi']);

                $peminjaman->details()->delete();

                foreach ($asetList as $aset) {
                    PeminjamanAsetBmnDetail::create([
                        'peminjaman_aset_bmn_id' => $peminjaman->id,
                        'peminjaman_id' => $peminjaman->id,
                        'aset_bmn_id' => $aset->id,
                        'aset_id' => $aset->id,
                        'kondisi_awal' => $aset->kondisi ?: 'baik',
                        'status_item' => 'dipinjam',
                        'catatan' => trim((string) ($data['kondisi_catatan'] ?? '')) ?: null,
                    ]);
                }

                $peminjaman->update(array_merge($baseUpdate, [
                    'screening_status' => PeminjamanAsetBmn::SCREENING_TERSEDIA,
                    'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI,
                ]));
            });
        } catch (\Throwable $e) {
            if ($fotoPath !== null) {
                $abs = public_path(ltrim($fotoPath, '/'));
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }

            return back()->withInput()->with('error', 'Gagal menyimpan screening: '.$e->getMessage());
        }

        $msg = match ($result) {
            'tidak_tersedia' => 'Pengajuan ditolak karena barang tidak tersedia.',
            'perlu_konfirmasi' => 'Screening disimpan. Menunggu konfirmasi lanjutan.',
            default => 'Screening selesai. Pengajuan diteruskan ke Pak Hendri untuk persetujuan final.',
        };

        return redirect()
            ->route('bmn.peminjaman-aset.show', $id)
            ->with('success', $msg);
    }

    private function storeKondisiFoto(UploadedFile $file): string
    {
        $dirRelative = PeminjamanAsetBmn::KONDISI_FOTO_DIR;
        $dirAbsolute = bmn_public_upload_path($dirRelative);

        if (! is_dir($dirAbsolute)) {
            @mkdir($dirAbsolute, 0755, true);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension()) ?: 'jpg';
        $filename = sprintf('kondisi-%s-%s.%s', Carbon::now()->format('Ymd'), Str::random(8), $ext);
        $file->move($dirAbsolute, $filename);

        return $dirRelative.'/'.$filename;
    }
}

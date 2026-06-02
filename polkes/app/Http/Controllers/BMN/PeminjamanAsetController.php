<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\ApprovePeminjamanRequest;
use App\Http\Requests\BMN\StorePeminjamanAsetRequest;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\KategoriAset;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PeminjamanAsetBmnDetail;
use App\Models\BmnSatker;
use App\Services\BMN\BmnPublicVerificationService;
use App\Services\BMN\BmnWaNumberNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Modul Peminjaman Aset BMN.
 *
 * Catatan keamanan & performance:
 *   - eager-load detail.aset.kategori untuk hindari N+1 di index/show.
 *   - pagination wajib di index.
 *   - akses dibatasi per scope:
 *       * jurusan/requester: hanya melihat transaksi miliknya.
 *       * approver (admin + can_approve_bmn): melihat semua.
 *       * admin biasa: read-only semua transaksi, tidak boleh approve/reject.
 *
 * Catatan skema DB production saat ini:
 *   - peminjaman_aset_bmn.kode_peminjaman           (bukan kode_transaksi)
 *   - peminjaman_aset_bmn.rejected_by + rejected_at (bukan alasan_reject)
 *   - peminjaman_aset_bmn_detail FK utama: peminjaman_aset_bmn_id, aset_bmn_id
 *     (alias legacy peminjaman_id & aset_id juga disinkronkan saat insert).
 */
class PeminjamanAsetController extends Controller
{
    public function __construct(
        private readonly BmnPublicVerificationService $publicVerificationService
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $filters = $request->only(['q', 'status', 'approval_stage', 'jurusan', 'tanggal_from', 'tanggal_to']);

        $query = PeminjamanAsetBmn::query()
            ->with([
                'user:id,name,jurusan,email,no_hp',
                'approver:id,name',
                'verifier1:id,name',
                'verifier2:id,name',
                'finalReporter:id,name',
            ])
            ->withCount('details')
            ->filter($filters)
            ->orderByDesc('id');

        // Scoping akses: non-admin/non-approver/non-verifikator/non-rumah-tangga hanya lihat miliknya.
        if (! $user->hasRole('admin') && ! $user->canApproveBmn() && ! $user->isBmnApprovalParticipant() && ! $user->isRumahTanggaBmn()) {
            $query->where('user_id', $user->id);
        }

        $list = $query->paginate(15)->appends($request->query());

        $canApprove = $user->canApproveBmn();
        $canRequest = $user->canRequestBmn();

        return view('bmn.peminjaman.index', compact('list', 'filters', 'canApprove', 'canRequest'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $isGuest = $user === null;

        if (! $isGuest) {
            abort_unless($user->canRequestBmn(), 403, 'Hanya pengguna jurusan yang berhak mengajukan peminjaman aset BMN.');
        }

        $filters = $request->only([
            'q', 'kategori_id', 'kondisi', 'status', 'satker_id',
        ]);

        // Default: hanya tampilkan aset aktif yang belum terikat peminjaman aktif.
        // Gunakan FK utama (peminjaman_aset_bmn_id, aset_bmn_id) untuk query.
        $occupiedAsetIds = PeminjamanAsetBmnDetail::query()
            ->join('peminjaman_aset_bmn as p', 'p.id', '=', 'peminjaman_aset_bmn_detail.peminjaman_aset_bmn_id')
            ->whereIn('p.status', [
                PeminjamanAsetBmn::STATUS_PENDING,
                PeminjamanAsetBmn::STATUS_APPROVED,
                PeminjamanAsetBmn::STATUS_DIPINJAM,
            ])
            ->pluck('peminjaman_aset_bmn_detail.aset_bmn_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $asetQuery = AsetBmn::query()
            ->with(['kategori:id,nama_kategori', 'lokasiMaster:id,nama_lokasi'])
            ->where('status', 'aktif')
            ->whereNotIn('id', $occupiedAsetIds)
            ->filter($filters)
            ->orderBy('kode_barang')
            ->orderBy('nup');

        $asetList = $asetQuery->paginate(20)->appends($request->query());

        $kategoriList = KategoriAset::orderBy('nama_kategori')->get(['id', 'nama_kategori']);
        $satkers = BmnSatker::orderBy('sort_order')->orderBy('id')->get(['id', 'nama']);

        return view('bmn.peminjaman.create', compact(
            'asetList',
            'kategoriList',
            'satkers',
            'filters',
            'isGuest'
        ));
    }

    public function store(StorePeminjamanAsetRequest $request): RedirectResponse
    {
        $user = $request->user();
        $isGuest = $user === null;
        $data = $request->validated();

        /** @var list<int> $asetIds */
        $asetIds = array_values(array_unique(array_map('intval', $data['aset_ids'])));

        // Upload Nota Dinas (opsional). File disimpan LANGSUNG ke document root
        // (public/uploads/nota_dinas) agar URL-nya dapat diakses tanpa perlu
        // `php artisan storage:link`. Penting untuk shared hosting yang
        // mem-block symlink (mis. Hostinger). Path relatif publik yang disimpan
        // ke DB berbentuk: "uploads/nota_dinas/<nama-file-aman>".
        $notaPath = null;
        $notaOriginalName = null;
        if ($request->hasFile('nota_dinas')) {
            /** @var UploadedFile $file */
            $file = $request->file('nota_dinas');
            $notaOriginalName = (string) $file->getClientOriginalName();
            $notaPath = $this->storeNotaDinasPublic($file);
        }

        try {
            $peminjaman = DB::transaction(function () use ($user, $isGuest, $data, $asetIds, $notaPath, $notaOriginalName): PeminjamanAsetBmn {
                // Lock aset agar dua request bersamaan tidak menabrak baris yang sama.
                $asetList = AsetBmn::query()
                    ->whereIn('id', $asetIds)
                    ->lockForUpdate()
                    ->get(['id', 'kondisi']);

                $kode = $this->generateKodePeminjaman();

                $payload = [
                    'kode_peminjaman' => $kode,
                    'user_id' => $isGuest ? null : $user->id,
                    'jurusan' => $isGuest ? null : $user->jurusan,
                    'tujuan_peminjaman' => $data['tujuan_peminjaman'],
                    'tanggal_pinjam' => $data['tanggal_pinjam'],
                    'rencana_kembali' => $data['rencana_kembali'],
                    'status' => PeminjamanAsetBmn::STATUS_PENDING,
                    'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1,
                    'catatan' => $data['catatan'] ?? null,
                    'nota_dinas_path' => $notaPath,
                    'nota_dinas_original_name' => $notaOriginalName,
                    'source' => $isGuest ? PeminjamanAsetBmn::SOURCE_PUBLIC : PeminjamanAsetBmn::SOURCE_LOGIN,
                    'verification_status' => $isGuest
                        ? PeminjamanAsetBmn::VERIFICATION_PENDING
                        : PeminjamanAsetBmn::VERIFICATION_NOT_REQUIRED,
                ];

                if ($isGuest) {
                    $payload['public_nama'] = $data['public_nama'];
                    $payload['public_no_wa'] = BmnWaNumberNormalizer::normalize($data['public_no_wa']);
                    $payload['public_email'] = $data['public_email'];
                }

                $peminjaman = PeminjamanAsetBmn::create($payload);

                // Insert detail satu per satu via create() agar SEMUA kolom (utama + alias)
                // terisi dengan benar dan default kondisi_awal terbaca dari aset.
                foreach ($asetList as $aset) {
                    PeminjamanAsetBmnDetail::create([
                        'peminjaman_aset_bmn_id' => $peminjaman->id,
                        'peminjaman_id' => $peminjaman->id,
                        'aset_bmn_id' => $aset->id,
                        'aset_id' => $aset->id,
                        'kondisi_awal' => $aset->kondisi ?: 'baik',
                        'status_item' => 'dipinjam',
                        'catatan' => null,
                    ]);
                }

                return $peminjaman;
            });
        } catch (\Throwable $e) {
            // Rollback file upload jika DB gagal supaya tidak ada file yatim
            // di disk. Hapus berdasarkan nama file (basename) lewat helper,
            // bukan public_path() — karena di cPanel/Hostinger lokasi fisik
            // ada di public_html/uploads/nota_dinas, BUKAN di Laravel public/.
            if ($notaPath !== null) {
                $absolute = bmn_nota_dinas_path(basename($notaPath));
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }

            return back()->withInput()->with('error', 'Gagal menyimpan peminjaman: '.$e->getMessage());
        }

        if ($isGuest) {
            $token = $this->publicVerificationService->initializePublicVerification($peminjaman);

            return redirect()
                ->route('bmn.public-verification.show', [
                    'type' => 'peminjaman',
                    'id' => $peminjaman->id,
                    'token' => $token,
                ])
                ->with('success', 'Pengajuan tersimpan. Mohon verifikasi email Anda untuk melanjutkan.');
        }

        return redirect()
            ->route('bmn.peminjaman-aset.show', $peminjaman->id)
            ->with('success', 'Peminjaman '.$peminjaman->kode_peminjaman.' berhasil diajukan dan menunggu persetujuan.');
    }

    public function show(Request $request, int $id): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        /** @var PeminjamanAsetBmn $peminjaman */
        $peminjaman = PeminjamanAsetBmn::query()
            ->with([
                'user:id,name,jurusan,email,no_hp',
                'approver:id,name',
                'rejecter:id,name',
                'verifier1:id,name',
                'verifier2:id,name',
                'finalReporter:id,name',
                'screener:id,name',
                'details.aset.kategori:id,nama_kategori',
                'details.aset.lokasiMaster:id,nama_lokasi',
                'pengembalian.penerima:id,name',
            ])
            ->findOrFail($id);

        // Scoping: requester hanya boleh lihat miliknya (public hanya admin/approver/verifikator/rumah tangga).
        if (! $user->hasRole('admin') && ! $user->canApproveBmn() && ! $user->isBmnApprovalParticipant() && ! $user->isRumahTanggaBmn()) {
            if ($peminjaman->isPublicSource() || (int) $peminjaman->user_id !== (int) $user->id) {
                abort(403, 'Anda tidak berhak melihat transaksi ini.');
            }
        }

        $canApproveLegacy = $user->canApproveBmn() && ! $peminjaman->usesMultiStageApproval();
        $canMarkDipinjam = $user->canApproveBmn() || $user->isBmnVerifier1();

        $canApproveStage1 = $user->isBmnVerifier1()
            && $peminjaman->status === PeminjamanAsetBmn::STATUS_PENDING
            && $peminjaman->approval_stage === PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1;

        $canApproveStage2 = $user->isBmnVerifier2()
            && $peminjaman->status === PeminjamanAsetBmn::STATUS_PENDING
            && $peminjaman->approval_stage === PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2;

        $canFinalReport = $user->isBmnVerifier1()
            && $peminjaman->status === PeminjamanAsetBmn::STATUS_PENDING
            && $peminjaman->approval_stage === PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_REPORT;

        $canRejectStage = (
                ($user->isBmnVerifier1() && $peminjaman->approval_stage === PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1)
                || ($user->isBmnVerifier2() && $peminjaman->approval_stage === PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2)
            )
            && $peminjaman->status === PeminjamanAsetBmn::STATUS_PENDING;

        $canScreen = $user->isRumahTanggaBmn() && $peminjaman->isAwaitingScreening();

        $canApproveFinalHendri = $user->isFinalBmnApprover()
            && $peminjaman->status === PeminjamanAsetBmn::STATUS_PENDING
            && $peminjaman->approval_stage === PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI;

        $canRejectFinalHendri = $canApproveFinalHendri;

        // Pengembalian boleh diproses oleh:
        //   - approver (admin + can_approve_bmn = 1), atau
        //   - admin biasa yang menerima fisik aset, atau
        //   - peminjam pemilik transaksi (upload bukti pengembalian sendiri).
        $isOwner = (int) $peminjaman->user_id === (int) $user->id;
        $canCreatePengembalian = (
                $user->hasRole('admin')
                || $user->canApproveBmn()
                || $isOwner
            )
            && $peminjaman->status === PeminjamanAsetBmn::STATUS_DIPINJAM;

        // Histori transaksi user terkait (untuk audit cepat).
        $userHistory = PeminjamanAsetBmn::query()
            ->where('user_id', $peminjaman->user_id)
            ->where('id', '!=', $peminjaman->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'kode_peminjaman', 'status', 'tanggal_pinjam', 'rencana_kembali']);

        return view('bmn.peminjaman.show', compact(
            'peminjaman',
            'canApproveLegacy',
            'canMarkDipinjam',
            'canApproveStage1',
            'canApproveStage2',
            'canFinalReport',
            'canRejectStage',
            'canScreen',
            'canApproveFinalHendri',
            'canRejectFinalHendri',
            'canCreatePengembalian',
            'userHistory'
        ));
    }

    public function approve(ApprovePeminjamanRequest $request, int $id): RedirectResponse
    {
        /** @var PeminjamanAsetBmn $peminjaman */
        $peminjaman = PeminjamanAsetBmn::query()->lockForUpdate()->findOrFail($id);

        if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_PENDING) {
            return back()->with('error', 'Transaksi tidak lagi berstatus pending.');
        }

        if ($peminjaman->usesMultiStageApproval()) {
            return back()->with('error', 'Peminjaman ini memakai workflow verifikator berurutan. Gunakan aksi tahap approval yang sesuai.');
        }

        $aksi = (string) $request->validated()['aksi'];
        $alasan = trim((string) ($request->validated()['alasan_reject'] ?? ''));

        try {
            DB::transaction(function () use ($peminjaman, $aksi, $alasan, $request): void {
                if ($aksi === 'approve') {
                    $peminjaman->update([
                        'status' => PeminjamanAsetBmn::STATUS_APPROVED,
                        'approved_by' => $request->user()->id,
                        'approved_at' => Carbon::now(),
                    ]);

                    return;
                }

                // Reject: simpan alasan reject ke kolom catatan dengan prefix
                // (kolom alasan_reject sudah tidak ada di skema production).
                $catatanLama = (string) ($peminjaman->catatan ?? '');
                $catatanBaru = $catatanLama;
                if ($alasan !== '') {
                    $prefix = 'ALASAN REJECT: '.$alasan;
                    $catatanBaru = $catatanLama !== ''
                        ? $catatanLama."\n".$prefix
                        : $prefix;
                }

                $peminjaman->update([
                    'status' => PeminjamanAsetBmn::STATUS_REJECTED,
                    'rejected_by' => $request->user()->id,
                    'rejected_at' => Carbon::now(),
                    'catatan' => $catatanBaru !== '' ? $catatanBaru : null,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses persetujuan: '.$e->getMessage());
        }

        $msg = $aksi === 'approve'
            ? 'Peminjaman '.$peminjaman->kode_peminjaman.' disetujui. Silakan tandai sebagai dipinjam saat aset diserahkan.'
            : 'Peminjaman '.$peminjaman->kode_peminjaman.' ditolak.';

        return back()->with('success', $msg);
    }

    /**
     * Approver menandai transaksi yang sudah approved menjadi "dipinjam" saat
     * aset fisik diserahkan. Saat itulah status setiap aset di-set ke "dipinjam".
     */
    public function markDipinjam(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->canApproveBmn() || $user->isBmnVerifier1()), 403);

        try {
            DB::transaction(function () use ($id): void {
                /** @var PeminjamanAsetBmn $peminjaman */
                $peminjaman = PeminjamanAsetBmn::query()->lockForUpdate()->findOrFail($id);

                if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_APPROVED) {
                    throw new \RuntimeException('Transaksi harus berstatus approved untuk ditandai dipinjam.');
                }

                $asetIds = $peminjaman->details()->pluck('aset_bmn_id')->filter()->all();

                if ($asetIds !== []) {
                    AsetBmn::query()
                        ->whereIn('id', $asetIds)
                        ->lockForUpdate()
                        ->get(['id', 'status']);

                    // Pastikan tidak ada yang sudah berubah status karena race condition.
                    $bukanAktif = AsetBmn::query()
                        ->whereIn('id', $asetIds)
                        ->where('status', '!=', 'aktif')
                        ->pluck('id')
                        ->all();

                    if ($bukanAktif !== []) {
                        throw new \RuntimeException(
                            'Aset berikut tidak lagi aktif: '.implode(', ', $bukanAktif)
                        );
                    }

                    AsetBmn::query()
                        ->whereIn('id', $asetIds)
                        ->update(['status' => 'dipinjam']);
                }

                $peminjaman->details()->update(['status_item' => 'dipinjam']);

                $peminjaman->update([
                    'status' => PeminjamanAsetBmn::STATUS_DIPINJAM,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menandai dipinjam: '.$e->getMessage());
        }

        return back()->with('success', 'Transaksi ditandai dipinjam. Status aset terkait diperbarui.');
    }

    /**
     * Simpan file Nota Dinas langsung ke folder publik di document root:
     *   <docroot>/uploads/nota_dinas/nota-dinas-{YYYYMMDD}-{rand8}.{ext}
     *
     * Lokasi fisik diresolusi via bmn_nota_dinas_path() — mirroring strategi
     * foto BMN. Pada cPanel/Hostinger (docroot = public_html, Laravel di
     * subfolder polkes/) lokasi ini WAJIB di-set via env
     * BMN_NOTA_DINAS_BASE_PATH, karena public_path() menunjuk ke
     * polkes/public/ — BUKAN ke document root yang diakses browser.
     *
     * Kenapa tidak Storage::disk('public')?
     *   - Symlink storage→public sering tidak aktif di shared hosting,
     *     sehingga URL /storage/... selalu 404.
     *   - Folder /uploads/nota_dinas/ di docroot dijamin accessible via HTTP.
     *
     * @return string path relatif publik (mis. "uploads/nota_dinas/nota-dinas-...pdf"),
     *                aman untuk disimpan ke DB & dipanggil lewat notaDinasUrl().
     *
     * @throws \RuntimeException jika folder tujuan tidak bisa dibuat / ditulisi.
     */
    private function storeNotaDinasPublic(UploadedFile $file): string
    {
        $dirRelative = PeminjamanAsetBmn::NOTA_DINAS_DIR; // "uploads/nota_dinas"
        $dirAbsolute = bmn_nota_dinas_path();

        if (! is_dir($dirAbsolute)) {
            // 0755 cukup untuk webserver; recursive=true antisipasi folder
            // induk (uploads/) juga belum ada.
            @mkdir($dirAbsolute, 0755, true);
        }

        if (! is_dir($dirAbsolute) || ! is_writable($dirAbsolute)) {
            // Fail-fast: jika folder tujuan tidak ada / read-only, jangan
            // diam-diam lolos dan menyimpan path "valid" tapi file kosong.
            throw new \RuntimeException(
                'Folder upload nota dinas tidak tersedia atau tidak dapat ditulisi: '.$dirAbsolute
            );
        }

        // Ekstensi disanitasi: hanya whitelist yang dipakai validasi.
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (! in_array($ext, $allowed, true) || $ext === '') {
            // Defensive fallback — seharusnya sudah ditolak validasi mimes,
            // tapi jangan biarkan ekstensi liar lolos ke filesystem.
            $ext = 'bin';
        }

        // Format nama file: nota-dinas-{YYYYMMDD}-{rand8}.{ext}
        // - tanggal lokal server (cocok dengan Carbon Laravel)
        // - rand8 = 8 char alfanumerik random (Str::random kuat secara crypto)
        $filename = sprintf(
            'nota-dinas-%s-%s.%s',
            Carbon::now()->format('Ymd'),
            Str::random(8),
            $ext
        );

        $file->move($dirAbsolute, $filename);

        // Path RELATIF publik yang disimpan ke DB. Selalu pakai "uploads/..."
        // (tanpa leading slash) supaya bisa dirender konsisten via
        // bmn_nota_dinas_url(basename(path)) di view/model.
        return $dirRelative.'/'.$filename;
    }

    private function generateKodePeminjaman(): string
    {
        // Format: PJM-BMN-YYYYMMDD-XXXXX (XXXXX = 5 char random uppercase)
        $tgl = Carbon::now()->format('Ymd');
        for ($i = 0; $i < 5; $i++) {
            $kode = 'PJM-BMN-'.$tgl.'-'.strtoupper(Str::random(5));
            $exists = PeminjamanAsetBmn::query()->where('kode_peminjaman', $kode)->exists();
            if (! $exists) {
                return $kode;
            }
        }

        // Fallback ekstrem: append timestamp ms.
        return 'PJM-BMN-'.$tgl.'-'.strtoupper(Str::random(5)).'-'.substr((string) microtime(true), -4);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\BMN\PengembalianAsetController;
use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\StorePengembalianAsetRequest;
use App\Http\Requests\BMN\StorePublicPeminjamanInternalRequest;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Services\BMN\BmnPublicVerificationService;
use App\Services\BMN\BmnWaNumberNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicPeminjamanInternalController extends Controller
{
    public function __construct(
        private readonly BmnPublicVerificationService $publicVerificationService
    ) {
    }

    public function index(): View
    {
        return view('public.peminjaman-internal.index');
    }

    public function createPinjam(): View
    {
        return view('public.peminjaman-internal.pinjam');
    }

    public function storePinjam(StorePublicPeminjamanInternalRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $notaPath = null;
        $notaOriginalName = null;
        if ($request->hasFile('nota_dinas')) {
            /** @var UploadedFile $file */
            $file = $request->file('nota_dinas');
            $notaOriginalName = (string) $file->getClientOriginalName();
            $notaPath = $this->storeNotaDinasPublic($file);
        }

        try {
            $peminjaman = DB::transaction(function () use ($data, $notaPath, $notaOriginalName): PeminjamanAsetBmn {
                $kode = $this->generateKodePeminjaman();

                return PeminjamanAsetBmn::create([
                    'kode_peminjaman' => $kode,
                    'user_id' => null,
                    'jurusan' => null,
                    'requested_barang' => $data['requested_barang'],
                    'tujuan_peminjaman' => $data['tujuan_peminjaman'],
                    'tanggal_pinjam' => $data['tanggal_pinjam'],
                    'rencana_kembali' => $data['rencana_kembali'],
                    'status' => PeminjamanAsetBmn::STATUS_PENDING,
                    'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA,
                    'screening_status' => PeminjamanAsetBmn::SCREENING_PENDING,
                    'catatan' => $data['catatan'] ?? null,
                    'nota_dinas_path' => $notaPath,
                    'nota_dinas_original_name' => $notaOriginalName,
                    'source' => PeminjamanAsetBmn::SOURCE_PUBLIC,
                    'public_nama' => $data['public_nama'],
                    'public_no_wa' => BmnWaNumberNormalizer::normalize($data['public_no_wa']),
                    'public_email' => $data['public_email'],
                    'verification_status' => PeminjamanAsetBmn::VERIFICATION_PENDING,
                ]);
            });
        } catch (\Throwable $e) {
            if ($notaPath !== null) {
                $absolute = bmn_nota_dinas_path(basename($notaPath));
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }

            return back()->withInput()->with('error', 'Gagal menyimpan pengajuan: '.$e->getMessage());
        }

        $token = $this->publicVerificationService->initializePublicVerification($peminjaman);

        return redirect()
            ->route('bmn.public-verification.show', [
                'type' => 'peminjaman',
                'id' => $peminjaman->id,
                'token' => $token,
            ])
            ->with('success', 'Pengajuan tersimpan. Verifikasi Email dan WhatsApp untuk melanjutkan.');
    }

    public function createKembali(Request $request): View
    {
        abort_if($request->user() !== null, 404);

        $peminjaman = null;
        $kode = trim((string) $request->query('kode_peminjaman', ''));
        if ($kode !== '') {
            $peminjaman = PeminjamanAsetBmn::query()
                ->where('kode_peminjaman', $kode)
                ->first(['id', 'kode_peminjaman', 'status', 'tujuan_peminjaman', 'tanggal_pinjam']);
        }

        return view('public.peminjaman-internal.kembali', compact('peminjaman', 'kode'));
    }

    public function storeKembali(StorePengembalianAsetRequest $request, PengembalianAsetController $pengembalianController): RedirectResponse
    {
        return $pengembalianController->store($request);
    }

    private function storeNotaDinasPublic(UploadedFile $file): string
    {
        $dirRelative = PeminjamanAsetBmn::NOTA_DINAS_DIR;
        $dirAbsolute = bmn_nota_dinas_path();

        if (! is_dir($dirAbsolute)) {
            @mkdir($dirAbsolute, 0755, true);
        }

        if (! is_dir($dirAbsolute) || ! is_writable($dirAbsolute)) {
            throw new \RuntimeException('Folder upload nota dinas tidak tersedia.');
        }

        $ext = strtolower((string) $file->getClientOriginalExtension()) ?: 'pdf';
        $filename = sprintf('nota-dinas-%s-%s.%s', Carbon::now()->format('Ymd'), Str::random(8), $ext);
        $file->move($dirAbsolute, $filename);

        return $dirRelative.'/'.$filename;
    }

    private function generateKodePeminjaman(): string
    {
        $tgl = Carbon::now()->format('Ymd');
        for ($i = 0; $i < 5; $i++) {
            $kode = 'PJM-BMN-'.$tgl.'-'.strtoupper(Str::random(5));
            if (! PeminjamanAsetBmn::query()->where('kode_peminjaman', $kode)->exists()) {
                return $kode;
            }
        }

        return 'PJM-BMN-'.$tgl.'-'.strtoupper(Str::random(5)).'-'.substr((string) microtime(true), -4);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\StorePublicSewaRequest;
use App\Models\BMN\BmnSewaPengajuan;
use App\Models\BMN\BmnSewaTarif;
use App\Services\BMN\BmnPublicVerificationService;
use App\Services\BMN\BmnWaNumberNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicSewaController extends Controller
{
    public function __construct(
        private readonly BmnPublicVerificationService $publicVerificationService
    ) {
    }

    public function index(): View
    {
        return view('public.sewa.index');
    }

    public function createUmum(Request $request): View
    {
        return $this->formView('umum', $request);
    }

    public function createPegawai(Request $request): View
    {
        return $this->formView('pegawai', $request);
    }

    public function storeUmum(StorePublicSewaRequest $request): RedirectResponse
    {
        return $this->storeSewa($request, BmnSewaPengajuan::JENIS_UMUM);
    }

    public function storePegawai(StorePublicSewaRequest $request): RedirectResponse
    {
        return $this->storeSewa($request, BmnSewaPengajuan::JENIS_PEGAWAI);
    }

    private function formView(string $jenis, Request $request): View
    {
        $kategoriList = BmnSewaTarif::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('kategori');

        $selectedKategori = (string) $request->query('kategori', '');
        $selectedTarifId = (int) $request->query('tarif_id', 0);

        return view('public.sewa.form', compact('jenis', 'kategoriList', 'selectedKategori', 'selectedTarifId'));
    }

    private function storeSewa(StorePublicSewaRequest $request, string $jenis): RedirectResponse
    {
        $data = $request->validated();

        $suratPath = null;
        $suratOriginal = null;
        $buktiPath = null;
        $buktiOriginal = null;

        try {
            if ($request->hasFile('surat_resmi')) {
                /** @var UploadedFile $file */
                $file = $request->file('surat_resmi');
                $suratOriginal = (string) $file->getClientOriginalName();
                $suratPath = $this->storeUpload($file, BmnSewaPengajuan::SURAT_DIR, 'surat');
            }
            if ($request->hasFile('bukti_bayar')) {
                /** @var UploadedFile $file */
                $file = $request->file('bukti_bayar');
                $buktiOriginal = (string) $file->getClientOriginalName();
                $buktiPath = $this->storeUpload($file, BmnSewaPengajuan::BUKTI_BAYAR_DIR, 'bukti');
            }

            $pengajuan = DB::transaction(function () use ($data, $jenis, $suratPath, $suratOriginal, $buktiPath, $buktiOriginal): BmnSewaPengajuan {
                $mulai = Carbon::parse((string) $data['tanggal_mulai_sewa'])->startOfDay();
                $kembali = Carbon::parse((string) $data['tanggal_rencana_kembali'])->startOfDay();
                $jumlahHari = (int) $mulai->diffInDays($kembali, false) + 1;
                if ($jumlahHari < 1) {
                    $jumlahHari = 1;
                }

                /** @var BmnSewaTarif $tarif */
                $tarif = BmnSewaTarif::query()->findOrFail((int) $data['tarif_id']);
                $tarifDipakai = $tarif->tarifForJenis($jenis);
                $totalBiaya = round($tarifDipakai * $jumlahHari, 2);

                $attrs = [
                    'kode_sewa' => $this->generateKodeSewa(),
                    'jenis_penyewa' => $jenis,
                    'nama_lengkap' => $data['nama_lengkap'],
                    'nomor_wa' => BmnWaNumberNormalizer::normalize($data['nomor_wa']),
                    'email' => $data['email'],
                    'tarif_id' => (int) $data['tarif_id'],
                    'tanggal_mulai_sewa' => $data['tanggal_mulai_sewa'],
                    'tanggal_rencana_kembali' => $data['tanggal_rencana_kembali'],
                    'tujuan_peminjaman' => $data['tujuan_peminjaman'],
                    'surat_resmi_path' => $suratPath,
                    'bukti_bayar_path' => $buktiPath,
                    'catatan' => $data['catatan'] ?? null,
                    'payment_status' => BmnSewaPengajuan::PAYMENT_PENDING,
                    'status' => BmnSewaPengajuan::STATUS_PAYMENT_REVIEW,
                ];

                if (Schema::hasColumn('bmn_sewa_pengajuan', 'jumlah_hari_sewa')) {
                    $attrs['jumlah_hari_sewa'] = $jumlahHari;
                }
                if (Schema::hasColumn('bmn_sewa_pengajuan', 'total_biaya')) {
                    $attrs['total_biaya'] = $totalBiaya;
                }
                if (Schema::hasColumn('bmn_sewa_pengajuan', 'surat_resmi_original_name')) {
                    $attrs['surat_resmi_original_name'] = $suratOriginal;
                }
                if (Schema::hasColumn('bmn_sewa_pengajuan', 'bukti_bayar_original_name')) {
                    $attrs['bukti_bayar_original_name'] = $buktiOriginal;
                }
                if (Schema::hasColumn('bmn_sewa_pengajuan', 'public_token')) {
                    // public_token akan di-overwrite oleh
                    // BmnPublicVerificationService::initializePublicVerification
                    // setelah record dibuat. Set placeholder agar kolom NOT NULL
                    // (jika ada constraint) tetap valid.
                    $attrs['public_token'] = BmnSewaPengajuan::generatePublicToken();
                }
                if (Schema::hasColumn('bmn_sewa_pengajuan', 'verification_status')) {
                    $attrs['verification_status'] = BmnSewaPengajuan::VERIFICATION_PENDING;
                }

                return BmnSewaPengajuan::create($attrs);
            });
        } catch (\Throwable $e) {
            $this->cleanupUpload($suratPath);
            $this->cleanupUpload($buktiPath);

            return back()->withInput()->with('error', 'Gagal menyimpan pengajuan sewa: '.$e->getMessage());
        }

        // === Email verification (WAJIB) ===
        // WhatsApp OTP sementara dinonaktifkan karena integrasi WhatsApp
        // Business API belum aktif/berbayar. Nomor WA tetap disimpan untuk
        // kontak petugas, namun pemohon hanya wajib memverifikasi email.
        //
        // Jika tabel sewa belum memiliki kolom OTP (migration belum jalan),
        // fallback ke success page langsung agar deploy tidak hard-error.
        if (Schema::hasColumn('bmn_sewa_pengajuan', 'public_email_otp')) {
            $token = $this->publicVerificationService->initializePublicVerification($pengajuan);

            return redirect()
                ->route('bmn.public-verification.show', [
                    'type' => 'sewa',
                    'id' => $pengajuan->id,
                    'token' => $token,
                ])
                ->with('success', 'Pengajuan sewa tersimpan. Mohon verifikasi email Anda untuk melanjutkan.');
        }

        return redirect()
            ->route('public.sewa.success', $pengajuan->id)
            ->with('success', 'Pengajuan sewa berhasil dikirim.');
    }

    public function success(int $id): View
    {
        $pengajuan = BmnSewaPengajuan::query()
            ->with('tarif:id,nama_layanan,kategori,satuan')
            ->findOrFail($id);

        return view('public.sewa.success', compact('pengajuan'));
    }

    /**
     * Halaman cek status publik (tanpa login).
     * GET: form kode_sewa + (email/nomor_wa).
     * POST: validasi & tampilkan status jika cocok.
     */
    public function statusForm(Request $request): View
    {
        return view('public.sewa.status', [
            'pengajuan' => null,
            'old' => [
                'kode_sewa' => (string) $request->old('kode_sewa', $request->query('kode_sewa', '')),
                'identitas' => (string) $request->old('identitas', ''),
            ],
        ]);
    }

    public function statusLookup(Request $request): View
    {
        $request->validate([
            'kode_sewa' => ['required', 'string', 'max:100'],
            'identitas' => ['required', 'string', 'max:255'],
        ]);

        $kode = trim((string) $request->input('kode_sewa'));
        $identitas = trim((string) $request->input('identitas'));

        $pengajuan = BmnSewaPengajuan::query()
            ->with('tarif:id,nama_layanan,kategori,satuan')
            ->where('kode_sewa', $kode)
            ->where(function ($q) use ($identitas): void {
                $q->where('email', $identitas)
                    ->orWhere('nomor_wa', $identitas)
                    ->orWhere('nomor_wa', \App\Services\BMN\BmnWaNumberNormalizer::normalize($identitas));
            })
            ->first();

        if ($pengajuan === null) {
            return view('public.sewa.status', [
                'pengajuan' => null,
                'old' => ['kode_sewa' => $kode, 'identitas' => $identitas],
                'notFound' => true,
            ]);
        }

        return view('public.sewa.status', [
            'pengajuan' => $pengajuan,
            'old' => ['kode_sewa' => $kode, 'identitas' => $identitas],
        ]);
    }

    private function storeUpload(UploadedFile $file, string $dirRelative, string $prefix): string
    {
        $dirAbsolute = bmn_public_upload_path($dirRelative);

        if (! is_dir($dirAbsolute)) {
            @mkdir($dirAbsolute, 0755, true);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension()) ?: 'bin';
        $filename = sprintf('%s-%s-%s.%s', $prefix, Carbon::now()->format('Ymd'), Str::random(8), $ext);
        $file->move($dirAbsolute, $filename);

        return $dirRelative.'/'.$filename;
    }

    private function cleanupUpload(?string $path): void
    {
        if ($path === null) {
            return;
        }

        $abs = public_path(ltrim($path, '/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private function generateKodeSewa(): string
    {
        $tgl = Carbon::now()->format('Ymd');
        for ($i = 0; $i < 5; $i++) {
            $kode = 'SW-BMN-'.$tgl.'-'.strtoupper(Str::random(5));
            if (! BmnSewaPengajuan::query()->where('kode_sewa', $kode)->exists()) {
                return $kode;
            }
        }

        return 'SW-BMN-'.$tgl.'-'.strtoupper(Str::random(5)).'-'.substr((string) microtime(true), -4);
    }
}

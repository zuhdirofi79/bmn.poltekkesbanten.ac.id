<?php

declare(strict_types=1);

namespace App\Services\Documentation;

use App\Models\BhpKebersihan\BhpKebersihanAdmin;
use App\Models\Persediaan\PersediaanAdmin;
use App\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * Generate PDF Panduan Penggunaan SIM-BMN berdasarkan audit source code.
 */
final class DocumentationGeneratorService
{
    public const PDF_FILENAME = 'Panduan_Penggunaan_SIM_BMN_Poltekkes_Banten.pdf';

    public const APP_VERSION = '1.0.0-2026';

    /** @return array{pdf_path: string, relative_path: string, audit: array<string, mixed>, screenshots: array<string, string>} */
    public function generate(bool $forcePlaceholders = false): array
    {
        $audit = $this->auditApplication();
        $screenshotDir = $this->ensureScreenshotDirectory();
        $screenshots = $this->resolveScreenshots($screenshotDir, $forcePlaceholders);

        $logoPath = public_path('img/logo.png');
        if (! is_file($logoPath)) {
            throw new RuntimeException('Logo tidak ditemukan: public/img/logo.png');
        }

        $data = [
            'title' => 'PANDUAN PENGGUNAAN SISTEM INFORMASI BARANG MILIK NEGARA (BMN)',
            'subtitle' => 'POLTEKKES KEMENKES BANTEN',
            'version' => self::APP_VERSION,
            'year' => 2026,
            'generatedAt' => now()->timezone(config('app.timezone', 'Asia/Jakarta')),
            'appName' => config('app.name', 'SIM-BMN'),
            'logoPath' => $logoPath,
            'audit' => $audit,
            'roles' => $this->roleMatrix(),
            'screenshots' => $screenshots,
            'modules' => $audit['modules'],
            'loginUrls' => $this->loginUrls(),
        ];

        $pdf = Pdf::loadView('documentation.pdf.main', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
                'chroot' => base_path(),
            ]);

        $outputDir = storage_path('app/public/documentation');
        File::ensureDirectoryExists($outputDir);
        $pdfPath = $outputDir.DIRECTORY_SEPARATOR.self::PDF_FILENAME;

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->get_canvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(42, 820, 'Panduan Penggunaan SIM-BMN | Poltekkes Kemenkes Banten | 2026', $font, 8, [0.35, 0.4, 0.48]);
        $canvas->page_text(500, 820, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.35, 0.4, 0.48]);

        file_put_contents($pdfPath, $dompdf->output());

        return [
            'pdf_path' => $pdfPath,
            'relative_path' => 'storage/app/public/documentation/'.self::PDF_FILENAME,
            'audit' => $audit,
            'screenshots' => $screenshots,
        ];
    }

    /** @return array<string, mixed> */
    public function auditApplication(): array
    {
        $routeNames = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->values()
            ->all();

        $controllers = $this->listPhpFiles(app_path('Http/Controllers'));
        $models = $this->listPhpFiles(app_path('Models'));
        $views = $this->listFiles(resource_path('views'), ['blade.php']);

        $modules = [
            'portal' => [
                'name' => 'Portal Layanan BMN',
                'status' => 'aktif',
                'routes' => ['public.landing'],
                'urls' => ['/'],
            ],
            'aset' => [
                'name' => 'Aset BMN',
                'status' => 'aktif',
                'routes' => array_values(array_filter($routeNames, fn ($n) => str_starts_with((string) $n, 'bmn.aset') || $n === 'bmn.dashboard.index' || str_starts_with((string) $n, 'bmn.mutasi') || $n === 'bmn.laporan.index')),
                'urls' => ['/login', '/bmn', '/bmn/aset', '/bmn/mutasi', '/bmn/laporan'],
                'features' => [
                    'Dashboard BMN',
                    'CRUD Data Aset',
                    'Filter & pencarian multi-kriteria',
                    'Import Excel / template',
                    'Export Excel (semua & terpilih)',
                    'Bulk delete',
                    'Upload foto aset',
                    'Mutasi lokasi',
                    'Laporan BMN',
                ],
            ],
            'peminjaman' => [
                'name' => 'Peminjaman BMN',
                'status' => 'aktif',
                'routes' => array_values(array_filter($routeNames, fn ($n) => str_contains((string) $n, 'peminjaman') || str_contains((string) $n, 'pengembalian'))),
                'urls' => [
                    '/public/peminjaman-internal',
                    '/bmn/peminjaman-aset/create',
                    '/bmn/peminjaman-aset',
                    '/bmn/pengembalian-aset',
                ],
                'features' => [
                    'Pengajuan publik/internal',
                    'Upload dokumen (nota dinas)',
                    'Screening Rumah Tangga',
                    'Approval berjenjang (Singgih → Hendri)',
                    'Penandaan dipinjam',
                    'Pengembalian & verifikasi',
                    'Verifikasi publik via token',
                ],
            ],
            'sewa' => [
                'name' => 'Sewa BMN',
                'status' => 'aktif',
                'routes' => array_values(array_filter($routeNames, fn ($n) => str_starts_with((string) $n, 'public.sewa') || str_starts_with((string) $n, 'sewa-admin.') || str_starts_with((string) $n, 'bmn.sewa'))),
                'urls' => ['/public/sewa', '/sewa-admin/login', '/bmn/sewa'],
                'features' => [
                    'Pengajuan sewa umum & pegawai',
                    'Cek status pengajuan',
                    'Master tarif + foto (SK Tarif BLU)',
                    'Verifikasi pembayaran',
                    'Koordinasi penyewa/tim teknis',
                    'Pelaporan bulanan (Excel/CSV)',
                    'Komplain asrama',
                ],
            ],
            'pemeliharaan' => [
                'name' => 'Pemeliharaan BMN',
                'status' => 'informasional',
                'routes' => ['public.pemeliharaan.index', 'bmn.pemeliharaan-aset.index'],
                'urls' => ['/public/pemeliharaan', '/bmn/pemeliharaan-aset'],
                'features' => [
                    'Halaman informasi alur pemeliharaan (publik)',
                    'Dashboard pemeliharaan admin (placeholder statistik)',
                    'Pengajuan resmi masih melalui nota dinas/koordinasi unit BMN',
                ],
                'note' => 'Modul belum memiliki form pengajuan online penuh; halaman bersifat panduan alur.',
            ],
            'persediaan' => [
                'name' => 'Persediaan Aset BMN',
                'status' => 'aktif',
                'routes' => array_values(array_filter($routeNames, fn ($n) => str_starts_with((string) $n, 'public.persediaan') || str_starts_with((string) $n, 'persediaan.admin'))),
                'urls' => ['/public/persediaan', '/persediaan/login'],
                'features' => [
                    'Pemilihan unit kerja',
                    'Registrasi & login pemohon',
                    'Permintaan barang + PDF',
                    'QR akses pintu & QR rak (Direktorat)',
                    'Admin: dashboard, master barang, barcode',
                    'Pembelian (input & import)',
                    'Approval permintaan & pengeluaran stok',
                    'Stok akhir, stock opname, laporan',
                    'Master rak + mutasi barang antar rak',
                ],
            ],
            'bhp' => [
                'name' => 'BHP Kebersihan',
                'status' => 'aktif',
                'routes' => array_values(array_filter($routeNames, fn ($n) => str_starts_with((string) $n, 'public.bhp-kebersihan') || str_starts_with((string) $n, 'bhp.admin'))),
                'urls' => ['/public/bhp-kebersihan', '/bhp-kebersihan/login'],
                'features' => [
                    'Landing publik + scan/cari barcode',
                    'Pengajuan pengambilan via token barang',
                    'Master barang + foto + barcode',
                    'Import foto OCR',
                    'Verifikasi permintaan',
                    'Proses pengambilan',
                    'Stok akhir, opname, laporan',
                ],
            ],
            'penghapusan' => [
                'name' => 'Penghapusan BMN',
                'status' => 'dalam_pengembangan',
                'routes' => ['bmn.penghapusan-aset.index'],
                'urls' => ['/bmn/penghapusan-aset'],
                'features' => ['Dashboard placeholder — fitur usulan/verifikasi belum aktif'],
            ],
        ];

        return [
            'scanned_at' => now()->toDateTimeString(),
            'route_count' => count($routeNames),
            'controller_count' => count($controllers),
            'model_count' => count($models),
            'view_count' => count($views),
            'middleware' => [
                'auth',
                'role (Spatie)',
                'sewa.admin',
                'persediaan.pemohon',
                'persediaan.admin',
                'bhp.admin',
            ],
            'modules' => $modules,
            'guards' => [
                'web (default Laravel UI)',
                'persediaan (pemohon persediaan)',
            ],
        ];
    }

    /** @return list<array{role: string, menu: string, fungsi: string}> */
    public function roleMatrix(): array
    {
        return [
            [
                'role' => 'Superadmin BMN (users.id di BMN_SUPERADMIN_IDS)',
                'menu' => 'Seluruh menu modul /bmn + bypass role spesifik',
                'fungsi' => 'Akses penuh administrasi BMN, peminjaman, dan sewa sesuai konstanta User.php',
            ],
            [
                'role' => 'Admin (Spatie role:admin)',
                'menu' => '/admin/* (users, master data), /bmn/* (jika login)',
                'fungsi' => 'Manajemen user & master data aplikasi; akses panel BMN setelah autentikasi',
            ],
            [
                'role' => 'Admin BMN Approver (role:admin + can_approve_bmn)',
                'menu' => 'Peminjaman aset — approval',
                'fungsi' => 'Menyetujui/menolak pengajuan peminjaman sesuai wewenang',
            ],
            [
                'role' => 'Verifikator Peminjaman (Hendri / Singgih — ID tetap)',
                'menu' => 'Screening, approve-singgih, approve-hendri, final report',
                'fungsi' => 'Alur persetujuan berjenjang peminjaman BMN',
            ],
            [
                'role' => 'Rumah Tangga BMN (Munawir / Singgih)',
                'menu' => 'Screening peminjaman',
                'fungsi' => 'Pemeriksaan kesiapan aset sebelum approval lanjutan',
            ],
            [
                'role' => 'Pemohon Peminjaman (role:jurusan / jurusan whitelist)',
                'menu' => 'Form peminjaman (publik/internal & /bmn/peminjaman-aset/create)',
                'fungsi' => 'Mengajukan peminjaman aset BMN unit kerja',
            ],
            [
                'role' => 'Admin Sewa — Verifikator Pembayaran (Indah)',
                'menu' => '/sewa-admin (tarif, sewa, konfirmasi/tolak pembayaran)',
                'fungsi' => 'Verifikasi bukti bayar sewa BLU',
            ],
            [
                'role' => 'Admin Sewa — Koordinator (Retno / Singgih)',
                'menu' => '/sewa-admin (koordinasi penyewa & teknis)',
                'fungsi' => 'Koordinasi pasca-pembayaran dan status lapangan',
            ],
            [
                'role' => 'Superadmin Sewa (adminbmn)',
                'menu' => 'Seluruh menu sewa-admin',
                'fungsi' => 'Akses penuh modul Admin Sewa BMN',
            ],
            [
                'role' => 'Pemohon Publik Sewa',
                'menu' => '/public/sewa (umum/pegawai, cek status)',
                'fungsi' => 'Mengajukan sewa tanpa login admin',
            ],
            [
                'role' => 'Pemohon Persediaan (guard persediaan)',
                'menu' => '/public/persediaan/{unit}/* permintaan',
                'fungsi' => 'Registrasi, login, ajukan & pantau permintaan barang',
            ],
            [
                'role' => 'Admin Persediaan — '.PersediaanAdmin::ROLE_SUPERADMIN,
                'menu' => 'Semua unit: dashboard, master, pembelian, permintaan, opname, laporan, rak',
                'fungsi' => 'Supervisi stok seluruh unit kerja',
            ],
            [
                'role' => 'Admin Persediaan per Unit (keperawatan/kebidanan/tlm/direktorat/pppk/pimpinan)',
                'menu' => 'Menu admin persediaan terbatas unit masing-masing',
                'fungsi' => 'Kelola stok & approval permintaan unit',
            ],
            [
                'role' => 'Superadmin BHP ('.BhpKebersihanAdmin::ROLE_SUPERADMIN.')',
                'menu' => 'Semua menu /bhp-kebersihan',
                'fungsi' => 'Akses penuh modul BHP Kebersihan',
            ],
            [
                'role' => 'Admin Master BHP',
                'menu' => 'Dashboard, stok, opname, laporan, master barang, pengaturan',
                'fungsi' => 'Kelola master & stok BHP',
            ],
            [
                'role' => 'Verifikator BHP',
                'menu' => 'Dashboard, permintaan, laporan',
                'fungsi' => 'Setujui/tolak permintaan pengambilan',
            ],
            [
                'role' => 'Pengambilan BHP',
                'menu' => 'Permintaan, pengambilan',
                'fungsi' => 'Proses penyerahan barang ke pemohon',
            ],
            [
                'role' => 'Pengguna Publik BHP',
                'menu' => '/public/bhp-kebersihan',
                'fungsi' => 'Cari/scan barcode & ajukan pengambilan',
            ],
            [
                'role' => 'PLP (Spatie role:plp)',
                'menu' => '/petugas/* (lab legacy)',
                'fungsi' => 'Modul laboratorium legacy — di luar fokus portal BMN',
            ],
            [
                'role' => 'User/Customer (Spatie)',
                'menu' => '/user/* (lab legacy)',
                'fungsi' => 'Peminjaman lab legacy — terpisah dari portal BMN publik',
            ],
        ];
    }

    /** @return array<string, string> */
    public function loginUrls(): array
    {
        return [
            'Portal publik' => '/',
            'Login aplikasi (Laravel UI)' => '/login',
            'Admin Sewa BMN' => '/sewa-admin/login',
            'Admin Persediaan' => '/persediaan/login',
            'Admin BHP Kebersihan' => '/bhp-kebersihan/login',
            'Registrasi pemohon persediaan' => '/public/persediaan/{unit}/register',
        ];
    }

    public function ensureScreenshotDirectory(): string
    {
        $dir = base_path('documentation/screenshots');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /**
     * @return array<string, string> key => absolute path
     */
    public function resolveScreenshots(string $dir, bool $forcePlaceholders = false): array
    {
        $keys = [
            'homepage' => '01_homepage',
            'login' => '02_login',
            'dashboard_aset' => '03_dashboard_aset',
            'master_aset' => '04_master_aset',
            'peminjaman' => '05_peminjaman',
            'sewa' => '06_sewa',
            'pemeliharaan' => '07_pemeliharaan',
            'persediaan' => '08_persediaan',
            'bhp' => '09_bhp_kebersihan',
            'sewa_admin' => '10_sewa_admin_login',
        ];

        $resolved = [];
        foreach ($keys as $key => $basename) {
            $candidates = [
                $dir.DIRECTORY_SEPARATOR.$basename.'.png',
                $dir.DIRECTORY_SEPARATOR.$basename.'.jpg',
            ];
            $found = null;
            foreach ($candidates as $candidate) {
                if (! $forcePlaceholders && is_file($candidate) && filesize($candidate) > 1024) {
                    $found = $candidate;
                    break;
                }
            }
            if ($found === null) {
                $found = $this->createPlaceholderScreenshot(
                    $dir.DIRECTORY_SEPARATOR.$basename.'.png',
                    $this->screenshotLabel($key)
                );
            }
            $resolved[$key] = $found;
        }

        return $resolved;
    }

    private function screenshotLabel(string $key): string
    {
        return match ($key) {
            'homepage' => 'Portal Layanan BMN — Beranda',
            'login' => 'Halaman Login Admin (/login)',
            'dashboard_aset' => 'Dashboard Aset BMN',
            'master_aset' => 'Master / Daftar Aset BMN',
            'peminjaman' => 'Layanan Peminjaman BMN',
            'sewa' => 'Layanan Sewa BMN',
            'pemeliharaan' => 'Layanan Pemeliharaan BMN',
            'persediaan' => 'Persediaan Aset BMN',
            'bhp' => 'BHP Kebersihan',
            'sewa_admin' => 'Login Admin Sewa BMN',
            default => 'SIM-BMN Poltekkes Banten',
        };
    }

    private function createPlaceholderScreenshot(string $path, string $label): string
    {
        $width = 1200;
        $height = 675;
        $im = imagecreatetruecolor($width, $height);
        $blue = imagecolorallocate($im, 22, 49, 92);
        $green = imagecolorallocate($im, 46, 139, 87);
        $white = imagecolorallocate($im, 255, 255, 255);
        $light = imagecolorallocate($im, 232, 240, 248);

        imagefilledrectangle($im, 0, 0, $width, $height, $blue);
        imagefilledrectangle($im, 0, $height - 90, $width, $height, $green);
        imagefilledrectangle($im, 40, 40, $width - 40, $height - 130, $light);

        $title = 'SIM-BMN Poltekkes Kemenkes Banten';
        imagestring($im, 5, 60, 70, $title, $blue);
        imagestring($im, 5, 60, 110, $label, $blue);
        imagestring($im, 3, 60, 160, 'Screenshot placeholder — jalankan scripts/capture-documentation-screenshots.js', $blue);
        imagestring($im, 3, 60, $height - 60, 'Dokumentasi resmi 2026', $white);

        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    /** @return list<string> */
    private function listPhpFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = File::allFiles($dir);

        return array_map(fn ($f) => $f->getRelativePathname(), $files);
    }

    /**
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function listFiles(string $dir, array $extensions): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = File::allFiles($dir);

        return array_values(array_filter(
            array_map(fn ($f) => $f->getRelativePathname(), $files),
            function (string $path) use ($extensions): bool {
                foreach ($extensions as $ext) {
                    if (str_ends_with($path, $ext)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }
}

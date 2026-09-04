<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', 'Public\PublicLandingController@index')->name('public.landing');

// ===== BMN PUBLIC PORTAL (tanpa login) =====
Route::prefix('public')->name('public.')->middleware('throttle:60,1')->group(function (): void {
    Route::get('peminjaman-internal', 'Public\PublicPeminjamanInternalController@index')
        ->name('peminjaman-internal.index');
    Route::get('peminjaman-internal/pinjam', 'Public\PublicPeminjamanInternalController@createPinjam')
        ->name('peminjaman-internal.pinjam');
    Route::post('peminjaman-internal/pinjam', 'Public\PublicPeminjamanInternalController@storePinjam')
        ->middleware('throttle:10,1')
        ->name('peminjaman-internal.pinjam.store');
    Route::get('peminjaman-internal/kembali', 'Public\PublicPeminjamanInternalController@createKembali')
        ->name('peminjaman-internal.kembali');
    Route::post('peminjaman-internal/kembali', 'Public\PublicPeminjamanInternalController@storeKembali')
        ->middleware('throttle:10,1')
        ->name('peminjaman-internal.kembali.store');

    Route::get('sewa', 'Public\PublicSewaController@index')->name('sewa.index');
    Route::get('sewa/status', 'Public\PublicSewaController@statusForm')->name('sewa.status');
    Route::post('sewa/status', 'Public\PublicSewaController@statusLookup')
        ->middleware('throttle:20,1')
        ->name('sewa.status.submit');
    Route::get('sewa/umum', 'Public\PublicSewaController@createUmum')->name('sewa.umum');
    Route::post('sewa/umum', 'Public\PublicSewaController@storeUmum')
        ->middleware('throttle:10,1')
        ->name('sewa.umum.store');
    Route::get('sewa/pegawai', 'Public\PublicSewaController@createPegawai')->name('sewa.pegawai');
    Route::post('sewa/pegawai', 'Public\PublicSewaController@storePegawai')
        ->middleware('throttle:10,1')
        ->name('sewa.pegawai.store');
    Route::get('sewa/success/{id}', 'Public\PublicSewaController@success')
        ->where('id', '[0-9]+')
        ->name('sewa.success');

    Route::get('pemeliharaan', 'Public\PublicPemeliharaanController@index')->name('pemeliharaan.index');
});

// ===== PERSEDIAAN ASET BMN — PUBLIK + PEMOHON (guard 'persediaan') =====
Route::prefix('public/persediaan')->name('public.persediaan.')->middleware('throttle:60,1')->group(function (): void {
    // Pilih unit kerja (folder cards)
    Route::get('/', 'Persediaan\UnitPickerController@index')->name('index');

    // Auth pemohon (terpisah dari admin BMN & admin sewa) — login via NIP
    Route::get('{unit}/register', 'Persediaan\PemohonAuthController@showRegister')
        ->where('unit', 'keperawatan|kebidanan|tlm|direktorat')->name('register');
    Route::post('{unit}/register', 'Persediaan\PemohonAuthController@register')
        ->where('unit', 'keperawatan|kebidanan|tlm|direktorat')
        ->middleware('throttle:10,1')->name('register.store');
    Route::get('{unit}/login', 'Persediaan\PemohonAuthController@showLogin')
        ->where('unit', 'keperawatan|kebidanan|tlm|direktorat')->name('login');
    Route::post('{unit}/login', 'Persediaan\PemohonAuthController@login')
        ->where('unit', 'keperawatan|kebidanan|tlm|direktorat')
        ->middleware('throttle:10,1')->name('login.attempt');
    Route::post('logout', 'Persediaan\PemohonAuthController@logout')->name('logout');

    // Area pemohon login
    Route::middleware('persediaan.pemohon')->group(function (): void {
        Route::get('permintaan', 'Persediaan\PermintaanController@index')->name('permintaan.index');
        Route::get('permintaan/create', 'Persediaan\PermintaanController@create')->name('permintaan.create');
        Route::post('permintaan', 'Persediaan\PermintaanController@store')
            ->middleware('throttle:20,1')->name('permintaan.store');
        Route::post('permintaan/{id}/ajukan', 'Persediaan\PermintaanController@ajukan')
            ->where('id', '[0-9]+')->name('permintaan.ajukan');
        Route::get('permintaan/{id}', 'Persediaan\PermintaanController@show')
            ->where('id', '[0-9]+')->name('permintaan.show');
        Route::get('permintaan/{id}/pdf', 'Persediaan\PermintaanController@pdf')
            ->where('id', '[0-9]+')->name('permintaan.pdf');
    });
});

// ===== ADMIN SEWA BMN (login terpisah dari admin BMN utama) =====
// Hanya 3 user real (Bu Indah / Bu Retno / Pak Singgih) yang lolos middleware 'sewa.admin'.
Route::prefix('sewa-admin')->name('sewa-admin.')->group(function (): void {
    // Auth (guest accessible)
    Route::get('login', 'SewaAdmin\SewaAdminAuthController@showLoginForm')->name('login.show');
    Route::post('login', 'SewaAdmin\SewaAdminAuthController@login')
        ->middleware('throttle:10,1')
        ->name('login.attempt');
    Route::post('logout', 'SewaAdmin\SewaAdminAuthController@logout')->name('logout');

    // Protected area
    Route::middleware(['auth', 'sewa.admin'])->group(function (): void {
        Route::get('dashboard', 'SewaAdmin\SewaAdminDashboardController@index')->name('dashboard');

        Route::prefix('tarif')->name('tarif.')->group(function (): void {
            Route::get('/', 'SewaAdmin\SewaAdminTarifController@index')->name('index');
            Route::get('{id}', 'SewaAdmin\SewaAdminTarifController@show')
                ->where('id', '[0-9]+')->name('show');
            Route::post('{id}/fotos', 'SewaAdmin\SewaAdminTarifController@uploadPhotos')
                ->where('id', '[0-9]+')->name('fotos.upload');
            Route::post('{tarifId}/fotos/{fotoId}/primary', 'SewaAdmin\SewaAdminTarifController@setPrimary')
                ->where(['tarifId' => '[0-9]+', 'fotoId' => '[0-9]+'])->name('fotos.primary');
            Route::post('{tarifId}/fotos/{fotoId}/move', 'SewaAdmin\SewaAdminTarifController@movePhoto')
                ->where(['tarifId' => '[0-9]+', 'fotoId' => '[0-9]+'])->name('fotos.move');
            Route::delete('{tarifId}/fotos/{fotoId}', 'SewaAdmin\SewaAdminTarifController@deletePhoto')
                ->where(['tarifId' => '[0-9]+', 'fotoId' => '[0-9]+'])->name('fotos.delete');
        });

        Route::prefix('sewa')->name('sewa.')->group(function (): void {
            Route::get('/', 'SewaAdmin\SewaAdminSewaController@index')->name('index');
            Route::get('{id}', 'SewaAdmin\SewaAdminSewaController@show')
                ->where('id', '[0-9]+')->name('show');
            Route::post('{id}/forward-to-verifier', 'SewaAdmin\SewaAdminSewaController@forwardToVerifier')
                ->where('id', '[0-9]+')->name('forward-to-verifier');
            Route::post('{id}/confirm-payment', 'SewaAdmin\SewaAdminSewaController@confirmPayment')
                ->where('id', '[0-9]+')->name('confirm-payment');
            Route::post('{id}/reject-payment', 'SewaAdmin\SewaAdminSewaController@rejectPayment')
                ->where('id', '[0-9]+')->name('reject-payment');
            Route::post('{id}/mark-sent-to-team', 'SewaAdmin\SewaAdminSewaController@markSentToTeam')
                ->where('id', '[0-9]+')->name('mark-sent-to-team');
            Route::post('{id}/mark-coordination-done', 'SewaAdmin\SewaAdminSewaController@markCoordinationDone')
                ->where('id', '[0-9]+')->name('mark-coordination-done');
        });

        Route::prefix('pelaporan-bulanan')->name('pelaporan.')->group(function (): void {
            Route::get('/', 'SewaAdmin\SewaAdminPelaporanController@index')->name('index');
            Route::get('export-excel', 'SewaAdmin\SewaAdminPelaporanController@exportExcel')->name('export-excel');
            Route::get('export-csv', 'SewaAdmin\SewaAdminPelaporanController@exportCsv')->name('export-csv');
        });

        Route::get('komplain-asrama', 'SewaAdmin\SewaAdminKomplainController@index')->name('komplain.index');
    });
});

// ===== ADMIN PERSEDIAAN (session custom; user_id 3235 & 3243) =====
Route::prefix('persediaan')->name('persediaan.admin.')->group(function (): void {
    Route::get('login', 'PersediaanAdmin\AuthController@showLoginForm')->name('login.show');
    Route::post('login', 'PersediaanAdmin\AuthController@login')
        ->middleware('throttle:10,1')->name('login.attempt');
    Route::post('logout', 'PersediaanAdmin\AuthController@logout')->name('logout');

    Route::middleware('persediaan.admin')->group(function (): void {
        Route::get('dashboard', 'PersediaanAdmin\DashboardController@index')->name('dashboard');

        // Permintaan
        Route::prefix('permintaan')->name('permintaan.')->group(function (): void {
            Route::get('/', 'PersediaanAdmin\PermintaanController@index')->name('index');
            Route::get('{id}', 'PersediaanAdmin\PermintaanController@show')->where('id', '[0-9]+')->name('show');
            Route::post('{id}/setujui', 'PersediaanAdmin\PermintaanController@setujui')->where('id', '[0-9]+')->name('setujui');
            Route::post('{id}/tolak', 'PersediaanAdmin\PermintaanController@tolak')->where('id', '[0-9]+')->name('tolak');
            Route::post('{id}/keluarkan', 'PersediaanAdmin\PermintaanController@keluarkan')->where('id', '[0-9]+')->name('keluarkan');
            Route::get('{id}/pdf', 'PersediaanAdmin\PermintaanController@pdf')->where('id', '[0-9]+')->name('pdf');
        });

        // Pembelian
        Route::prefix('pembelian')->name('pembelian.')->group(function (): void {
            Route::get('/', 'PersediaanAdmin\PembelianController@index')->name('index');
            Route::get('create', 'PersediaanAdmin\PembelianController@create')->name('create');
            Route::post('/', 'PersediaanAdmin\PembelianController@store')->name('store');
            Route::get('{id}', 'PersediaanAdmin\PembelianController@show')->where('id', '[0-9]+')->name('show');
            Route::get('import/template', 'PersediaanAdmin\PembelianController@importTemplate')->name('import.template');
            Route::post('import', 'PersediaanAdmin\PembelianController@import')->name('import');
        });

        // Stok Akhir
        Route::get('stok-akhir', 'PersediaanAdmin\StokAkhirController@index')->name('stok-akhir.index');
        Route::get('stok-akhir/export', 'PersediaanAdmin\StokAkhirController@exportExcel')->name('stok-akhir.export');

        // Stock Opname
        Route::prefix('opname')->name('opname.')->group(function (): void {
            Route::get('/', 'PersediaanAdmin\StockOpnameController@index')->name('index');
            Route::get('create', 'PersediaanAdmin\StockOpnameController@create')->name('create');
            Route::post('/', 'PersediaanAdmin\StockOpnameController@store')->name('store');
            Route::get('{id}', 'PersediaanAdmin\StockOpnameController@show')->where('id', '[0-9]+')->name('show');
            Route::get('{id}/laporan', 'PersediaanAdmin\StockOpnameController@laporanPdf')->where('id', '[0-9]+')->name('laporan');
            Route::get('{id}/laporan-excel', 'PersediaanAdmin\StockOpnameController@laporanExcel')->where('id', '[0-9]+')->name('laporan-excel');
            Route::get('{id}/berita-acara', 'PersediaanAdmin\StockOpnameController@beritaAcara')->where('id', '[0-9]+')->name('berita-acara');
            Route::get('{id}/export', 'PersediaanAdmin\StockOpnameController@exportExcel')->where('id', '[0-9]+')->name('export');
        });

        // Laporan
        Route::get('laporan', 'PersediaanAdmin\LaporanController@index')->name('laporan.index');
        Route::get('laporan/export', 'PersediaanAdmin\LaporanController@exportExcel')->name('laporan.export');

        // Master Barang
        Route::prefix('master-barang')->name('master-barang.')->group(function (): void {
            Route::get('/', 'PersediaanAdmin\MasterBarangController@index')->name('index');
            Route::get('template-excel', 'PersediaanAdmin\MasterBarangController@templateExcel')->name('template');
            Route::post('import-excel', 'PersediaanAdmin\MasterBarangController@importExcel')->name('import');
            Route::post('/', 'PersediaanAdmin\MasterBarangController@store')->name('store');
            Route::put('{id}', 'PersediaanAdmin\MasterBarangController@update')->where('id', '[0-9]+')->name('update');
            Route::delete('{id}', 'PersediaanAdmin\MasterBarangController@destroy')->where('id', '[0-9]+')->name('destroy');
        });

        // Pengaturan
        Route::get('pengaturan', 'PersediaanAdmin\PengaturanController@index')->name('pengaturan.index');
    });
});

// ===== BHP KEBERSIHAN — PUBLIK + ADMIN (modul terpisah) =====
Route::prefix('public/bhp-kebersihan')->name('public.bhp-kebersihan.')->middleware('throttle:60,1')->group(function (): void {
    Route::get('/', 'BhpKebersihan\LandingController@index')->name('index');
    Route::post('cari', 'BhpKebersihan\LandingController@search')
        ->middleware('throttle:20,1')->name('cari');
    Route::get('barang/{token}', 'BhpKebersihan\BarangController@show')->name('barang.show');
    Route::post('barang/{token}', 'BhpKebersihan\BarangController@store')
        ->middleware('throttle:10,1')->name('barang.store');
    Route::get('success/{nomor}', 'BhpKebersihan\BarangController@success')->name('success');
});

Route::prefix('bhp-kebersihan')->name('bhp.admin.')->group(function (): void {
    Route::get('login', 'BhpKebersihanAdmin\AuthController@showLoginForm')->name('login.show');
    Route::post('login', 'BhpKebersihanAdmin\AuthController@login')
        ->middleware('throttle:10,1')->name('login.attempt');
    Route::post('logout', 'BhpKebersihanAdmin\AuthController@logout')->name('logout');

    Route::middleware('bhp.admin')->group(function (): void {
        Route::get('dashboard', 'BhpKebersihanAdmin\DashboardController@index')->name('dashboard');

        Route::prefix('permintaan')->name('permintaan.')->group(function (): void {
            Route::get('/', 'BhpKebersihanAdmin\PermintaanController@index')->name('index');
            Route::get('{id}', 'BhpKebersihanAdmin\PermintaanController@show')->where('id', '[0-9]+')->name('show');
            Route::post('{id}/setujui', 'BhpKebersihanAdmin\PermintaanController@setujui')->where('id', '[0-9]+')->name('setujui');
            Route::post('{id}/tolak', 'BhpKebersihanAdmin\PermintaanController@tolak')->where('id', '[0-9]+')->name('tolak');
        });

        Route::prefix('pengambilan')->name('pengambilan.')->group(function (): void {
            Route::get('/', 'BhpKebersihanAdmin\PengambilanController@index')->name('index');
            Route::get('{id}', 'BhpKebersihanAdmin\PengambilanController@show')->where('id', '[0-9]+')->name('show');
            Route::post('{id}/sudah-diambil', 'BhpKebersihanAdmin\PengambilanController@sudahDiambil')->where('id', '[0-9]+')->name('sudah-diambil');
        });

        Route::get('stok-akhir', 'BhpKebersihanAdmin\StokAkhirController@index')->name('stok-akhir.index');
        Route::get('stok-akhir/export', 'BhpKebersihanAdmin\StokAkhirController@exportExcel')->name('stok-akhir.export');

        Route::prefix('opname')->name('opname.')->group(function (): void {
            Route::get('/', 'BhpKebersihanAdmin\StockOpnameController@index')->name('index');
            Route::get('create', 'BhpKebersihanAdmin\StockOpnameController@create')->name('create');
            Route::post('/', 'BhpKebersihanAdmin\StockOpnameController@store')->name('store');
            Route::get('{id}', 'BhpKebersihanAdmin\StockOpnameController@show')->where('id', '[0-9]+')->name('show');
            Route::get('{id}/export', 'BhpKebersihanAdmin\StockOpnameController@exportExcel')->where('id', '[0-9]+')->name('export');
        });

        Route::get('laporan', 'BhpKebersihanAdmin\LaporanController@index')->name('laporan.index');
        Route::get('laporan/export', 'BhpKebersihanAdmin\LaporanController@exportExcel')->name('laporan.export');

        Route::prefix('master-barang')->name('master-barang.')->group(function (): void {
            Route::get('/', 'BhpKebersihanAdmin\MasterBarangController@index')->name('index');
            Route::get('export-excel', 'BhpKebersihanAdmin\MasterBarangController@exportExcel')->name('export');
            Route::get('barcode-batch', 'BhpKebersihanAdmin\MasterBarangController@barcodeBatch')->name('barcode-batch');
            Route::post('generate-all-barcodes', 'BhpKebersihanAdmin\MasterBarangController@generateAllBarcodes')->name('generate-all-barcodes');
            Route::post('/', 'BhpKebersihanAdmin\MasterBarangController@store')->name('store');
            Route::get('{id}', 'BhpKebersihanAdmin\MasterBarangController@show')->where('id', '[0-9]+')->name('show');
            Route::put('{id}', 'BhpKebersihanAdmin\MasterBarangController@update')->where('id', '[0-9]+')->name('update');
            Route::delete('{id}', 'BhpKebersihanAdmin\MasterBarangController@destroy')->where('id', '[0-9]+')->name('destroy');
            Route::get('{id}/print-barcode', 'BhpKebersihanAdmin\MasterBarangController@barcodePrint')->where('id', '[0-9]+')->name('print-barcode');
            Route::get('{id}/download-barcode', 'BhpKebersihanAdmin\MasterBarangController@barcodeDownload')->where('id', '[0-9]+')->name('download-barcode');
            Route::post('{id}/generate-barcode', 'BhpKebersihanAdmin\MasterBarangController@generateBarcode')->where('id', '[0-9]+')->name('generate-barcode');
            Route::post('{id}/regenerate-barcode', 'BhpKebersihanAdmin\MasterBarangController@regenerateBarcode')->where('id', '[0-9]+')->name('regenerate-barcode');
        });

        Route::get('pengaturan', 'BhpKebersihanAdmin\PengaturanController@index')->name('pengaturan.index');
    });
});

Auth::routes();
Route::get('/changePassword','Auth\ChangePasswordController@showChangePasswordForm');
Route::post('/changePassword','Auth\ChangePasswordController@changePassword')->name('changePassword');
Route::get('/profile', 'HomeController@profile')->name('profile');
Route::post('/update_profile', 'HomeController@update_profile');
Route::post('/upload_foto', 'HomeController@upload_foto');


Route::get('/home', 'HomeController@profile')->name('home');

// ===== BMN PUBLIC (tanpa login) =====
Route::prefix('bmn')->name('bmn.')->middleware('throttle:60,1')->group(function (): void {
    Route::get('peminjaman-aset/create', 'BMN\PeminjamanAsetController@create')->name('peminjaman-aset.create');
    Route::post('peminjaman-aset', 'BMN\PeminjamanAsetController@store')
        ->middleware('throttle:10,1')
        ->name('peminjaman-aset.store');

    Route::get('pengembalian-aset/create', 'BMN\PengembalianAsetController@createPublic')
        ->name('pengembalian-aset.create-public');
    Route::post('pengembalian-aset', 'BMN\PengembalianAsetController@store')
        ->middleware('throttle:10,1')
        ->name('pengembalian-aset.store');

    Route::get('public-verification/{type}/{id}/{token}', 'BMN\BmnPublicVerificationController@show')
        ->where('type', 'peminjaman|pengembalian|sewa')
        ->where('id', '[0-9]+')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('public-verification.show');
    Route::post('public-verification/{type}/{id}/{token}', 'BMN\BmnPublicVerificationController@verify')
        ->middleware('throttle:10,1')
        ->where('type', 'peminjaman|pengembalian|sewa')
        ->where('id', '[0-9]+')
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('public-verification.verify');
});

Route::group(['prefix' => 'bmn', 'middleware' => ['auth']], function () {
    Route::name('bmn.')->group(function () {
        Route::get('/', 'BMN\BmnDashboardController@index')->name('dashboard.index');
        Route::get('aset/template', 'BMN\AsetController@downloadTemplate')->name('aset.template');
        // PENTING: route export HARUS sebelum Route::resource('aset', ...)
        // agar segmen "export" tidak ditangkap sebagai parameter {aset}.
        Route::get('aset/export/excel', 'BMN\AsetController@exportExcel')->name('aset.export.excel');
        // PENTING: export "terpilih" juga harus sebelum Route::resource('aset', ...)
        // agar segmen "export-selected-excel" tidak ditangkap sebagai {aset}.
        Route::post('aset/export-selected-excel', 'BMN\AsetController@exportSelectedExcel')
            ->name('aset.export.selected.excel');
        Route::post('aset/import', 'BMN\AsetController@import')->name('aset.import');
        Route::post('aset/bulk-delete', 'BMN\AsetController@bulkDelete')->name('aset.bulk-delete');
        Route::resource('aset', 'BMN\AsetController')->except(['show']);
        Route::resource('mutasi', 'BMN\MutasiController')->only(['index', 'create', 'store']);
        Route::get('laporan', 'BMN\LaporanController@index')->name('laporan.index');

        // ===== PEMINJAMAN ASET BMN (auth) =====
        Route::prefix('peminjaman-aset')->name('peminjaman-aset.')->group(function () {
            Route::get('/', 'BMN\PeminjamanAsetController@index')->name('index');
            Route::get('{id}/screening', 'BMN\BmnPeminjamanScreeningController@show')
                ->where('id', '[0-9]+')->name('screening');
            Route::post('{id}/screening', 'BMN\BmnPeminjamanScreeningController@store')
                ->where('id', '[0-9]+')->name('screening.store');
            Route::get('{id}', 'BMN\PeminjamanAsetController@show')
                ->where('id', '[0-9]+')->name('show');
            Route::get('{peminjaman}/documents/{document}/view', 'BMN\PeminjamanAsetController@viewDocument')
                ->where('peminjaman', '[0-9]+')->where('document', '[0-9]+')->name('documents.view');
            Route::get('{peminjaman}/documents/{document}/download', 'BMN\PeminjamanAsetController@downloadDocument')
                ->where('peminjaman', '[0-9]+')->where('document', '[0-9]+')->name('documents.download');
            Route::get('{peminjaman}/legacy-nota/view', 'BMN\PeminjamanAsetController@viewLegacyNotaDinas')
                ->where('peminjaman', '[0-9]+')->name('legacy-nota.view');
            Route::get('{peminjaman}/legacy-nota/download', 'BMN\PeminjamanAsetController@downloadLegacyNotaDinas')
                ->where('peminjaman', '[0-9]+')->name('legacy-nota.download');

            // ===== Workflow approval BARU: Pak Singgih -> Pak Hendri =====
            // Routes ini WAJIB sebelum resource-style routes lain agar pattern
            // {id}/approve-singgih dst tidak tertimpa wildcard.
            Route::post('{id}/approve-singgih', 'BMN\PeminjamanAsetController@approveBySinggih')
                ->where('id', '[0-9]+')->name('approve-singgih');
            Route::post('{id}/reject-singgih', 'BMN\PeminjamanAsetController@rejectBySinggih')
                ->where('id', '[0-9]+')->name('reject-singgih');
            Route::post('{id}/approve-hendri', 'BMN\PeminjamanAsetController@approveByHendri')
                ->where('id', '[0-9]+')->name('approve-hendri');
            Route::post('{id}/reject-hendri', 'BMN\PeminjamanAsetController@rejectByHendri')
                ->where('id', '[0-9]+')->name('reject-hendri');

            Route::post('{id}/approve', 'BMN\PeminjamanAsetController@approve')
                ->where('id', '[0-9]+')->name('approve');
            Route::post('{peminjaman}/approve-stage-1', 'BMN\BmnPeminjamanApprovalController@approveStage1')
                ->where('peminjaman', '[0-9]+')->name('approve-stage-1');
            Route::post('{peminjaman}/approve-stage-2', 'BMN\BmnPeminjamanApprovalController@approveStage2')
                ->where('peminjaman', '[0-9]+')->name('approve-stage-2');
            Route::post('{peminjaman}/final-report', 'BMN\BmnPeminjamanApprovalController@finalReport')
                ->where('peminjaman', '[0-9]+')->name('final-report');
            Route::post('{peminjaman}/reject-stage', 'BMN\BmnPeminjamanApprovalController@rejectStage')
                ->where('peminjaman', '[0-9]+')->name('reject-stage');
            Route::post('{peminjaman}/approve-final-hendri', 'BMN\BmnPeminjamanApprovalController@approveFinalHendri')
                ->where('peminjaman', '[0-9]+')->name('approve-final-hendri');
            Route::post('{peminjaman}/reject-final-hendri', 'BMN\BmnPeminjamanApprovalController@rejectFinalHendri')
                ->where('peminjaman', '[0-9]+')->name('reject-final-hendri');
            Route::post('{id}/mark-dipinjam', 'BMN\PeminjamanAsetController@markDipinjam')
                ->where('id', '[0-9]+')->name('mark-dipinjam');
        });

        // ===== PENGEMBALIAN ASET BMN (auth) =====
        Route::prefix('pengembalian-aset')->name('pengembalian-aset.')->group(function () {
            Route::get('/', 'BMN\PengembalianAsetController@index')->name('index');
            Route::get('{peminjaman_id}/create', 'BMN\PengembalianAsetController@create')
                ->where('peminjaman_id', '[0-9]+')->name('create');
            Route::post('{id}/verify', 'BMN\PengembalianAsetController@verify')
                ->where('id', '[0-9]+')->name('verify');
            Route::post('{id}/reject', 'BMN\PengembalianAsetController@reject')
                ->where('id', '[0-9]+')->name('reject');
        });

        Route::prefix('sewa')->name('sewa.')->group(function (): void {
            Route::get('/', 'BMN\BmnSewaController@index')->name('index');
            Route::get('{id}', 'BMN\BmnSewaController@show')->where('id', '[0-9]+')->name('show');
            Route::post('{id}/confirm-payment', 'BMN\BmnSewaController@confirmPayment')
                ->where('id', '[0-9]+')->name('confirm-payment');
            Route::post('{id}/reject-payment', 'BMN\BmnSewaController@rejectPayment')
                ->where('id', '[0-9]+')->name('reject-payment');
            Route::post('{id}/mark-coordinated', 'BMN\BmnSewaController@markCoordinated')
                ->where('id', '[0-9]+')->name('mark-coordinated');
        });

        Route::get('pemeliharaan-aset', 'BMN\BmnPemeliharaanController@index')->name('pemeliharaan-aset.index');
        Route::get('penghapusan-aset', 'BMN\BmnPenghapusanController@index')->name('penghapusan-aset.index');
    });
});

// Route::get('admin-page', function() {
//     return 'Halaman untuk Admin';
// })->middleware('role:admin')->name('admin.page');

// Route::get('user-page', function() {
//     return 'Halaman untuk User';
// })->middleware('role:customer')->name('user.page');

//ADMIN
Route::group(['prefix' => 'admin','middleware' => ['role:admin']], function () {
    Route::get('/users','AdminController@users');
    Route::get('/user/{id}','AdminController@user_detail');
    Route::post('/user_update','AdminController@user_update');
    Route::post('/user_reset','AdminController@user_reset');
    Route::get('/user_manage','AdminController@user_manage');
    Route::get('/user_edit/{id}','AdminController@user_edit');
    Route::post('/add_user','AdminController@add_user');
    Route::get('/delete_user/{id}','AdminController@delete_user');

    Route::get('/import_user','AdminController@import_user');
    Route::post('/import','AdminController@import');
    // Route::post('/import_mhs','AdminController@import_mhs');

    Route::get('/add_role_user/{model_id}/{role_id}','AdminController@add_role_user');
    Route::get('/delete_role_user/{model_id}/{role_id}','AdminController@delete_role_user');
    Route::get('/master_data','AdminController@master_data');
    
    Route::post('/add_ruangan','AdminController@add_ruangan');
    Route::post('/add_kelas','AdminController@add_kelas');
    Route::post('/add_tingkat','AdminController@add_tingkat');
    Route::post('/add_semester','AdminController@add_semester');

    Route::post('/edit_ruangan','AdminController@edit_ruangan');
    Route::post('/edit_kelas','AdminController@edit_kelas');
    Route::post('/edit_tingkat','AdminController@edit_tingkat');
    Route::post('/edit_semester','AdminController@edit_semester');
    
    Route::get('/delete_kelas/{id}','AdminController@delete_kelas');
    Route::get('/delete_ruangan/{id}','AdminController@delete_ruangan');
    Route::get('/delete_tingkat/{id}','AdminController@delete_tingkat');
    
    Route::get('/icon','AdminController@icon');
    Route::get('/icon2','AdminController@icon2');
    Route::post('/delete_icon','AdminController@delete_icon');
});

// View Composer: inject $fotos once for petugas_lab.request (fix N+1)
View::composer('petugas_lab.request', function ($view) {
    $fotos = DB::table('peminjaman_foto')->get()->groupBy('peminjaman_id');
    $view->with('fotos', $fotos);
});

//PETUGAS
Route::group(['prefix' => 'petugas','middleware' => ['role:plp']], function () {
    Route::get('/peminjaman','PetugasLabController@peminjaman');
    Route::get('/request','PetugasLabController@request');
    Route::get('/peminjaman-foto/{id}', function ($id) {
        $rows = DB::table('peminjaman_foto')
            ->where('peminjaman_id', $id)
            ->get(['foto_path']);
    
        $photos = $rows->map(function ($row) {
            return [
                'foto_path' => $row->foto_path,
                'foto_url' => asset($row->foto_path),
            ];
        })->values();
    
        return response()->json($photos);
    });
    Route::get('/request_detail/{id}','PetugasLabController@request_detail');
    Route::post('/request_submit','PetugasLabController@request_submit');

    Route::get('/pengembalian','PetugasLabController@pengembalian');
    Route::get('/pengembalian_detail/{id}','PetugasLabController@pengembalian_detail');
    Route::post('/pengembalian_submit','PetugasLabController@pengembalian_submit');
    Route::get('/belum_dikembalikan','PetugasLabController@belum_dikembalikan');
    
    Route::get('/pengembalian_detail2/{id}','PetugasLabController@pengembalian_detail2');
    Route::post('/pengembalian_submit2','PetugasLabController@pengembalian_submit2');


    // Route::post('/edit_request','PetugasLabController@edit_request');
    // Route::get('/ketersediaan','PetugasLabController@ketersediaan');
    // Route::get('/detail_pinjaman/{kode}','PetugasLabController@detail_pinjaman');
    // Route::get('/barang_masuk','PetugasLabController@barang_masuk');
    // Route::get('/barang_masuk/keperawatan','PetugasLabController@barang_masuk');

    // Route::post('/add_barang_masuk','PetugasLabController@add_barang_masuk');
    // Route::post('/edit_barang_masuk','PetugasLabController@edit_barang_masuk');
    // Route::post('/delete_barang_masuk','PetugasLabController@delete_barang_masuk');
    // Route::post('/update_barang_masuk','PetugasLabController@update_barang_masuk');
    // CRUD BARANG
    Route::get('/barang','PetugasLabController@barang');
    Route::post('/add_barang','PetugasLabController@add_barang');
    Route::post('/edit_barang','PetugasLabController@edit_barang');
    Route::post('/delete_barang','PetugasLabController@delete_barang');
    Route::post('/penambahan_stok_barang','PetugasLabController@penambahan_stok_barang');
    // CRUD BAHAN MEDIS
    Route::get('/barangbhp','PetugasLabController@barangbhp');
    Route::get('/edit_bhp/{id}','PetugasLabController@edit_bhp');
    Route::post('/add_bhp_bahan','PetugasLabController@add_bhp_bahan');
    Route::post('/edit_bhp_bahan','PetugasLabController@edit_bhp_bahan');
    Route::post('/delete_bhp_bahan','PetugasLabController@delete_bhp_bahan');
    // CRUD ALAT MEDIS
    Route::get('/barangmedis','PetugasLabController@barangmedis');
    Route::post('/add_bhp_alat','PetugasLabController@add_bhp_alat');
    Route::post('/edit_bhp_alat','PetugasLabController@edit_bhp_alat');
    Route::post('/delete_bhp_alat','PetugasLabController@delete_bhp_alat');
    
    Route::get('/ruang','PetugasLabController@ruang');
    Route::get('/tambah_jadwal','PetugasLabController@tambah_jadwal');
    Route::get('/detail_jadwal/{id}','PetugasLabController@detail_jadwal');
    Route::post('/add_jadwal','PetugasLabController@add_jadwal');
    Route::post('/edit_jadwal','PetugasLabController@edit_jadwal');
    Route::post('/delete_jadwal','PetugasLabController@delete_jadwal');

    Route::get('/request_jadwal','PetugasLabController@request_jadwal');
    Route::post('/submit_request_jadwal','PetugasLabController@submit_request_jadwal');

    Route::get('/laporan','PetugasLabController@lap_index');
    Route::get('/laporan/alat_lab','PetugasLabController@lap_alat_lab');
    Route::get('/laporan/alat_medis','PetugasLabController@lap_alat_medis');
    Route::get('/laporan/bahan_bhp', 'PetugasLabController@lap_bahan_bhp')->name('petugas.laporan.bahan_bhp');
    Route::get('/laporan/bahan_bhp_dimusnahkan', 'PetugasLabController@lap_bahan_bhp_dimusnahkan')->name('petugas.laporan.bahan_bhp_dimusnahkan');
    Route::post('/laporan/bahan_bhp/tindak_lanjut', 'PetugasLabController@lap_bahan_bhp_update_tindak_lanjut');
    Route::post('/laporan/bahan_bhp/tindak_lanjut_bulk', 'PetugasLabController@lap_bahan_bhp_bulk_tindak_lanjut');
    Route::get('/laporan/penambahan_alat_lab','PetugasLabController@lap_penambahan_alat_lab');
    Route::get('/laporan/penambahan_alat_medis','PetugasLabController@lap_penambahan_alat_medis');
    Route::get('/laporan/penambahan_bahan_bhp','PetugasLabController@lap_penambahan_bahan_bhp');
    Route::get('/laporan/pinjaman_alat_lab','PetugasLabController@lap_pinjaman_alat_lab');
    Route::get('/laporan/pinjaman_alat_medis','PetugasLabController@lap_pinjaman_alat_medis');
    Route::get('/laporan/pinjaman_bahan_bhp','PetugasLabController@lap_pinjaman_bahan_bhp');
    Route::get('/laporan/kunjungan_lab','PetugasLabController@lap_kunjungan_lab');
    Route::get('/laporan/penggunaan_lab','PetugasLabController@lap_penggunaan_lab');

});

//USER
Route::prefix('user')->group(function(){
    Route::get('/daftar_barang', 'UserController@daftar_barang');
    Route::get('/daftar_barangbhp', 'UserController@daftar_barangbhp');
    Route::get('/daftar_barangmedis', 'UserController@daftar_barangmedis');
    Route::post('/add_request','UserController@add_request');
    // Route::get('/history', 'UserController@history');
    Route::get('/peminjaman', 'UserController@peminjaman');
    Route::get('/peminjaman_detail/{id}', 'UserController@peminjaman_detail');
    Route::get('/req_peminjaman', 'UserController@req_peminjaman');
    Route::post('/add_peminjaman', 'UserController@add_peminjaman');

    Route::get('/kunjungan', 'UserController@kunjungan');
    Route::post('/add_kunjungan', 'UserController@add_kunjungan');

    Route::get('/jadwal_praktikum', 'UserController@jadwal_praktikum');
    Route::get('/req_jadwal', 'UserController@req_jadwal');
    Route::post('/add_jadwal', 'UserController@add_jadwal');


});

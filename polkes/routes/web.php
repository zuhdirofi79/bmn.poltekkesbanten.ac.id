<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return redirect(url('login'));
});

Auth::routes();
Route::get('/changePassword','Auth\ChangePasswordController@showChangePasswordForm');
Route::post('/changePassword','Auth\ChangePasswordController@changePassword')->name('changePassword');
Route::get('/profile', 'HomeController@profile')->name('profile');
Route::post('/update_profile', 'HomeController@update_profile');
Route::post('/upload_foto', 'HomeController@upload_foto');


Route::get('/home', 'HomeController@profile')->name('home');
// Route::get('/home', 'HomeController@profile')->name('home');
// Route::get('/lap_pdf', 'HomeController@lap_pdf');
// Route::get('/blank', 'HomeController@blank');

Route::group(['prefix' => 'bmn', 'middleware' => ['auth']], function () {
    Route::name('bmn.')->group(function () {
        Route::get('aset/template', 'BMN\AsetController@downloadTemplate')->name('aset.template');
        Route::post('aset/import', 'BMN\AsetController@import')->name('aset.import');
        Route::post('aset/bulk-delete', 'BMN\AsetController@bulkDelete')->name('aset.bulk-delete');
        Route::resource('aset', 'BMN\AsetController')->except(['show']);
        Route::resource('mutasi', 'BMN\MutasiController')->only(['index', 'create', 'store']);
        Route::get('laporan', 'BMN\LaporanController@index')->name('laporan.index');
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

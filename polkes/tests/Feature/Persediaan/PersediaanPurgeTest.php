<?php

declare(strict_types=1);

namespace Tests\Feature\Persediaan;

use App\Http\Controllers\PersediaanAdmin\MasterBarangController;
use App\Models\Persediaan\PersediaanAdmin;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanBarangArsip;
use App\Models\Persediaan\PersediaanPurgeBatch;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\PersediaanValuationService;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\UnitKerja;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PersediaanPurgeTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $adminSession = [];

    public function createApplication()
    {
        $app = parent::createApplication();
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        $this->createSchema();
        require_once base_path('database/migrations/2026_10_13_000001_create_persediaan_barang_arsip_and_purge.php');
        (new \CreatePersediaanBarangArsipAndPurge())->up();
        View::share('errors', new ViewErrorBag());
        $this->loginAdmin();
    }

    public function test_sampah_tanpa_histori_terhapus_fisik(): void
    {
        $barang = $this->trash($this->barang('CLEAN', 'Tanpa Histori'));

        $response = app(MasterBarangController::class)->purgeStart($this->request('POST', '/purge/start', [
            'mode' => 'single',
            'ids'  => [$barang->id],
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $batch = $response->getData(true)['batch_id'];
        $step = app(MasterBarangController::class)->purgeStep($this->request('POST', '/purge/step'), $batch)->getData(true);

        $this->assertSame('done', $step['status']);
        $this->assertSame(1, $step['succeeded']);
        $this->assertNull(PersediaanBarang::withTrashed()->find($barang->id));
        $this->assertSame('Tanpa Histori', PersediaanBarangArsip::query()->where('original_barang_id', $barang->id)->value('nama_barang'));
    }

    public function test_sampah_berhistori_diarsipkan_dan_transaksi_tetap(): void
    {
        $barang = $this->trash($this->barang('HIS', 'Lama'));
        DB::table('persediaan_pembelian_items')->insert([
            'pembelian_id' => 1,
            'barang_id'    => $barang->id,
            'kode_barang'  => 'HIS',
            'nama_barang'  => 'Lama',
            'satuan'       => 'Buah',
            'harga_satuan' => 12500,
            'jumlah'       => 4,
            'subtotal'     => 50000,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'tipe'       => PersediaanStockMovement::TIPE_PEMBELIAN,
            'qty_masuk'  => 4,
            'qty_keluar' => 0,
            'keterangan' => 'masuk uji',
        ]);

        $this->finish($this->start('single', [$barang->id]));

        $this->assertNull(PersediaanBarang::withTrashed()->find($barang->id));
        $item = DB::table('persediaan_pembelian_items')->where('barang_id', $barang->id)->first();
        $this->assertSame('Lama', $item->nama_barang);
        $this->assertSame(4, (int) $item->jumlah);
        $this->assertEquals(12500, (float) $item->harga_satuan);
        $movement = PersediaanStockMovement::query()->where('barang_id', $barang->id)->first();
        $this->assertSame(4, (int) $movement->qty_masuk);
        $this->assertSame('Lama', $movement->nama_barang);
        $this->assertSame('HIS', PersediaanBarangArsip::query()->where('original_barang_id', $barang->id)->value('kode_barang'));
    }

    public function test_barang_aktif_ditolak(): void
    {
        $aktif = $this->barang('AKTIF', 'Masih Aktif');
        $data = $this->finish($this->start('single', [$aktif->id]));

        $this->assertSame(0, $data['succeeded']);
        $this->assertSame(1, $data['failed']);
        $this->assertNotNull(PersediaanBarang::query()->find($aktif->id));
        $this->assertSame(0, PersediaanBarangArsip::query()->count());
    }

    public function test_kode_sama_tidak_menghapus_master_baru(): void
    {
        $lama = $this->trash($this->barang('SAMA', 'Master Lama', 3));
        $baru = $this->barang('SAMA', 'Master Baru', 9);
        PersediaanStockMovement::create([
            'barang_id'  => $baru->id,
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'tipe'       => PersediaanStockMovement::TIPE_STOK_AWAL,
            'qty_masuk'  => 9,
            'qty_keluar' => 0,
        ]);

        $this->finish($this->start('all', [], 'HAPUS PERMANEN'));

        $this->assertNull(PersediaanBarang::withTrashed()->find($lama->id));
        $baru->refresh();
        $this->assertSame('Master Baru', $baru->nama_barang);
        $this->assertSame(9, (int) $baru->stok_awal);
        $this->assertSame(9, (int) PersediaanStockMovement::query()->where('barang_id', $baru->id)->sum('qty_masuk'));
        $this->assertSame(9, (int) app(PersediaanValuationService::class)->reconcile(['active_only' => true])['summary']['total_stok_saat_ini']);
    }

    public function test_sepuluh_barang_terpilih_terhapus(): void
    {
        $ids = [];
        for ($i = 1; $i <= 10; $i++) {
            $ids[] = $this->trash($this->barang('B' . $i, 'Pilihan ' . $i))->id;
        }
        $tetap = $this->trash($this->barang('TETAP', 'Tidak Dipilih'));

        $data = $this->finish($this->start('selected', $ids));

        $this->assertSame(10, $data['succeeded']);
        $this->assertSame(0, PersediaanBarang::withTrashed()->whereIn('id', $ids)->count());
        $this->assertNotNull(PersediaanBarang::withTrashed()->find($tetap->id));
    }

    public function test_hapus_semua_sampah_berjalan_bertahap(): void
    {
        $aktif = $this->barang('BARU', 'Master Aktif', 5);
        for ($i = 1; $i <= 45; $i++) {
            $this->trash($this->barang('OLD' . $i, 'Lama ' . $i));
        }

        $started = $this->start('all', [], 'HAPUS PERMANEN');
        $first = app(MasterBarangController::class)->purgeStep($this->request('POST', '/step'), $started['batch_id'])->getData(true);
        $this->assertSame('running', $first['status']);
        $this->assertSame(40, $first['succeeded']);

        $done = $this->finish($started);
        $this->assertSame(45, $done['succeeded']);
        $this->assertSame(45, PersediaanBarangArsip::query()->count());
        $this->assertSame(0, PersediaanBarang::onlyTrashed()->count());
        $this->assertNotNull(PersediaanBarang::query()->find($aktif->id));
    }

    public function test_dependensi_yang_belum_didukung_tidak_merusak_record_lain(): void
    {
        $aman = $this->trash($this->barang('AMAN', 'Aman'));
        $tertahan = $this->trash($this->barang('TAHAN', 'Tertahan'));
        DB::table('persediaan_widget_refs')->insert(['barang_id' => $tertahan->id]);

        $data = $this->finish($this->start('selected', [$aman->id, $tertahan->id]));

        $this->assertSame(1, $data['succeeded']);
        $this->assertSame(1, $data['failed']);
        $this->assertNull(PersediaanBarang::withTrashed()->find($aman->id));
        $this->assertNotNull(PersediaanBarang::withTrashed()->find($tertahan->id));
        $this->assertSame(1, DB::table('persediaan_widget_refs')->count());
    }

    public function test_user_tanpa_sesi_ditolak_dan_konfirmasi_semua_wajib(): void
    {
        $request = Request::create('/purge/start', 'POST', ['mode' => 'all', 'confirm' => 'HAPUS PERMANEN']);
        $store = app('session')->driver();
        $store->start();
        $request->setLaravelSession($store);

        $denied = app(MasterBarangController::class)->purgeStart($request);
        $this->assertSame(403, $denied->getStatusCode());

        $missing = app(MasterBarangController::class)->purgeStart($this->request('POST', '/purge/start', ['mode' => 'all']));
        $this->assertSame(422, $missing->getStatusCode());
    }

    public function test_batch_kedua_ditolak_selama_proses_berjalan_lalu_bisa_dilanjutkan(): void
    {
        $barang = $this->trash($this->barang('LOCK', 'Terkunci'));
        $started = $this->start('single', [$barang->id]);
        $batch = PersediaanPurgeBatch::query()->where('public_id', $started['batch_id'])->first();
        $batch->step_token = 'sedang-jalan';
        $batch->locked_at = now();
        $batch->save();

        $busyStep = app(MasterBarangController::class)->purgeStep($this->request('POST', '/step'), $started['batch_id'])->getData(true);
        $this->assertTrue($busyStep['busy']);
        $this->assertNotNull(PersediaanBarang::withTrashed()->find($barang->id));

        $again = app(MasterBarangController::class)->purgeStart($this->request('POST', '/purge/start', [
            'mode'    => 'all',
            'confirm' => 'HAPUS PERMANEN',
        ]));
        $this->assertSame(409, $again->getStatusCode());

        $batch->locked_at = now()->subMinutes(3);
        $batch->save();
        $done = app(MasterBarangController::class)->purgeStep($this->request('POST', '/step'), $started['batch_id'])->getData(true);
        $this->assertSame(1, $done['succeeded']);
        $againDone = app(MasterBarangController::class)->purgeStep($this->request('POST', '/step'), $started['batch_id'])->getData(true);
        $this->assertSame(1, $againDone['succeeded']);
        $this->assertSame(1, PersediaanBarangArsip::query()->count());
    }

    public function test_restore_tetap_berfungsi_sebelum_purge(): void
    {
        $barang = $this->trash($this->barang('REST', 'Pulih'));
        $request = $this->request('POST', '/restore');
        app(MasterBarangController::class)->restore($request, $barang->id);

        $this->assertNull($barang->fresh()->deleted_at);
        $this->assertSame('Barang berhasil dipulihkan.', $request->session()->get('success'));
    }

    public function test_halaman_sampah_menampilkan_konfirmasi_dan_empty_state(): void
    {
        $empty = app(MasterBarangController::class)->trash($this->request('GET', '/persediaan/master-barang/sampah'))->render();
        $this->assertStringContainsString('Hapus Permanen Semua Sampah', $empty);
        $this->assertStringContainsString('HAPUS PERMANEN', $empty);
        $this->assertStringContainsString('Sampah Kosong', $empty);
        $this->assertStringContainsString('Ya, Hapus Permanen Seluruh Sampah', $empty);

        $this->trash($this->barang('UI', 'Tampil'));
        $html = app(MasterBarangController::class)->trash($this->request('GET', '/persediaan/master-barang/sampah'))->render();
        $this->assertStringContainsString('js-purge-one', $html);
        $this->assertStringContainsString('Hapus Permanen Terpilih', $html);
        $this->assertStringNotContainsString('Tidak ada barang yang dihapus:', $html);
    }

    private function barang(string $kode, string $nama, int $stok = 0): PersediaanBarang
    {
        return PersediaanBarang::create([
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'satuan'       => 'Buah',
            'unit_kerja'   => UnitKerja::DIREKTORAT,
            'stok_awal'    => $stok,
            'stok_minimum' => 0,
            'harga_satuan' => 1000,
            'is_active'    => true,
        ]);
    }

    private function trash(PersediaanBarang $barang): PersediaanBarang
    {
        $barang->delete();

        return $barang->fresh();
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function start(string $mode, array $ids = [], ?string $confirm = null): array
    {
        $payload = ['mode' => $mode, 'ids' => $ids];
        if ($confirm !== null) {
            $payload['confirm'] = $confirm;
        }
        $response = app(MasterBarangController::class)->purgeStart($this->request('POST', '/purge/start', $payload));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $response->getData(true);
    }

    /**
     * @param  array<string, mixed>  $started
     * @return array<string, mixed>
     */
    private function finish(array $started): array
    {
        $state = $started;
        for ($i = 0; $i < 5 && ($state['status'] ?? '') === 'running'; $i++) {
            $state = app(MasterBarangController::class)
                ->purgeStep($this->request('POST', '/step'), $started['batch_id'])
                ->getData(true);
        }

        return $state;
    }

    private function loginAdmin(): void
    {
        $user = User::create([
            'name'     => 'Admin Purge',
            'email'    => 'purge@example.test',
            'password' => Hash::make('secret'),
        ]);
        config(['persediaan.superadmin_user_ids' => [(int) $user->id]]);
        PersediaanAdmin::create([
            'user_id'   => $user->id,
            'role'      => PersediaanAdmin::ROLE_SUPERADMIN,
            'is_active' => true,
        ]);
        $this->adminSession = [
            PersediaanAdminSession::KEY_USER_ID => (int) $user->id,
            PersediaanAdminSession::KEY_ROLE    => PersediaanAdmin::ROLE_SUPERADMIN,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $uri, array $payload = []): Request
    {
        $request = Request::create($uri, $method, $payload);
        $store = app('session')->driver();
        if (! $store->isStarted()) {
            $store->start();
        }
        foreach ($this->adminSession as $key => $value) {
            $store->put($key, $value);
        }
        $request->setLaravelSession($store);
        $this->app->instance('request', $request);

        return $request;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('persediaan_admins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('role');
            $table->string('satker')->nullable();
            $table->unsignedTinyInteger('is_active')->default(1);
            $table->timestamps();
        });
        Schema::create('persediaan_barang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
            $table->string('unit_kerja');
            $table->integer('stok_awal')->default(0);
            $table->integer('stok_minimum')->default(0);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('barcode_path')->nullable();
            $table->string('barcode_payload')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('persediaan_rak', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_rak');
            $table->string('nama_rak')->nullable();
            $table->string('unit_kerja');
            $table->timestamps();
        });
        Schema::create('persediaan_rak_barang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rak_id');
            $table->unsignedBigInteger('barang_id');
            $table->timestamps();
        });
        Schema::create('persediaan_pembelian_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pembelian_id')->nullable();
            $table->unsignedBigInteger('barang_id')->nullable();
            $table->string('kode_barang')->nullable();
            $table->string('nama_barang')->nullable();
            $table->string('satuan')->nullable();
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->integer('jumlah')->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('persediaan_stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('barang_id');
            $table->string('unit_kerja');
            $table->string('tipe');
            $table->integer('qty_masuk')->default(0);
            $table->integer('qty_keluar')->default(0);
            $table->string('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('persediaan_widget_refs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('barang_id');
        });
    }
}

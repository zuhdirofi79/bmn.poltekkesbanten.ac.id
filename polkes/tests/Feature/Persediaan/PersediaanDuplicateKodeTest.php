<?php

declare(strict_types=1);

namespace Tests\Feature\Persediaan;

use App\Http\Controllers\PersediaanAdmin\MasterBarangController;
use App\Http\Controllers\PersediaanAdmin\PembelianController;
use App\Imports\PersediaanMasterBarangImport;
use App\Models\Persediaan\PersediaanAdmin;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\PersediaanCodeService;
use App\Services\Persediaan\PersediaanDeletionService;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\UnitKerja;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PersediaanDuplicateKodeTest extends TestCase
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
        require_once base_path('database/migrations/2026_10_09_000001_drop_unique_kode_barang_on_persediaan_barang.php');
        (new \DropUniqueKodeBarangOnPersediaanBarang())->up();
        View::share('errors', new ViewErrorBag());
        $this->loginAdmin();
    }

    public function test_unique_kode_barang_dihapus_dan_index_biasa_tetap_ada(): void
    {
        $names = array_map(static function ($row): string {
            return (string) ($row->name ?? '');
        }, DB::select("PRAGMA index_list('persediaan_barang')"));

        $this->assertNotContains('persediaan_barang_kode_barang_unit_kerja_unique', $names);
        $this->assertContains('persediaan_barang_kode_barang_index', $names);

        PersediaanBarang::create($this->attributes('ABC001', 'Satu'));
        PersediaanBarang::create($this->attributes('ABC001', 'Dua'));
        $this->assertSame(2, PersediaanBarang::query()->where('kode_barang', 'ABC001')->count());

        (new \DropUniqueKodeBarangOnPersediaanBarang())->down();
        PersediaanBarang::create($this->attributes('ABC001', 'Tiga'));
        $this->assertSame(3, PersediaanBarang::query()->where('kode_barang', 'ABC001')->count());
    }

    public function test_kode_sama_boleh_aktif_dan_sampah_lalu_dipulihkan(): void
    {
        $a = $this->store('ABC001', 'Barang A');
        $b = $this->store('ABC001', 'Barang B');
        $this->assertNotSame($a->id, $b->id);

        app(MasterBarangController::class)->destroy($this->request('DELETE', '/persediaan/master-barang/' . $a->id), $a->id);
        $a->refresh();
        $this->assertNotNull($a->deleted_at);
        $this->assertNotNull(PersediaanBarang::query()->find($b->id));

        $c = $this->store('ABC001', 'Barang C');
        $restore = $this->request('POST', '/persediaan/master-barang/' . $a->id . '/restore');
        app(MasterBarangController::class)->restore($restore, $a->id);
        $this->assertSame('Barang berhasil dipulihkan.', $restore->session()->get('success'));
        $a->refresh();
        $this->assertNull($a->deleted_at);

        $aktif = PersediaanBarang::query()->where('kode_barang', 'ABC001')->orderBy('id')->get();
        $this->assertSame(['Barang A', 'Barang B', 'Barang C'], $aktif->pluck('nama_barang')->all());

        $html = app(MasterBarangController::class)
            ->index($this->request('GET', '/persediaan/master-barang?q=ABC001&per_page=20'))
            ->render();
        $this->assertStringContainsString('Barang A', $html);
        $this->assertStringContainsString('Barang B', $html);
        $this->assertStringContainsString('Barang C', $html);
        $this->assertStringNotContainsString('jangan buat duplikat', $html);

        $edit = $this->request('PUT', '/persediaan/master-barang/' . $b->id, $this->attributes('ABC001', 'Barang B diubah', [
            'is_active' => '1',
            'stok_awal' => 0,
        ]));
        app(MasterBarangController::class)->update($edit, $b->id);
        $this->assertSame('Barang B diubah', $b->fresh()->nama_barang);
        $this->assertSame('Barang A', $a->fresh()->nama_barang);
        $this->assertSame('Barang C', $c->fresh()->nama_barang);

        app(MasterBarangController::class)->destroy($this->request('DELETE', '/persediaan/master-barang/' . $c->id), $c->id);
        $this->assertNotNull($c->fresh()->deleted_at);
        $this->assertNull($a->fresh()->deleted_at);
        $this->assertNull($b->fresh()->deleted_at);

        $trash = app(MasterBarangController::class)
            ->trash($this->request('GET', '/persediaan/master-barang/sampah?q=ABC001'))
            ->render();
        $this->assertStringContainsString('Barang C', $trash);
        $this->assertStringContainsString('Sampah', $trash);
        $this->assertStringNotContainsString('Barang A', $trash);
        $this->assertStringNotContainsString('Barang B diubah', $trash);

        $force = $this->request('DELETE', '/persediaan/master-barang/' . $c->id . '/force');
        app(MasterBarangController::class)->forceDestroy($force, $c->id);
        $this->assertSame('Barang berhasil dihapus permanen.', $force->session()->get('success'));
        $this->assertNull(PersediaanBarang::withTrashed()->find($c->id));
        $this->assertNotNull(PersediaanBarang::query()->find($a->id));
    }

    public function test_hapus_permanen_barang_berhistori_ditolak_dan_bulk_tidak_sebagian(): void
    {
        $safe = $this->store('HIS-1', 'Aman');
        $histori = $this->store('HIS-1', 'Berhistori');
        PersediaanStockMovement::create([
            'barang_id'  => $histori->id,
            'unit_kerja' => $histori->unit_kerja,
            'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
            'qty_masuk'  => 0,
            'qty_keluar' => 2,
            'keterangan' => 'keluar uji',
        ]);

        app(MasterBarangController::class)->destroy($this->request('DELETE', '/x'), $safe->id);
        app(MasterBarangController::class)->destroy($this->request('DELETE', '/x'), $histori->id);

        $single = $this->request('DELETE', '/persediaan/master-barang/' . $histori->id . '/force');
        app(MasterBarangController::class)->forceDestroy($single, $histori->id);
        $this->assertSame(PersediaanDeletionService::BARANG_BLOCKED, $single->session()->get('error'));
        $this->assertNotNull(PersediaanBarang::withTrashed()->find($histori->id));
        $this->assertSame(1, PersediaanStockMovement::query()->where('barang_id', $histori->id)->count());

        $bulk = $this->request('POST', '/persediaan/master-barang/trash/bulk-force-delete', [
            'ids' => [$safe->id, $histori->id],
        ]);
        app(MasterBarangController::class)->trashBulkForceDestroy($bulk);
        $this->assertStringContainsString('Tidak ada barang yang dihapus', (string) $bulk->session()->get('error'));
        $this->assertNotNull(PersediaanBarang::withTrashed()->find($safe->id));
        $this->assertNotNull(PersediaanBarang::withTrashed()->find($histori->id));
    }

    public function test_barcode_dan_import_tidak_menggabungkan_kode_yang_sama(): void
    {
        $a = PersediaanBarang::create($this->attributes('000012', 'Pulpen'));
        $b = PersediaanBarang::create($this->attributes('000012', 'Pensil'));
        $codes = app(PersediaanCodeService::class);

        $this->assertSame((int) $a->id, (int) $codes->resolve($codes->identityPayload($a))->id);
        $this->assertSame((int) $b->id, (int) $codes->resolve($codes->identityPayload($b))->id);
        $this->assertNull($codes->resolve('DIR|000012'));
        $this->assertStringContainsString((string) $a->id, $codes->filename($a));
        $this->assertStringContainsString((string) $b->id, $codes->filename($b));

        $path = $this->writeImportFile([
            ['kode_barang', 'nama_barang', 'unit_kerja', 'satuan', 'stok_awal', 'harga_satuan', 'status'],
            ['ABC001', 'Dari Excel 1', UnitKerja::DIREKTORAT, 'Buah', 0, 1500, 'aktif'],
            ['ABC001', 'Dari Excel 2', UnitKerja::DIREKTORAT, 'Buah', 0, 2500, 'aktif'],
        ]);

        try {
            $result = (new PersediaanMasterBarangImport(null))->import(
                new UploadedFile($path, 'master.xlsx', null, null, true),
            );
        } finally {
            @unlink($path);
        }

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(2, PersediaanBarang::query()->where('kode_barang', 'ABC001')->count());
    }

    public function test_import_pembelian_tidak_mengaitkan_kode_yang_ambigu(): void
    {
        $satu = PersediaanBarang::create($this->attributes('UNIK', 'Unik'));
        PersediaanBarang::create($this->attributes('000012', 'Pulpen'));
        PersediaanBarang::create($this->attributes('000012', 'Pensil'));

        $method = new \ReflectionMethod(PembelianController::class, 'masterMap');
        $method->setAccessible(true);
        $map = $method->invoke(app(PembelianController::class));

        $this->assertSame($satu->id, $map[UnitKerja::DIREKTORAT . '|UNIK']['id']);
        $this->assertArrayNotHasKey(UnitKerja::DIREKTORAT . '|000012', $map);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function attributes(string $kode, string $nama, array $extra = []): array
    {
        return $extra + [
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'satuan'       => 'Buah',
            'unit_kerja'   => UnitKerja::DIREKTORAT,
            'stok_awal'    => 0,
            'stok_minimum' => 0,
            'harga_satuan' => 1000,
        ];
    }

    private function store(string $kode, string $nama): PersediaanBarang
    {
        $request = $this->request('POST', '/persediaan/master-barang', $this->attributes($kode, $nama));
        $response = app(MasterBarangController::class)->store($request);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('Barang berhasil ditambahkan.', $request->session()->get('success'));
        $this->assertNull($request->session()->get('error'));

        $barang = PersediaanBarang::query()->where('nama_barang', $nama)->first();
        $this->assertNotNull($barang);

        return $barang;
    }

    private function loginAdmin(): void
    {
        $user = User::create([
            'name'     => 'Admin Uji',
            'email'    => 'admin-dup@example.test',
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

    /**
     * @param  list<list<int|string>>  $rows
     */
    private function writeImportFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = storage_path('app/duplicate-kode-import-' . uniqid('', true) . '.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
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
            $table->index('kode_barang');
            $table->unique(['kode_barang', 'unit_kerja']);
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
        Schema::create('persediaan_stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('barang_id');
            $table->string('unit_kerja');
            $table->string('tipe');
            $table->string('ref_table')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->integer('qty_masuk')->default(0);
            $table->integer('qty_keluar')->default(0);
            $table->string('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }
}

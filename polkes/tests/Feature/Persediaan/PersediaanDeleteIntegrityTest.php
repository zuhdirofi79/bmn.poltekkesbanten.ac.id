<?php

declare(strict_types=1);

namespace Tests\Feature\Persediaan;

use App\Http\Controllers\PersediaanAdmin\DashboardController;
use App\Http\Controllers\PersediaanAdmin\LaporanController;
use App\Http\Controllers\PersediaanAdmin\MasterBarangController;
use App\Http\Controllers\PersediaanAdmin\PermintaanController;
use App\Models\Persediaan\PersediaanAdmin;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanPermintaanItem;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\PersediaanDeletionService;
use App\Services\Persediaan\PersediaanValuationService;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\PublicUploadPath;
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

class PersediaanDeleteIntegrityTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $adminSession = [];

    private ?string $barcodeFile = null;

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
        View::share('errors', new ViewErrorBag());
        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        if ($this->barcodeFile !== null && is_file($this->barcodeFile)) {
            @unlink($this->barcodeFile);
        }
        parent::tearDown();
    }

    public function test_master_barang_soft_delete_restore_dan_bukan_nonaktif(): void
    {
        $barang = $this->barang('SOFT-001', 4);
        $request = $this->request('DELETE', '/persediaan/master-barang/' . $barang->id);
        $request->headers->set('referer', 'http://localhost/persediaan/master-barang?q=SOFT&per_page=20');

        $response = app(MasterBarangController::class)->destroy($request, $barang->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('q=SOFT', $response->headers->get('Location') ?? '');
        $this->assertStringContainsString('per_page=20', $response->headers->get('Location') ?? '');
        $this->assertSame('Barang berhasil di-soft delete.', $request->session()->get('success'));

        $barang->refresh();
        $this->assertNotNull($barang->deleted_at);
        $this->assertTrue($barang->is_active);
        $this->assertNull(PersediaanBarang::query()->find($barang->id));

        $aktif = app(MasterBarangController::class)->index($this->request('GET', '/persediaan/master-barang'))->render();
        $this->assertStringNotContainsString('SOFT-001', $aktif);

        $sampah = app(MasterBarangController::class)
            ->index($this->request('GET', '/persediaan/master-barang?lifecycle=sampah'))
            ->render();
        $this->assertStringContainsString('SOFT-001', $sampah);
        $this->assertStringContainsString('Restore', $sampah);

        $restoreRequest = $this->request('POST', '/persediaan/master-barang/' . $barang->id . '/restore');
        app(MasterBarangController::class)->restore($restoreRequest, $barang->id);
        $this->assertSame('Barang berhasil dipulihkan.', $restoreRequest->session()->get('success'));

        $restored = PersediaanBarang::query()->find($barang->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertTrue($restored->is_active);
    }

    public function test_nonaktifkan_tidak_mengisi_deleted_at(): void
    {
        $barang = $this->barang('OFF-001', 2);
        $request = $this->request('POST', '/persediaan/master-barang/' . $barang->id . '/nonaktifkan');

        app(MasterBarangController::class)->deactivate($request, $barang->id);

        $this->assertSame('Barang dinonaktifkan.', $request->session()->get('success'));
        $barang->refresh();
        $this->assertFalse($barang->is_active);
        $this->assertNull($barang->deleted_at);
        $this->assertNotNull(PersediaanBarang::query()->find($barang->id));
    }

    public function test_barang_tanpa_histori_bisa_dihapus_permanen_beserta_file_barcode(): void
    {
        $relative = 'uploads/persediaan/barcode/barang/delete-test-exclusive.png';
        $this->barcodeFile = PublicUploadPath::absolute($relative);
        PublicUploadPath::ensureDir(dirname($this->barcodeFile));
        file_put_contents($this->barcodeFile, 'barcode');
        $barang = $this->barang('CLEAN-001', 1, $relative);
        $request = $this->request('DELETE', '/persediaan/master-barang/' . $barang->id . '/force');

        app(MasterBarangController::class)->forceDestroy($request, $barang->id);

        $this->assertSame('Barang berhasil dihapus permanen.', $request->session()->get('success'));
        $this->assertNull(PersediaanBarang::withTrashed()->find($barang->id));
        $this->assertFalse(is_file($this->barcodeFile));
        $this->barcodeFile = null;
    }

    public function test_barang_berhistori_tidak_bisa_dihapus_permanen(): void
    {
        $barang = $this->barang('HIST-001', 9);
        $permintaan = $this->permintaan('PRM-DIR-2026-010', PersediaanPermintaan::STATUS_DIAJUKAN);
        $this->item($permintaan, $barang, 1);
        $request = $this->request('DELETE', '/persediaan/master-barang/' . $barang->id . '/force');

        app(MasterBarangController::class)->forceDestroy($request, $barang->id);

        $this->assertSame(PersediaanDeletionService::BARANG_BLOCKED, $request->session()->get('error'));
        $this->assertNotNull(PersediaanBarang::query()->find($barang->id));
        $this->assertSame(1, PersediaanPermintaanItem::query()->count());
    }

    public function test_bulk_permanent_barang_melewati_yang_punya_histori(): void
    {
        $clean = [];
        foreach (['C1', 'C2', 'C3'] as $kode) {
            $clean[] = $this->barang($kode, 1);
        }
        $byItem = $this->barang('BLOCK-ITEM', 1);
        $byBeli = $this->barang('BLOCK-BELI', 1);
        $permintaan = $this->permintaan('PRM-DIR-2026-011', PersediaanPermintaan::STATUS_DITOLAK);
        $this->item($permintaan, $byItem, 2);
        DB::table('persediaan_pembelian_items')->insert([
            'pembelian_id' => 1,
            'barang_id'    => $byBeli->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $ids = array_merge(
            array_map(static fn (PersediaanBarang $row): int => (int) $row->id, $clean),
            [(int) $byItem->id, (int) $byBeli->id],
        );
        $request = $this->request('POST', '/persediaan/master-barang/bulk-force-delete', ['ids' => $ids]);

        app(MasterBarangController::class)->bulkForceDestroy($request);

        $this->assertSame(
            '3 barang berhasil dihapus permanen. 2 barang tidak dapat dihapus karena memiliki histori transaksi.',
            $request->session()->get('success'),
        );
        foreach ($clean as $row) {
            $this->assertNull(PersediaanBarang::withTrashed()->find($row->id));
        }
        $this->assertNotNull(PersediaanBarang::query()->find($byItem->id));
        $this->assertNotNull(PersediaanBarang::query()->find($byBeli->id));
    }

    public function test_halaman_master_menawarkan_soft_dan_permanen_bukan_confirm_nonaktif(): void
    {
        $this->barang('UI-001', 1);
        $html = app(MasterBarangController::class)->index($this->request('GET', '/persediaan/master-barang'))->render();

        $this->assertStringContainsString('Soft Delete Barang', $html);
        $this->assertStringContainsString('Hapus Permanen Barang', $html);
        $this->assertStringContainsString('Nonaktifkan', $html);
        $this->assertStringContainsString('Data Aktif', $html);
        $this->assertStringContainsString('Pilih semua barang di halaman ini', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringNotContainsString("confirm('Nonaktifkan barang ini?')", $html);

        $route = app('router')->getRoutes()->getByName('persediaan.admin.master-barang.force-destroy');
        $this->assertNotNull($route);
        $this->assertContains('DELETE', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    public function test_permintaan_pending_soft_delete_restore_dan_permanen_membersihkan_detail(): void
    {
        $barang = $this->barang('REQ-001', 10);
        $pending = $this->permintaan('PRM-DIR-2026-001', PersediaanPermintaan::STATUS_DIAJUKAN);
        $this->item($pending, $barang, 2);
        $controller = app(PermintaanController::class);

        $soft = $this->request('DELETE', '/persediaan/permintaan/' . $pending->id);
        $controller->destroy($soft, $pending->id);
        $this->assertSame('Permintaan berhasil di-soft delete.', $soft->session()->get('success'));

        $pending->refresh();
        $this->assertNotNull($pending->deleted_at);
        $this->assertSame(PersediaanPermintaan::STATUS_DIAJUKAN, $pending->status);
        $this->assertSame(1, PersediaanPermintaanItem::query()->where('permintaan_id', $pending->id)->count());
        $this->assertNull(PersediaanPermintaan::query()->find($pending->id));

        $restore = $this->request('POST', '/persediaan/permintaan/' . $pending->id . '/restore');
        $controller->restore($restore, $pending->id);
        $this->assertSame('Permintaan berhasil dipulihkan.', $restore->session()->get('success'));
        $this->assertNull($pending->fresh()->deleted_at);

        $force = $this->request('DELETE', '/persediaan/permintaan/' . $pending->id . '/force');
        $controller->forceDestroy($force, $pending->id);
        $this->assertSame('Permintaan berhasil dihapus permanen.', $force->session()->get('success'));
        $this->assertNull(PersediaanPermintaan::withTrashed()->find($pending->id));
        $this->assertSame(0, PersediaanPermintaanItem::query()->where('permintaan_id', $pending->id)->count());
        $this->assertNotNull(PersediaanBarang::query()->find($barang->id));
    }

    public function test_permintaan_ditolak_tanpa_movement_boleh_dihapus_permanen(): void
    {
        $barang = $this->barang('REJ-001', 6);
        $ditolak = $this->permintaan('PRM-DIR-2026-002', PersediaanPermintaan::STATUS_DITOLAK);
        $this->item($ditolak, $barang, 1);
        $request = $this->request('DELETE', '/persediaan/permintaan/' . $ditolak->id . '/force');

        app(PermintaanController::class)->forceDestroy($request, $ditolak->id);

        $this->assertSame('Permintaan berhasil dihapus permanen.', $request->session()->get('success'));
        $this->assertNull(PersediaanPermintaan::withTrashed()->find($ditolak->id));
        $this->assertSame(0, DB::table('persediaan_stock_movements')->count());
    }

    public function test_permintaan_selesai_dengan_movement_tidak_bisa_dihapus_dan_stok_tetap(): void
    {
        $barang = $this->barang('OUT-001', 20);
        $selesai = $this->permintaan('PRM-DIR-2026-003', PersediaanPermintaan::STATUS_SELESAI);
        $this->item($selesai, $barang, 5);
        $this->movement($barang, $selesai, 5);
        $sebelum = $this->stockFingerprint();
        $controller = app(PermintaanController::class);
        $force = $this->request('DELETE', '/persediaan/permintaan/' . $selesai->id . '/force');

        $controller->forceDestroy($force, $selesai->id);

        $this->assertSame(PersediaanDeletionService::PERMINTAAN_BLOCKED, $force->session()->get('error'));
        $this->assertNotNull(PersediaanPermintaan::query()->find($selesai->id));
        $this->assertSame(1, PersediaanPermintaanItem::query()->where('permintaan_id', $selesai->id)->count());
        $this->assertSame($sebelum, $this->stockFingerprint());

        $soft = $this->request('DELETE', '/persediaan/permintaan/' . $selesai->id);
        $controller->destroy($soft, $selesai->id);
        $selesai->refresh();
        $this->assertNotNull($selesai->deleted_at);
        $this->assertSame($sebelum, $this->stockFingerprint());
        $this->assertSame($barang->nama_barang, PersediaanStockMovement::query()->first()->barang->nama_barang);
    }

    public function test_bulk_permanent_permintaan_hanya_menghapus_yang_belum_mengurangi_stok(): void
    {
        $barang = $this->barang('BULK-001', 30);
        $rows = [
            $this->permintaan('PRM-DIR-2026-021', PersediaanPermintaan::STATUS_DIAJUKAN),
            $this->permintaan('PRM-DIR-2026-022', PersediaanPermintaan::STATUS_MENUNGGU),
            $this->permintaan('PRM-DIR-2026-023', PersediaanPermintaan::STATUS_DITOLAK),
            $this->permintaan('PRM-DIR-2026-024', PersediaanPermintaan::STATUS_SELESAI),
            $this->permintaan('PRM-DIR-2026-025', PersediaanPermintaan::STATUS_DISETUJUI),
        ];
        foreach ($rows as $row) {
            $this->item($row, $barang, 1);
        }
        $this->movement($barang, $rows[3], 4);
        $this->movement($barang, $rows[4], 3);
        $sebelum = $this->stockFingerprint();
        $request = $this->request('POST', '/persediaan/permintaan/bulk-force-delete', [
            'ids' => array_map(static fn (PersediaanPermintaan $row): int => (int) $row->id, $rows),
        ]);

        app(PermintaanController::class)->bulkForceDestroy($request);

        $this->assertSame(
            '3 permintaan berhasil dihapus permanen. 2 permintaan tidak dapat dihapus karena sudah memengaruhi stok.',
            $request->session()->get('success'),
        );
        $this->assertNull(PersediaanPermintaan::withTrashed()->find($rows[0]->id));
        $this->assertNull(PersediaanPermintaan::withTrashed()->find($rows[1]->id));
        $this->assertNull(PersediaanPermintaan::withTrashed()->find($rows[2]->id));
        $this->assertNotNull(PersediaanPermintaan::query()->find($rows[3]->id));
        $this->assertNotNull(PersediaanPermintaan::query()->find($rows[4]->id));
        $this->assertSame(0, PersediaanPermintaanItem::query()->whereIn('permintaan_id', [$rows[0]->id, $rows[1]->id, $rows[2]->id])->count());
        $this->assertSame(2, PersediaanPermintaanItem::query()->whereIn('permintaan_id', [$rows[3]->id, $rows[4]->id])->count());
        $this->assertSame($sebelum, $this->stockFingerprint());
        $this->assertSame(2, PersediaanStockMovement::query()->count());
    }

    public function test_soft_delete_barang_berhistori_tidak_merusak_movement_dashboard_dan_laporan(): void
    {
        $histori = $this->barang('HISTORI-81', 20);
        $histori->nama_barang = 'Barang Histori Unik';
        $histori->save();
        $tetap = $this->barang('TETAP-81', 81);
        $tetap->nama_barang = 'Barang Tetap Delapan Puluh Satu';
        $tetap->save();
        $permintaan = $this->permintaan('PRM-DIR-2026-081', PersediaanPermintaan::STATUS_SELESAI);
        $this->item($permintaan, $histori, 5);
        $this->movement($histori, $permintaan, 5);

        app(MasterBarangController::class)->destroy(
            $this->request('DELETE', '/persediaan/master-barang/' . $histori->id),
            $histori->id,
        );

        $movement = PersediaanStockMovement::query()->first();
        $this->assertSame(5, (int) $movement->qty_keluar);
        $this->assertSame('Barang Histori Unik', $movement->barang->nama_barang);

        $report = app(PersediaanValuationService::class)->reconcile(['active_only' => true]);
        $this->assertSame(81, $report['summary']['total_stok_saat_ini']);
        $this->assertSame(1, $report['summary']['total_jenis_barang']);

        $dashboard = app(DashboardController::class)->index($this->request('GET', '/persediaan/dashboard'));
        $this->assertSame(81, $dashboard->getData()['totalStokSaatIni']);
        $this->assertStringContainsString('Total Stok Saat Ini', $dashboard->render());

        $laporan = app(LaporanController::class)->index($this->request('GET', '/persediaan/laporan'))->render();
        $this->assertStringContainsString('Barang Tetap Delapan Puluh Satu', $laporan);
        $this->assertStringNotContainsString('Barang Histori Unik', $laporan);
    }

    public function test_unit_lain_tidak_bisa_menghapus_dan_get_tidak_menghapus(): void
    {
        $dir = $this->barang('DIR-LOCK', 1);
        $kepBarang = $this->barang('KEP-LOCK', 1);
        $kepBarang->unit_kerja = UnitKerja::KEPERAWATAN;
        $kepBarang->save();

        $kep = User::create([
            'name'     => 'Admin Keperawatan',
            'email'    => 'kep-' . uniqid('', true) . '@example.test',
            'password' => Hash::make('secret'),
        ]);
        config([
            'persediaan.approvers' => [
                'Keperawatan' => [
                    'user_id' => (int) $kep->id,
                    'email'   => $kep->email,
                    'name'    => 'Admin Keperawatan',
                ],
            ],
        ]);
        PersediaanAdmin::create([
            'user_id'   => $kep->id,
            'role'      => PersediaanAdmin::ROLE_KEPERAWATAN,
            'satker'    => UnitKerja::KEPERAWATAN,
            'is_active' => true,
        ]);
        $session = [
            PersediaanAdminSession::KEY_USER_ID => (int) $kep->id,
            PersediaanAdminSession::KEY_ROLE    => PersediaanAdmin::ROLE_KEPERAWATAN,
            PersediaanAdminSession::KEY_SATKER  => UnitKerja::KEPERAWATAN,
        ];

        $denied = $this->request('DELETE', '/persediaan/master-barang/' . $dir->id, [], $session);
        try {
            app(MasterBarangController::class)->destroy($denied, $dir->id);
            $this->fail('Barang unit lain harus ditolak.');
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertNull(PersediaanBarang::withTrashed()->find($dir->id)->deleted_at);

        $allowed = $this->request('DELETE', '/persediaan/master-barang/' . $kepBarang->id, [], $session);
        app(MasterBarangController::class)->destroy($allowed, $kepBarang->id);
        $this->assertNotNull(PersediaanBarang::withTrashed()->find($kepBarang->id)->deleted_at);

        $route = app('router')->getRoutes()->getByName('persediaan.admin.master-barang.destroy');
        $this->assertContains('DELETE', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    public function test_daftar_permintaan_punya_checkbox_dan_filter_sampah(): void
    {
        $trashed = $this->permintaan('PRM-DIR-2026-099', PersediaanPermintaan::STATUS_DRAFT);
        $trashed->delete();

        $aktif = app(PermintaanController::class)->index($this->request('GET', '/persediaan/permintaan'))->render();
        $this->assertStringContainsString('Pilih semua permintaan di halaman ini', $aktif);
        $this->assertStringContainsString('Soft Delete Terpilih', $aktif);
        $this->assertStringContainsString('Hapus Permanen Terpilih', $aktif);
        $this->assertStringNotContainsString('PRM-DIR-2026-099', $aktif);

        $sampah = app(PermintaanController::class)
            ->index($this->request('GET', '/persediaan/permintaan?lifecycle=sampah'))
            ->render();
        $this->assertStringContainsString('PRM-DIR-2026-099', $sampah);
        $this->assertStringContainsString('Restore', $sampah);
    }

    public function test_bulk_soft_delete_barang(): void
    {
        $rows = [$this->barang('S1', 1), $this->barang('S2', 1)];
        $request = $this->request('POST', '/persediaan/master-barang/bulk-delete', [
            'ids' => [(int) $rows[0]->id, (int) $rows[1]->id, 99999],
        ]);

        app(MasterBarangController::class)->bulkDestroy($request);

        $this->assertSame('2 barang berhasil di-soft delete. 1 data tidak ditemukan.', $request->session()->get('success'));
        $this->assertNotNull($rows[0]->fresh()->deleted_at);
        $this->assertNotNull($rows[1]->fresh()->deleted_at);
        $this->assertTrue($rows[0]->fresh()->is_active);
    }

    private function loginAdmin(): void
    {
        $user = User::create([
            'name'     => 'Admin Persediaan',
            'email'    => 'admin-delete-' . uniqid('', true) . '@example.test',
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
     * @param  array<string, mixed>|null  $session
     */
    private function request(string $method, string $uri, array $payload = [], ?array $session = null): Request
    {
        $request = Request::create($uri, $method, $payload);
        $store = app('session')->driver();
        if (! $store->isStarted()) {
            $store->start();
        }
        foreach ($session ?? $this->adminSession as $key => $value) {
            $store->put($key, $value);
        }
        $request->setLaravelSession($store);
        $this->app->instance('request', $request);

        return $request;
    }

    private function barang(string $kode, int $stok, ?string $barcode = null): PersediaanBarang
    {
        return PersediaanBarang::create([
            'kode_barang'  => $kode,
            'nama_barang'  => 'Barang ' . $kode,
            'satuan'       => 'Buah',
            'unit_kerja'   => UnitKerja::DIREKTORAT,
            'stok_awal'    => $stok,
            'stok_minimum' => 0,
            'harga_satuan' => 1000,
            'is_active'    => true,
            'barcode_path' => $barcode,
        ]);
    }

    private function permintaan(string $nomor, string $status): PersediaanPermintaan
    {
        return PersediaanPermintaan::create([
            'nomor_permintaan'   => $nomor,
            'pemohon_id'         => 1,
            'unit_kerja'         => UnitKerja::DIREKTORAT,
            'tanggal_permintaan' => '2026-10-01',
            'status'             => $status,
        ]);
    }

    private function item(PersediaanPermintaan $permintaan, PersediaanBarang $barang, int $jumlah): void
    {
        PersediaanPermintaanItem::create([
            'permintaan_id'  => $permintaan->id,
            'barang_id'      => $barang->id,
            'kode_barang'    => $barang->kode_barang,
            'nama_barang'    => $barang->nama_barang,
            'satuan'         => 'Buah',
            'stok_tersedia'  => 10,
            'jumlah_diminta' => $jumlah,
        ]);
    }

    private function movement(PersediaanBarang $barang, PersediaanPermintaan $permintaan, int $qty): void
    {
        PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => $barang->unit_kerja,
            'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
            'ref_table'  => 'persediaan_permintaan',
            'ref_id'     => $permintaan->id,
            'qty_masuk'  => 0,
            'qty_keluar' => $qty,
            'keterangan' => 'Pengeluaran ' . $permintaan->nomor_permintaan,
        ]);
    }

    /** @return array{rows:int,keluar:int,masuk:int} */
    private function stockFingerprint(): array
    {
        return [
            'rows'   => (int) DB::table('persediaan_stock_movements')->count(),
            'keluar' => (int) DB::table('persediaan_stock_movements')->sum('qty_keluar'),
            'masuk'  => (int) DB::table('persediaan_stock_movements')->sum('qty_masuk'),
        ];
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
        Schema::create('persediaan_pemohon', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama')->nullable();
            $table->timestamps();
        });
        DB::table('persediaan_pemohon')->insert([
            'id'         => 1,
            'nama'       => 'Pemohon Uji',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        Schema::create('persediaan_permintaan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_permintaan');
            $table->unsignedBigInteger('pemohon_id');
            $table->string('unit_kerja');
            $table->date('tanggal_permintaan');
            $table->string('status')->default('draft');
            $table->string('tanda_tangan_pemohon_path')->nullable();
            $table->string('tanda_tangan_admin_path')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('persediaan_permintaan_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('permintaan_id');
            $table->unsignedBigInteger('barang_id')->nullable();
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
            $table->integer('stok_tersedia')->default(0);
            $table->integer('jumlah_diminta')->default(0);
            $table->integer('jumlah_disetujui')->nullable();
            $table->timestamps();
        });
        Schema::create('persediaan_rak', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_rak');
            $table->string('nama_rak')->nullable();
            $table->string('unit_kerja');
            $table->string('qr_token')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('persediaan_rak_barang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rak_id');
            $table->unsignedBigInteger('barang_id');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
        Schema::create('persediaan_pembelian_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pembelian_id')->nullable();
            $table->unsignedBigInteger('barang_id')->nullable();
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

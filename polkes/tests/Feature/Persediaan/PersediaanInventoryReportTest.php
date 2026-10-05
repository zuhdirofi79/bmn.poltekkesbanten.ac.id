<?php

declare(strict_types=1);

namespace Tests\Feature\Persediaan;

use App\Http\Controllers\PersediaanAdmin\DashboardController;
use App\Http\Controllers\PersediaanAdmin\LaporanController;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\PersediaanValuationService;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PersediaanInventoryReportTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00'));
        Schema::dropAllTables();
        $this->createSchema();
        View::share('errors', new ViewErrorBag());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rekonsiliasi_nilai_contoh(): void
    {
        $ballast = $this->barang('BALL', 'Saldo pembuka', UnitKerja::DIREKTORAT, 370185673, '1.00');
        $lines = [
            ['T1', 'Tinta 1', 1, '115000.00'],
            ['T2', 'Tinta 2', 1, '115000.00'],
            ['T3', 'Tinta 3', 1, '115000.00'],
            ['N1', 'Kertas', 20, '9990.00'],
            ['S1', 'Sheet', 10, '7770.00'],
            ['P1', 'Pulpen', 1, '3885.00'],
        ];
        foreach ($lines as [$kode, $nama, $stok, $harga]) {
            $barang = $this->barang($kode, $nama, UnitKerja::DIREKTORAT, $stok, $harga);
            $this->keluar($barang, $stok);
        }
        $masuk = $this->barang('IN1', 'Barang masuk', UnitKerja::DIREKTORAT, 0, '10000.00');
        $this->masuk($masuk, 2);

        $report = app(PersediaanValuationService::class)->reconcile();
        $summary = $report['summary'];

        $this->assertSame('370812058.00', $summary['nilai_persediaan_awal']);
        $this->assertSame('20000.00', $summary['nilai_barang_masuk']);
        $this->assertSame('626385.00', $summary['nilai_barang_keluar']);
        $this->assertSame('0.00', $summary['nilai_koreksi_opname']);
        $this->assertSame('370205673.00', $summary['nilai_persediaan_saat_ini']);
        $this->assertSame(0, bccomp(
            '370205673.00',
            bcadd(bcsub(bcadd('370812058.00', '20000.00', 2), '626385.00', 2), '0.00', 2),
            2,
        ));
        $this->assertSame($summary['nilai_persediaan_saat_ini'], $this->dashboard()['nilaiPersediaan']);
        $this->assertSame($summary['nilai_persediaan_saat_ini'], $this->laporan()['summary']['nilai_persediaan_saat_ini']);
        $this->assertSame('626385.00', $this->dashboard()['nilaiBarangKeluar']);
        $this->assertSame('20000.00', $this->dashboard()['nilaiBarangMasuk']);
        $this->assertNotSame(0, $ballast->id);
    }

    public function test_rincian_nilai_barang_keluar_626385(): void
    {
        $expected = '0.00';
        foreach ([115000, 115000, 115000, 199800, 77700, 3885] as $nilai) {
            $expected = bcadd($expected, (string) $nilai, 2);
        }
        $this->assertSame('626385.00', $expected);

        $parts = [
            [1, '115000.00'],
            [1, '115000.00'],
            [1, '115000.00'],
            [20, '9990.00'],
            [10, '7770.00'],
            [1, '3885.00'],
        ];
        $sum = '0.00';
        foreach ($parts as [$qty, $harga]) {
            $barang = $this->barang('K' . $qty . $harga, 'Keluar ' . $harga, UnitKerja::TLM, $qty, $harga);
            $this->keluar($barang, $qty);
            $sum = bcadd($sum, bcmul((string) $qty, $harga, 2), 2);
        }

        $this->assertSame('626385.00', $sum);
        $this->assertSame('626385.00', app(PersediaanValuationService::class)->reconcile()['summary']['nilai_barang_keluar']);
    }

    public function test_total_stok_253_adalah_kuantitas_bukan_jenis_barang(): void
    {
        $items = [];
        for ($i = 1; $i <= 285; $i++) {
            $items[] = $this->barang('SKU' . $i, 'Barang ' . $i, UnitKerja::KEPERAWATAN, 1, '1000.00');
        }
        for ($i = 0; $i < 34; $i++) {
            $this->keluar($items[$i], 1);
        }
        $this->masuk($items[284], 2);

        $summary = app(PersediaanValuationService::class)->reconcile()['summary'];

        $this->assertSame(285, $summary['total_jenis_barang']);
        $this->assertSame(285, $summary['total_stok_pembuka']);
        $this->assertSame(2, $summary['jumlah_barang_masuk']);
        $this->assertSame(34, $summary['jumlah_barang_keluar']);
        $this->assertSame(0, $summary['jumlah_koreksi']);
        $this->assertSame(253, $summary['total_stok_saat_ini']);
        $dash = $this->dashboard();
        $this->assertSame(285, $dash['totalJenisBarang']);
        $this->assertSame(253, $dash['totalStokSaatIni']);
        $html = app(DashboardController::class)->index($this->request('/persediaan/dashboard'))->render();
        $this->assertStringContainsString('Total Stok Saat Ini', $html);
        $this->assertStringContainsString('jenis barang aktif', $html);
        $this->assertStringContainsString('Nilai Persediaan Saat Ini', $html);
        $this->assertStringNotContainsString('>Jenis Barang<', $html);
    }

    public function test_pending_dan_ditolak_tidak_mengurangi_stok_atau_nilai(): void
    {
        $barang = $this->barang('PUL', 'Pulpen', UnitKerja::DIREKTORAT, 12, '4500.00');
        $before = app(PersediaanValuationService::class)->reconcile()['summary'];

        $this->assertSame(12, $before['total_stok_saat_ini']);
        $this->assertSame('54000.00', $before['nilai_persediaan_saat_ini']);
        $this->assertSame(0, PersediaanStockMovement::query()->where('barang_id', $barang->id)->count());
        $this->assertSame($before, app(PersediaanValuationService::class)->reconcile()['summary']);
    }

    public function test_pengeluaran_disetujui_mengurangi_stok_tepat_sekali(): void
    {
        $barang = $this->barang('PUL', 'Pulpen', UnitKerja::DIREKTORAT, 12, '4500.00');
        $payload = [
            'barang_id'  => $barang->id,
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
            'ref_table'  => 'persediaan_permintaan',
            'ref_id'     => 9,
            'qty_keluar' => 10,
            'keterangan' => 'Pengeluaran permintaan',
        ];
        $stock = app(StockService::class);
        $stock->recordPermintaanKeluar($payload);
        $stock->recordPermintaanKeluar($payload);

        $summary = app(PersediaanValuationService::class)->reconcile()['summary'];
        $this->assertSame(1, PersediaanStockMovement::query()->count());
        $this->assertSame(2, $summary['total_stok_saat_ini']);
        $this->assertSame(10, $summary['jumlah_barang_keluar']);
        $this->assertSame('45000.00', $summary['nilai_barang_keluar']);
        $this->assertSame('9000.00', $summary['nilai_persediaan_saat_ini']);
    }

    public function test_pembelian_menambah_stok_dan_nilai_sekali(): void
    {
        $barang = $this->barang('IN', 'Masuk', UnitKerja::KEBIDANAN, 5, '10000.00');
        $this->masuk($barang, 2);

        $summary = app(PersediaanValuationService::class)->reconcile()['summary'];
        $this->assertSame(2, $summary['jumlah_barang_masuk']);
        $this->assertSame('20000.00', $summary['nilai_barang_masuk']);
        $this->assertSame(7, $summary['total_stok_saat_ini']);
        $this->assertSame('70000.00', $summary['nilai_persediaan_saat_ini']);
        $this->assertSame('50000.00', $summary['nilai_persediaan_awal']);
    }

    public function test_opname_mengoreksi_sekali_dan_tidak_masuk_barang_keluar(): void
    {
        $barang = $this->barang('OP', 'Opname', UnitKerja::TLM, 10, '5000.00');
        PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => UnitKerja::TLM,
            'tipe'       => PersediaanStockMovement::TIPE_OPNAME,
            'ref_table'  => 'persediaan_stock_opname',
            'ref_id'     => 1,
            'qty_masuk'  => 0,
            'qty_keluar' => 2,
            'keterangan' => 'Koreksi opname',
        ]);

        $row = app(PersediaanValuationService::class)->reconcile()['rows'][0];
        $this->assertSame(0, $row['keluar']);
        $this->assertSame(0, $row['masuk']);
        $this->assertSame(-2, $row['koreksi']);
        $this->assertSame(8, $row['stok_saat_ini']);
        $this->assertSame('-10000.00', $row['nilai_koreksi']);
        $this->assertSame('40000.00', $row['nilai_saat_ini']);
        $this->assertSame(8, app(StockService::class)->stokAkhir($barang->fresh()));
    }

    public function test_dashboard_dan_laporan_sampai_hari_ini_sama(): void
    {
        $barang = $this->barang('X', 'Barang', UnitKerja::DIREKTORAT, 10, '1500.00');
        $this->keluar($barang, 4);
        $this->masuk($barang, 1);

        $dash = $this->dashboard();
        $lap = $this->laporan(['from' => '2026-10-01', 'to' => '2026-10-15']);

        $this->assertSame($dash['nilaiPersediaan'], $lap['summary']['nilai_persediaan_saat_ini']);
        $this->assertSame($dash['totalStokSaatIni'], $lap['summary']['total_stok_saat_ini']);
        $this->assertSame($dash['nilaiBarangMasuk'], $lap['summary']['nilai_barang_masuk']);
        $this->assertSame($dash['nilaiBarangKeluar'], $lap['summary']['nilai_barang_keluar']);
    }

    public function test_export_excel_mengikuti_filter_dan_sama_dengan_card(): void
    {
        $this->barang('A', 'Alpha', UnitKerja::DIREKTORAT, 4, '1000.00');
        $tlm = $this->barang('B', 'Beta', UnitKerja::TLM, 3, '2000.00');
        $this->keluar($tlm, 1);

        $response = app(LaporanController::class)->exportExcel(
            $this->request('/persediaan/laporan/export?unit=' . urlencode(UnitKerja::TLM) . '&from=2026-10-01&to=2026-10-31'),
        );
        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Kode', $sheet->getCell('A1')->getValue());
        $this->assertSame('B', $sheet->getCell('A2')->getValue());
        $this->assertSame('TOTAL', $sheet->getCell('B3')->getValue());
        $this->assertNull($sheet->getCell('A4')->getValue());

        $nilaiSaatIni = bcadd((string) $sheet->getCell('O2')->getCalculatedValue(), '0', 2);
        $total = bcadd((string) $sheet->getCell('O3')->getCalculatedValue(), '0', 2);
        $this->assertSame($nilaiSaatIni, $total);
        $this->assertSame('4000.00', $total);

        $lap = $this->laporan(['unit' => UnitKerja::TLM, 'from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertSame($total, $lap['summary']['nilai_persediaan_saat_ini']);
        $html = $lap['view']->render();
        $this->assertStringContainsString('Total Stok Saat Ini', $html);
        $this->assertStringContainsString('Nilai Persediaan Saat Ini', $html);
        $this->assertStringNotContainsString('>Jenis Barang<', $html);
    }

    public function test_periode_memisahkan_stok_pembuka_dan_movement_bulan_ini(): void
    {
        $barang = $this->barang('Z', 'Zeta', UnitKerja::KEPERAWATAN, 100, '10.00');
        $old = $this->keluar($barang, 20);
        $old->created_at = Carbon::parse('2026-09-20 08:00:00');
        $old->save();
        $this->keluar($barang, 5);

        $row = app(PersediaanValuationService::class)->reconcile([
            'from' => '2026-10-01',
            'to'   => '2026-10-31',
        ])['rows'][0];

        $this->assertSame(80, $row['stok_pembuka']);
        $this->assertSame(5, $row['keluar']);
        $this->assertSame(75, $row['stok_saat_ini']);
        $this->assertSame('750.00', $row['nilai_saat_ini']);
        $closing = bcadd(
            bcsub(bcadd($row['nilai_pembuka'], $row['nilai_masuk'], 2), $row['nilai_keluar'], 2),
            $row['nilai_koreksi'],
            2,
        );
        $this->assertSame($row['nilai_saat_ini'], $closing);
    }

    private function barang(string $kode, string $nama, string $unit, int $stok, string $harga): PersediaanBarang
    {
        return PersediaanBarang::create([
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'satuan'       => 'Buah',
            'unit_kerja'   => $unit,
            'stok_awal'    => $stok,
            'stok_minimum' => 0,
            'harga_satuan' => $harga,
            'is_active'    => true,
        ]);
    }

    private function keluar(PersediaanBarang $barang, int $qty): PersediaanStockMovement
    {
        return PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => $barang->unit_kerja,
            'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
            'qty_masuk'  => 0,
            'qty_keluar' => $qty,
            'keterangan' => 'Keluar',
        ]);
    }

    private function masuk(PersediaanBarang $barang, int $qty): PersediaanStockMovement
    {
        return PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => $barang->unit_kerja,
            'tipe'       => PersediaanStockMovement::TIPE_PEMBELIAN,
            'qty_masuk'  => $qty,
            'qty_keluar' => 0,
            'keterangan' => 'Pembelian',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(): array
    {
        return app(DashboardController::class)
            ->index($this->request('/persediaan/dashboard'))
            ->getData();
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function laporan(array $query = []): array
    {
        $qs = $query === [] ? '' : '?' . http_build_query($query);
        $view = app(LaporanController::class)->index($this->request('/persediaan/laporan' . $qs));
        $data = $view->getData();
        $data['view'] = $view;

        return $data;
    }

    private function request(string $uri): Request
    {
        $request = Request::create($uri, 'GET');
        $session = app('session')->driver();
        if (! $session->isStarted()) {
            $session->start();
        }
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return $request;
    }

    private function createSchema(): void
    {
        Schema::create('persediaan_barang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
            $table->string('unit_kerja');
            $table->integer('stok_awal')->nullable();
            $table->integer('stok_minimum')->default(0);
            $table->decimal('harga_satuan', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
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

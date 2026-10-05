<?php

declare(strict_types=1);

namespace Tests\Feature\Persediaan;

use App\Http\Controllers\PersediaanAdmin\DashboardController;
use App\Http\Controllers\PersediaanAdmin\MasterBarangController;
use App\Imports\PersediaanMasterBarangImport;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\PersediaanValuationService;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PersediaanNilaiPersediaanTest extends TestCase
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
        Schema::dropAllTables();
        $this->createSchema();
    }

    public function test_dua_barang_contoh_dashboard_sama_dengan_export(): void
    {
        $this->barang('A001', 'Barang A', UnitKerja::KEPERAWATAN, 5, '7500.00');
        $this->barang('B001', 'Barang B', UnitKerja::KEPERAWATAN, 70, '83500.00');

        $this->assertSame('37500.00', app(PersediaanValuationService::class)->rowValue(5, '7500.00'));
        $this->assertSame('5845000.00', app(PersediaanValuationService::class)->rowValue(70, '83500.00'));
        $this->assertSame('5882500.00', $this->dashboardNilai());
        $this->assertSame('5882500.00', $this->exportNilai());
        $this->assertSame($this->catalogIds(), $this->exportIds());
    }

    public function test_stok_atau_harga_null_dihitung_nol_tanpa_error(): void
    {
        DB::table('persediaan_barang')->insert([
            $this->row('N1', 'Stok null', UnitKerja::DIREKTORAT, null, '10000.00'),
            $this->row('N2', 'Harga null', UnitKerja::DIREKTORAT, 10, null),
        ]);

        $service = app(PersediaanValuationService::class);

        $this->assertSame('0.00', $service->rowValue(null, '10000.00'));
        $this->assertSame('0.00', $service->rowValue(10, null));
        $this->assertSame('0.00', $this->dashboardNilai());
        $this->assertSame('0.00', $this->exportNilai());
    }

    public function test_harga_decimal_tidak_berubah_karena_pembulatan(): void
    {
        $this->barang('D1', 'Decimal', UnitKerja::DIREKTORAT, 3, '16650.00');

        $this->assertSame('49950.00', app(PersediaanValuationService::class)->rowValue(3, '16650.00'));
        $this->assertSame('49950.00', $this->dashboardNilai());
        $this->assertSame('49950.00', $this->exportNilai());
    }

    public function test_pengeluaran_sepuluh_pulpen_mengurangi_nilai_saat_ini_empat_puluh_lima_ribu(): void
    {
        $barang = $this->barang('011698', 'Pulpen Joyko Q Gel Biru (bmn)', UnitKerja::DIREKTORAT, 29, '4500.00');
        PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
            'ref_table'  => 'persediaan_permintaan',
            'ref_id'     => 1,
            'qty_masuk'  => 0,
            'qty_keluar' => 10,
            'keterangan' => 'Pengeluaran barang permintaan PRM-DIR-2026-001',
        ]);

        $this->assertSame('130500.00', $this->exportNilai());
        $this->assertSame('85500.00', $this->dashboardNilai());
        $view = app(DashboardController::class)->index($this->request('/persediaan/dashboard'));
        $this->assertSame(1, $view->getData()['totalJenisBarang']);
        $this->assertSame(19, $view->getData()['totalStokSaatIni']);
    }

    public function test_ubah_harga_langsung_menaikkan_dashboard_dan_export(): void
    {
        $barang = $this->barang('H1', 'Harga berubah', UnitKerja::TLM, 1, '45000.00');

        $this->assertSame('45000.00', $this->dashboardNilai());
        $this->assertSame('45000.00', $this->exportNilai());

        $barang->harga_satuan = '50000.00';
        $barang->save();

        $this->assertSame('50000.00', $this->dashboardNilai());
        $this->assertSame('50000.00', $this->exportNilai());
    }

    public function test_import_master_barang_langsung_masuk_nilai_dashboard(): void
    {
        $path = $this->writeImportFile([
            ['kode_barang', 'nama_barang', 'unit_kerja', 'satuan', 'stok_awal', 'harga_satuan', 'status'],
            ['A001', 'Bedak', UnitKerja::KEPERAWATAN, 'TUBE', 5, 7500, 'aktif'],
        ]);

        try {
            $result = (new PersediaanMasterBarangImport(null))->import(
                new UploadedFile($path, 'master.xlsx', null, null, true),
            );
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(1, PersediaanStockMovement::query()->where('tipe', PersediaanStockMovement::TIPE_STOK_AWAL)->count());
        $this->assertSame('37500.00', $this->dashboardNilai());
        $this->assertSame('37500.00', $this->exportNilai());
    }

    public function test_barang_yang_dihapus_keluar_dari_dashboard_dan_export(): void
    {
        $tetap = $this->barang('T1', 'Tetap', UnitKerja::KEBIDANAN, 2, '1000.00');
        $hapus = $this->barang('T2', 'Hapus', UnitKerja::KEBIDANAN, 4, '2500.00');

        $this->assertSame('12000.00', $this->dashboardNilai());

        $hapus->delete();

        $this->assertSame('2000.00', $this->dashboardNilai());
        $this->assertSame('2000.00', $this->exportNilai());
        $this->assertSame([(int) $tetap->id], $this->catalogIds());
        $this->assertSame($this->catalogIds(), $this->exportIds());
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

    /**
     * @return array<string, mixed>
     */
    private function row(string $kode, string $nama, string $unit, ?int $stok, ?string $harga): array
    {
        return [
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'satuan'       => 'Buah',
            'unit_kerja'   => $unit,
            'stok_awal'    => $stok,
            'stok_minimum' => 0,
            'harga_satuan' => $harga,
            'is_active'    => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }

    private function dashboardNilai(): string
    {
        $view = app(DashboardController::class)->index($this->request('/persediaan/dashboard'));

        return bcadd((string) $view->getData()['nilaiPersediaan'], '0', 2);
    }

    private function exportNilai(): string
    {
        $sum = '0.00';
        foreach ($this->exportRows() as $row) {
            $sum = bcadd($sum, bcmul((string) $row['stok'], $row['harga'], 2), 2);
        }

        return $sum;
    }

    /**
     * @return list<int>
     */
    private function catalogIds(): array
    {
        return app(PersediaanValuationService::class)
            ->activeCatalogQuery()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function exportIds(): array
    {
        $ids = [];
        foreach ($this->exportRows() as $row) {
            $ids[] = (int) PersediaanBarang::query()
                ->where('kode_barang', $row['kode'])
                ->where('unit_kerja', $row['unit'])
                ->value('id');
        }
        sort($ids);

        return $ids;
    }

    /**
     * @return list<array{kode: string, unit: string, stok: int, harga: string}>
     */
    private function exportRows(): array
    {
        $response = app(MasterBarangController::class)->exportExcel(
            $this->request('/persediaan/master-barang/export'),
        );
        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        $rows = [];
        $highest = $sheet->getHighestDataRow();
        for ($row = 2; $row <= $highest; $row++) {
            $kode = trim((string) $sheet->getCell('B' . $row)->getValue());
            if ($kode === '') {
                continue;
            }
            $rows[] = [
                'kode'  => $kode,
                'unit'  => trim((string) $sheet->getCell('D' . $row)->getValue()),
                'stok'  => (int) $sheet->getCell('F' . $row)->getCalculatedValue(),
                'harga' => bcadd((string) $sheet->getCell('G' . $row)->getCalculatedValue(), '0', 2),
            ];
        }

        return $rows;
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

    /**
     * @param  list<list<int|string>>  $rows
     */
    private function writeImportFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = storage_path('app/nilai-persediaan-import-' . uniqid('', true) . '.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
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

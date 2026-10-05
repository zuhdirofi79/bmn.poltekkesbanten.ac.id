<?php

declare(strict_types=1);

namespace Tests\Feature\Persediaan;

use App\Models\Persediaan\PersediaanAdmin;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPembelian;
use App\Models\Persediaan\PersediaanPembelianItem;
use App\Http\Controllers\Persediaan\PermintaanController;
use App\Http\Controllers\PersediaanAdmin\PembelianController;
use App\Models\Persediaan\PersediaanPemohon;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\DuplicateFakturException;
use App\Services\Persediaan\PurchaseService;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\UnitKerja;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PersediaanPembelianPermintaanTest extends TestCase
{
    /** @var array<string,mixed> */
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
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Schema::dropAllTables();
        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('persediaan_admins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('role', 100);
            $table->string('satker', 100)->nullable();
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
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['kode_barang', 'unit_kerja']);
        });

        Schema::create('persediaan_pembelian', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_faktur');
            $table->string('penyedia')->nullable();
            $table->date('tanggal');
            $table->string('unit_kerja')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('faktur_path')->nullable();
            $table->string('bast_path')->nullable();
            $table->string('invoice_path')->nullable();
            $table->string('foto_barang_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('persediaan_pembelian_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pembelian_id');
            $table->unsignedBigInteger('barang_id')->nullable();
            $table->string('kode_barang');
            $table->string('nama_barang');
            $table->string('satuan')->default('Buah');
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
            $table->string('ref_table')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->integer('qty_masuk')->default(0);
            $table->integer('qty_keluar')->default(0);
            $table->string('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('persediaan_pemohon', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');
            $table->string('nip', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('nomor_wa')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('unit_kerja');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('persediaan_permintaan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_permintaan')->unique();
            $table->unsignedBigInteger('pemohon_id');
            $table->string('unit_kerja');
            $table->date('tanggal_permintaan');
            $table->text('keterangan')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->string('tanda_tangan_pemohon_path')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->string('tanda_tangan_admin_path')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->text('approval_note')->nullable();
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
    }

    public function test_pembelian_master_menaikkan_stok_tepat_sekali(): void
    {
        $this->loginAdmin();
        $barang = $this->makeBarang('Pulpen', 'ATK001', 10);

        $this->assertRedirectsTo(
            app(PembelianController::class)->store($this->adminRequest('POST', '/persediaan/pembelian', [
                'nomor_faktur' => 'INV-MASTER',
                'tanggal'      => '2026-10-01',
                'penyedia'     => 'PT ABC',
                'unit_kerja'   => UnitKerja::KEPERAWATAN,
                'items'        => [[
                    'mode'         => 'master',
                    'barang_id'    => $barang->id,
                    'kode_barang'  => 'ATK001',
                    'nama_barang'  => 'Pulpen',
                    'satuan'       => 'Buah',
                    'harga_satuan' => 2500,
                    'jumlah'       => 20,
                ]],
            ])),
            'persediaan.admin.pembelian.index',
        );

        $this->assertSame(1, PersediaanPembelian::count());
        $this->assertSame(1, PersediaanPembelianItem::count());
        $this->assertSame(1, PersediaanStockMovement::where('tipe', PersediaanStockMovement::TIPE_PEMBELIAN)->count());
        $this->assertSame(20, (int) PersediaanStockMovement::sum('qty_masuk'));
        $this->assertSame(30, app(StockService::class)->stokAkhir($barang->fresh()));

        $item = PersediaanPembelianItem::first();
        app(StockService::class)->recordPembelianMasuk(
            (int) $barang->id,
            UnitKerja::KEPERAWATAN,
            (int) $item->id,
            20,
            'ulang',
            null,
        );
        $this->assertSame(20, (int) PersediaanStockMovement::sum('qty_masuk'));
        $this->assertSame(30, app(StockService::class)->stokAkhir($barang->fresh()));
    }

    public function test_pembelian_manual_tidak_mengubah_stok_meski_kode_sama(): void
    {
        $this->loginAdmin();
        $barang = $this->makeBarang('Pulpen', 'ATK001', 10);

        $this->assertRedirectsTo(
            app(PembelianController::class)->store($this->adminRequest('POST', '/persediaan/pembelian', [
                'nomor_faktur' => 'INV-MANUAL',
                'tanggal'      => '2026-10-01',
                'penyedia'     => 'PT ABC',
                'unit_kerja'   => UnitKerja::KEPERAWATAN,
                'items'        => [[
                    'mode'         => 'manual',
                    'barang_id'    => $barang->id,
                    'kode_barang'  => 'ATK001',
                    'nama_barang'  => 'Pulpen ketik manual',
                    'satuan'       => 'Buah',
                    'harga_satuan' => 75000,
                    'jumlah'       => 20,
                ]],
            ])),
            'persediaan.admin.pembelian.index',
        );

        $item = PersediaanPembelianItem::first();
        $this->assertNull($item->barang_id);
        $this->assertSame('Pulpen ketik manual', $item->nama_barang);
        $this->assertSame(0, PersediaanStockMovement::count());
        $this->assertSame(10, app(StockService::class)->stokAkhir($barang->fresh()));
    }

    public function test_satu_pembelian_banyak_barang(): void
    {
        $this->loginAdmin();
        $a = $this->makeBarang('Pulpen', 'ATK001', 0);
        $b = $this->makeBarang('Kertas', 'ATK002', 0);

        $stored = app(PembelianController::class)->store($this->adminRequest('POST', '/persediaan/pembelian', [
            'nomor_faktur' => 'INV-BANYAK',
            'tanggal'      => '2026-10-01',
            'penyedia'     => 'PT ABC',
            'unit_kerja'   => UnitKerja::KEPERAWATAN,
            'items'        => [
                ['mode' => 'master', 'barang_id' => $a->id, 'harga_satuan' => 1000, 'jumlah' => 2],
                ['mode' => 'manual', 'kode_barang' => 'MAN-001', 'nama_barang' => 'Kabel', 'satuan' => 'Buah', 'harga_satuan' => 5000, 'jumlah' => 3],
                ['mode' => 'master', 'barang_id' => $b->id, 'harga_satuan' => 2000, 'jumlah' => 4],
            ],
        ]));
        $this->assertTrue($stored->isRedirect());

        $pembelian = PersediaanPembelian::with('items')->first();
        $this->assertCount(3, $pembelian->items);
        $this->assertSame(2, PersediaanStockMovement::count());
        $this->assertSame(2, app(StockService::class)->stokAkhir($a->fresh()));
        $this->assertSame(4, app(StockService::class)->stokAkhir($b->fresh()));
    }

    public function test_import_excel_menggabungkan_faktur_dan_tanggal_indonesia(): void
    {
        $this->loginAdmin();
        $barang = $this->makeBarang('Pulpen', 'ATK001', 10);
        $file = $this->workbook([
            ['no_faktur', 'tanggal', 'penyedia', 'unit_kerja', 'kode_barang', 'nama_barang', 'satuan', 'harga_satuan', 'jumlah'],
            ['INV-001', '06/08/2026', 'PT ABC', 'Keperawatan', 'ATK001', 'Pulpen', 'Buah', 2500, 20],
            ['INV-001', '06/08/2026', 'PT ABC', 'Keperawatan', 'ATK002', 'Kertas A4', 'Rim', 55000, 10],
            ['INV-001', '06/08/2026', 'PT ABC', 'Keperawatan', 'ATK003', 'Stapler', 'Buah', 35000, 5],
            ['INV-001', '06/08/2026', 'PT ABC', 'Keperawatan', 'ATK004', 'Map', 'Buah', 5000, 2],
            ['INV-001', '06/08/2026', 'PT ABC', 'Keperawatan', 'ATK005', 'Penghapus', 'Buah', 3000, 8],
            ['INV-002', '02/10/2026', 'PT XYZ', 'Keperawatan', 'MAN-1', 'Map', 'Buah', 5000, 20],
            ['INV-002', '02/10/2026', 'PT XYZ', 'Keperawatan', 'MAN-2', 'Pulpen', 'Buah', 2500, 50],
            ['INV-003', '01/10/2025', 'PT Lama', 'Keperawatan', '', 'Barang lama', 'Buah', 1000, 1],
        ]);

        $upload = $this->adminRequest('POST', '/persediaan/pembelian/import', [], ['file' => $file]);
        $this->assertRedirectsTo(app(PembelianController::class)->import($upload), 'persediaan.admin.pembelian.import.preview');

        $payload = $upload->session()->get('persediaan.pembelian.import');
        $this->assertTrue($payload['preview']['can_import']);
        $this->assertCount(3, $payload['preview']['groups']);
        $this->assertCount(5, $payload['preview']['groups'][0]['items']);
        $this->assertSame('2026-08-06', $payload['preview']['groups'][0]['header']['tanggal']);

        $confirm = $this->adminRequest('POST', '/persediaan/pembelian/import/confirm', ['token' => $payload['token']]);
        $this->assertRedirectsTo(app(PembelianController::class)->importConfirm($confirm), 'persediaan.admin.pembelian.index');

        $this->assertSame(3, PersediaanPembelian::count());
        $this->assertSame(8, PersediaanPembelianItem::count());
        $inv = PersediaanPembelian::where('nomor_faktur', 'INV-001')->first();
        $this->assertSame('2026-08-06', $inv->tanggal->format('Y-m-d'));
        $this->assertSame(5, $inv->items()->count());
        $this->assertSame(1, PersediaanStockMovement::count());
        $this->assertSame(30, app(StockService::class)->stokAkhir($barang->fresh()));
        $this->assertSame('2025-10-01', PersediaanPembelian::where('nomor_faktur', 'INV-003')->first()->tanggal->format('Y-m-d'));
    }

    public function test_import_sepuluh_item_satu_faktur_dan_tanggal_serial_excel(): void
    {
        $this->loginAdmin();
        $this->makeBarang('Pulpen', 'ATK001', 0);

        $sheetRows = [[
            'no_faktur', 'tanggal', 'penyedia', 'unit_kerja', 'kode_barang', 'nama_barang', 'satuan', 'harga_satuan', 'jumlah',
        ]];
        for ($i = 1; $i <= 10; $i++) {
            $sheetRows[] = ['INV-SEPULUH', null, 'PT ABC', 'Keperawatan', 'K' . $i, 'Barang ' . $i, 'Buah', 1000, 1];
        }
        $path = $this->writeSheet($sheetRows);
        $spreadsheet = IOFactory::load($path);
        $cell = $spreadsheet->getActiveSheet()->getCell('B2');
        $cell->setValue(ExcelDate::PHPToExcel(new \DateTime('2026-08-06')));
        $spreadsheet->getActiveSheet()->getStyle('B2:B11')->getNumberFormat()->setFormatCode('DD/MM/YYYY');
        for ($row = 3; $row <= 11; $row++) {
            $spreadsheet->getActiveSheet()->setCellValue('B' . $row, ExcelDate::PHPToExcel(new \DateTime('2026-08-06')));
        }
        (new Xlsx($spreadsheet))->save($path);

        $upload = $this->adminRequest('POST', '/persediaan/pembelian/import', [], [
            'file' => new UploadedFile($path, 'pembelian.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);
        $this->assertRedirectsTo(app(PembelianController::class)->import($upload), 'persediaan.admin.pembelian.import.preview');

        $payload = $upload->session()->get('persediaan.pembelian.import');
        $this->assertTrue($payload['preview']['can_import'], implode(' | ', $payload['preview']['messages'] ?? []));
        $this->assertSame('2026-08-06', $payload['preview']['groups'][0]['header']['tanggal']);
        $this->assertCount(10, $payload['preview']['groups'][0]['items']);
        $this->assertCount(1, $payload['preview']['groups']);
    }

    public function test_import_faktur_duplikat_ditolak_dan_gagal_di_tengah_di_rollback(): void
    {
        $this->loginAdmin();
        $barang = $this->makeBarang('Pulpen', 'ATK001', 10);
        app(PurchaseService::class)->store([
            'nomor_faktur' => 'INV-LAMA',
            'tanggal'      => '2026-01-01',
            'penyedia'     => 'PT Lama',
            'unit_kerja'   => UnitKerja::KEPERAWATAN,
        ], [[
            'barang_id' => null, 'kode_barang' => 'MAN', 'nama_barang' => 'Lama', 'satuan' => 'Buah', 'harga_satuan' => 1, 'jumlah' => 1,
        ]], null);

        $file = $this->workbook([
            ['no_faktur', 'tanggal', 'penyedia', 'unit_kerja', 'kode_barang', 'nama_barang', 'satuan', 'harga_satuan', 'jumlah'],
            ['INV-LAMA', '01/10/2026', 'PT ABC', 'Keperawatan', 'ATK001', 'Pulpen', 'Buah', 2500, 20],
        ]);
        $upload = $this->adminRequest('POST', '/persediaan/pembelian/import', [], ['file' => $file]);
        $this->assertRedirectsTo(app(PembelianController::class)->import($upload), 'persediaan.admin.pembelian.import.preview');
        $payload = $upload->session()->get('persediaan.pembelian.import');
        $this->assertFalse($payload['preview']['can_import']);
        $confirm = $this->adminRequest('POST', '/persediaan/pembelian/import/confirm', ['token' => $payload['token']]);
        app(PembelianController::class)->importConfirm($confirm);
        $this->assertNotEmpty($confirm->session()->get('error'));
        $this->assertSame(1, PersediaanPembelian::count());
        $this->assertSame(10, app(StockService::class)->stokAkhir($barang->fresh()));

        try {
            app(PurchaseService::class)->storeMany([
                [
                    'header' => [
                        'nomor_faktur' => 'INV-BARU',
                        'tanggal'      => '2026-10-01',
                        'penyedia'     => 'PT ABC',
                        'unit_kerja'   => UnitKerja::KEPERAWATAN,
                    ],
                    'items' => [[
                        'barang_id' => $barang->id, 'kode_barang' => 'ATK001', 'nama_barang' => 'Pulpen',
                        'satuan' => 'Buah', 'harga_satuan' => 2500, 'jumlah' => 20,
                    ]],
                ],
                [
                    'header' => [
                        'nomor_faktur' => 'INV-LAMA',
                        'tanggal'      => '2026-10-02',
                        'penyedia'     => 'PT ABC',
                        'unit_kerja'   => UnitKerja::KEPERAWATAN,
                    ],
                    'items' => [[
                        'barang_id' => null, 'kode_barang' => 'X', 'nama_barang' => 'X', 'satuan' => 'Buah', 'harga_satuan' => 1, 'jumlah' => 1,
                    ]],
                ],
            ], null);
            $this->fail('Import yang gagal di tengah harus melempar duplikat.');
        } catch (DuplicateFakturException $e) {
            $this->assertSame('INV-LAMA', $e->nomorFaktur());
        }

        $this->assertSame(1, PersediaanPembelian::count());
        $this->assertNull(PersediaanPembelian::where('nomor_faktur', 'INV-BARU')->first());
        $this->assertSame(0, PersediaanStockMovement::count());
        $this->assertSame(10, app(StockService::class)->stokAkhir($barang->fresh()));
    }

    public function test_export_dan_template_berasal_dari_data_nyata(): void
    {
        $this->loginAdmin();
        $barang = $this->makeBarang('Pulpen', 'ATK001', 0);
        app(PurchaseService::class)->store([
            'nomor_faktur' => 'INV-EKSPOR',
            'tanggal'      => '2026-08-06',
            'penyedia'     => 'PT ABC',
            'unit_kerja'   => UnitKerja::KEPERAWATAN,
        ], [
            [
                'barang_id' => $barang->id, 'kode_barang' => 'ATK001', 'nama_barang' => 'Pulpen',
                'satuan' => 'Buah', 'harga_satuan' => 2500, 'jumlah' => 4,
            ],
            [
                'barang_id' => null, 'kode_barang' => 'MAN-9', 'nama_barang' => 'Kabel HDMI',
                'satuan' => 'Buah', 'harga_satuan' => 75000, 'jumlah' => 2,
            ],
        ], null);

        $response = app(PembelianController::class)->export(
            $this->adminRequest('GET', '/persediaan/pembelian/export?unit_kerja=' . urlencode(UnitKerja::KEPERAWATAN)),
        );
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('Pembelian_Persediaan_', (string) $response->headers->get('content-disposition'));

        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('No. Faktur', $sheet->getCell('B1')->getValue());
        $this->assertSame('INV-EKSPOR', $sheet->getCell('B2')->getValue());
        $this->assertSame('06/08/2026', $sheet->getCell('C2')->getValue());
        $this->assertSame('Pulpen', $sheet->getCell('G2')->getValue());
        $this->assertSame('Kabel HDMI', $sheet->getCell('G3')->getValue());
        $this->assertSame(10000.0, (float) $sheet->getCell('K2')->getValue());
        $this->assertSame(160000.0, (float) $sheet->getCell('L3')->getValue());
        $this->assertNotNull($sheet->getAutoFilter()->getRange());
        $this->assertSame('A2', $sheet->getFreezePane());

        $template = app(PembelianController::class)->importTemplate();
        $book = IOFactory::load($template->getFile()->getPathname());
        $this->assertSame('Data Pembelian', $book->getSheet(0)->getTitle());
        $this->assertSame('Petunjuk', $book->getSheet(1)->getTitle());
        $this->assertSame('no_faktur', $book->getSheet(0)->getCell('A1')->getValue());
        $this->assertNull($book->getSheet(0)->getCell('A2')->getValue());
        $this->assertStringContainsString('DD/MM/YYYY', (string) $book->getSheet(1)->getCell('A5')->getValue());

        $bag = new \Illuminate\Support\ViewErrorBag();
        $index = app(PembelianController::class)->index($this->adminRequest('GET', '/persediaan/pembelian'))
            ->with('errors', $bag)->render();
        $this->assertStringContainsString('Import Excel', $index);
        $this->assertStringContainsString('Download Template Excel', $index);
        $this->assertStringContainsString('Export Excel', $index);
        $this->assertStringContainsString('Input Pembelian', $index);

        $form = app(PembelianController::class)->create($this->adminRequest('GET', '/persediaan/pembelian/create'))
            ->with('errors', $bag)->render();
        $this->assertStringContainsString('Input Manual', $form);
        $this->assertStringContainsString('Pilih dari Master', $form);
        $this->assertStringContainsString('name="faktur"', $form);
        $this->assertStringContainsString('name="bast"', $form);
        $this->assertStringContainsString('name="invoice"', $form);
        $this->assertStringContainsString('name="foto_barang"', $form);
    }

    public function test_tanggal_permintaan_lampau_masa_depan_dan_created_at_terpisah(): void
    {
        $pemohon = $this->makePemohon();
        $barang = $this->makeBarang('Pulpen', 'ATK001', 8);

        $create = app(PermintaanController::class)
            ->create($this->pemohonRequest($pemohon, 'GET', '/public/persediaan/permintaan/create'))
            ->with('errors', new \Illuminate\Support\ViewErrorBag())
            ->render();
        $this->assertStringContainsString('Tanggal Permintaan', $create);
        $this->assertStringNotContainsString('after_or_equal', $create);
        preg_match('/<input[^>]*id="tanggalPermintaan"[^>]*>/', $create, $match);
        $this->assertNotEmpty($match);
        $this->assertStringNotContainsString('min=', $match[0]);
        $this->assertStringNotContainsString('max=', $match[0]);
        $this->assertStringContainsString('value="' . now()->format('Y-m-d') . '"', $match[0]);

        $cases = [
            '2026-10-01' => '2026-10-01',
            '2026-09-30' => '2026-09-30',
            '2026-09-15' => '2026-09-15',
            '2025-10-01' => '2025-10-01',
            '2024-06-15' => '2024-06-15',
            '01/10/2025' => '2025-10-01',
            '06/08/2026' => '2026-08-06',
            '2030-01-15' => '2030-01-15',
        ];

        foreach ($cases as $input => $expected) {
            $stored = app(PermintaanController::class)->store(
                $this->pemohonRequest($pemohon, 'POST', '/public/persediaan/permintaan', $this->permintaanPayload($barang, (string) $input)),
            );
            $this->assertTrue($stored->isRedirect());
            $row = PersediaanPermintaan::query()->latest('id')->first();
            $this->assertSame($expected, $row->tanggal_permintaan->format('Y-m-d'), 'Input ' . $input);
            $this->assertSame(PersediaanPermintaan::STATUS_DRAFT, $row->status);
            $this->assertSame(now()->format('Y-m-d'), $row->created_at->format('Y-m-d'));
            if ($expected !== now()->format('Y-m-d')) {
                $this->assertNotSame($row->created_at->format('Y-m-d'), $row->tanggal_permintaan->format('Y-m-d'));
            }
            $this->assertStringContainsString('-' . date('Y') . '-', $row->nomor_permintaan);
        }

        $past = PersediaanPermintaan::query()->whereDate('tanggal_permintaan', '2024-06-15')->first();
        $this->assertNotNull($past);
        $this->assertSame(now()->format('Y-m-d'), $past->created_at->format('Y-m-d'));

        $empty = $this->pemohonRequest($pemohon, 'POST', '/public/persediaan/permintaan', $this->permintaanPayload($barang, ''));
        try {
            app(PermintaanController::class)->store($empty);
            $this->fail('Tanggal kosong harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame('Tanggal permintaan wajib diisi.', $e->errors()['tanggal_permintaan'][0]);
        }

        $invalid = $this->pemohonRequest($pemohon, 'POST', '/public/persediaan/permintaan', $this->permintaanPayload($barang, '31/02/2026'));
        try {
            app(PermintaanController::class)->store($invalid);
            $this->fail('Tanggal tidak valid harus ditolak.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame('Tanggal permintaan tidak valid.', $e->errors()['tanggal_permintaan'][0]);
        }

        $detail = app(PermintaanController::class)->show($past->id)
            ->with('errors', new \Illuminate\Support\ViewErrorBag())
            ->render();
        $this->assertStringContainsString('Tanggal Permintaan', $detail);
        $this->assertStringContainsString('15 Juni 2024', $detail);
    }

    private function permintaanPayload(PersediaanBarang $barang, string $tanggal): array
    {
        return [
            'tanggal_permintaan' => $tanggal,
            'keterangan'         => 'Uji tanggal',
            'action'             => 'draft',
            'items'              => [
                $barang->id => ['barang_id' => $barang->id, 'jumlah_diminta' => 1],
            ],
        ];
    }

    private function makeBarang(string $nama, string $kode, int $stokAwal): PersediaanBarang
    {
        return PersediaanBarang::create([
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'satuan'       => 'Buah',
            'unit_kerja'   => UnitKerja::KEPERAWATAN,
            'stok_awal'    => $stokAwal,
            'stok_minimum' => 0,
            'harga_satuan' => 2500,
            'is_active'    => true,
        ]);
    }

    private function makePemohon(): PersediaanPemohon
    {
        return PersediaanPemohon::create([
            'nama'       => 'Pemohon Uji',
            'jabatan'    => 'Staf',
            'unit_kerja' => UnitKerja::KEPERAWATAN,
            'password'   => Hash::make('secret'),
        ]);
    }

    private function loginAdmin(): User
    {
        $user = User::create([
            'name'     => 'Admin Persediaan',
            'email'    => 'admin-prs-' . uniqid('', true) . '@example.test',
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

        return $user;
    }

    /**
     * @param  array<string,mixed>  $params
     * @param  array<string,UploadedFile>  $files
     */
    private function adminRequest(string $method, string $uri, array $params = [], array $files = []): Request
    {
        $request = Request::create($uri, $method, $params, [], $files);
        $session = app('session')->driver();
        if (! $session->isStarted()) {
            $session->start();
        }
        foreach ($this->adminSession as $key => $value) {
            $session->put($key, $value);
        }
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return $request;
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private function pemohonRequest(PersediaanPemohon $pemohon, string $method, string $uri, array $params = []): Request
    {
        Auth::guard('persediaan')->login($pemohon);
        $request = Request::create($uri, $method, $params);
        $session = app('session')->driver();
        if (! $session->isStarted()) {
            $session->start();
        }
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return $request;
    }

    private function assertRedirectsTo($response, string $route): void
    {
        $this->assertTrue($response->isRedirect());
        $this->assertSame(route($route), $response->getTargetUrl());
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function workbook(array $rows): UploadedFile
    {
        $path = $this->writeSheet($rows);

        return new UploadedFile(
            $path,
            'pembelian.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function writeSheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'pbx');
        $target = $path . '.xlsx';
        @unlink($path);
        (new Xlsx($spreadsheet))->save($target);

        return $target;
    }
}

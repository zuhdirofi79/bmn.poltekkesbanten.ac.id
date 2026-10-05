<?php

declare(strict_types=1);

namespace Tests\Feature\Persediaan;

use App\Http\Controllers\PersediaanAdmin\MasterBarangController;
use App\Models\Persediaan\PersediaanAdmin;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanRak;
use App\Models\Persediaan\PersediaanRakBarang;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\UnitKerja;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class MasterBarangExportPageTest extends TestCase
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
        Schema::dropAllTables();
        $this->createSchema();
        $this->loginAdmin();
    }

    public function test_per_page_whitelist_dan_pagination_mengikuti_filter(): void
    {
        $this->seedBarang(21, UnitKerja::DIREKTORAT, 'DIR');
        $this->seedBarang(4, UnitKerja::TLM, 'TLM');

        $default = $this->index();
        $this->assertSame(20, $default->perPage());
        $this->assertSame(20, $default->count());
        $this->assertSame(25, $default->total());
        $this->assertSame(1, $default->firstItem());
        $this->assertSame(20, $default->lastItem());

        $hundred = $this->index(['per_page' => 100]);
        $this->assertSame(100, $hundred->perPage());
        $this->assertSame(25, $hundred->count());
        $this->assertSame(25, $hundred->lastItem());

        $this->assertSame(500, $this->index(['per_page' => 500])->perPage());
        $this->assertSame(25, $this->index(['per_page' => 500])->lastItem());
        $this->assertSame(1000, $this->index(['per_page' => 1000])->perPage());
        $this->assertSame(25, $this->index(['per_page' => 1000])->lastItem());
        $this->assertSame(20, $this->index(['per_page' => 999999])->perPage());
        $this->assertSame(20, $this->index(['per_page' => 'abc'])->perPage());
        $this->assertSame(20, $this->index(['per_page' => -1])->perPage());
        $this->assertSame(20, $this->index(['per_page' => 0])->perPage());

        $tlm = $this->index(['unit' => UnitKerja::TLM, 'per_page' => 100, 'q' => 'glove']);
        $this->assertSame(1, $tlm->total());
        $this->assertSame(UnitKerja::TLM, $tlm->first()->unit_kerja);
        $this->assertStringContainsString('Glove', $tlm->first()->nama_barang);

        $direktorat = $this->index(['unit' => UnitKerja::DIREKTORAT, 'per_page' => 20]);
        $this->assertSame(21, $direktorat->total());
        $this->assertSame(20, $direktorat->count());
        $pageTwo = $direktorat->url(2);
        $this->assertStringContainsString('unit=' . urlencode(UnitKerja::DIREKTORAT), $pageTwo);
        $this->assertStringContainsString('per_page=20', $pageTwo);
        $this->assertStringContainsString('page=2', $pageTwo);
    }

    public function test_export_excel_seluruh_hasil_filter_bukan_halaman_aktif(): void
    {
        $this->seedBarang(21, UnitKerja::DIREKTORAT, 'DIR');
        $first = PersediaanBarang::query()->where('unit_kerja', UnitKerja::DIREKTORAT)->orderBy('id')->first();
        $first->kode_barang = '000812';
        $first->save();
        $rak = PersediaanRak::create([
            'kode_rak'   => 'A01',
            'nama_rak'   => 'ATK',
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'qr_token'   => str_repeat('a', 32),
            'status'     => PersediaanRak::STATUS_AKTIF,
        ]);
        PersediaanRakBarang::create([
            'rak_id'      => $rak->id,
            'barang_id'   => $first->id,
            'assigned_at' => now(),
        ]);
        $this->seedBarang(4, UnitKerja::TLM, 'TLM');

        $response = app(MasterBarangController::class)->exportExcel(
            $this->request('GET', '/persediaan/master-barang/export?per_page=20'),
        );
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('Master_Barang_Persediaan_', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Kode Barang', $sheet->getCell('B1')->getValue());
        $this->assertSame(26, $sheet->getHighestDataRow());
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertNotNull($sheet->getAutoFilter()->getRange());

        $kodeCell = null;
        for ($row = 2; $row <= 26; $row++) {
            if ($sheet->getCell('B' . $row)->getValue() === '000812') {
                $kodeCell = $sheet->getCell('B' . $row);
                $this->assertSame('A01', $sheet->getCell('I' . $row)->getValue());
            }
        }
        $this->assertNotNull($kodeCell);
        $this->assertSame(DataType::TYPE_STRING, $kodeCell->getDataType());

        $direktorat = app(MasterBarangController::class)->exportExcel(
            $this->request('GET', '/persediaan/master-barang/export?unit=' . urlencode(UnitKerja::DIREKTORAT) . '&per_page=20'),
        );
        $dirSheet = IOFactory::load($direktorat->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(22, $dirSheet->getHighestDataRow());
        for ($row = 2; $row <= 22; $row++) {
            $this->assertSame(UnitKerja::DIREKTORAT, $dirSheet->getCell('D' . $row)->getValue());
        }

        $empty = app(MasterBarangController::class)->exportExcel(
            $this->request('GET', '/persediaan/master-barang/export?q=tidak-ada-barang-ini'),
        );
        $this->assertTrue($empty->isRedirect());
        $this->assertSame('Tidak ada data untuk diekspor.', $empty->getSession()->get('error'));
    }

    public function test_pagination_view_memakai_ikon_kecil_dan_menyimpan_filter(): void
    {
        $this->seedBarang(21, UnitKerja::DIREKTORAT, 'DIR');

        $page = $this->index(['unit' => UnitKerja::DIREKTORAT, 'per_page' => 20]);
        $html = $page->links('persediaan_admin.master-barang.pagination')->toHtml();

        $this->assertStringNotContainsString('w-5 h-5', $html);
        $this->assertStringContainsString('width="16"', $html);
        $this->assertStringContainsString('height="16"', $html);
        $this->assertStringContainsString('class="master-barang-pagination"', $html);
        $this->assertStringContainsString('Previous', $html);
        $this->assertStringContainsString('Next', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('unit=' . urlencode(UnitKerja::DIREKTORAT), $html);
        $this->assertStringContainsString('per_page=20', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('.master-barang-pagination svg', $html);
    }

    private function index(array $query = [])
    {
        $qs = $query === [] ? '' : '?' . http_build_query($query);
        $view = app(MasterBarangController::class)->index($this->request('GET', '/persediaan/master-barang' . $qs));

        return $view->getData()['barang'];
    }

    private function request(string $method, string $uri): Request
    {
        $request = Request::create($uri, $method);
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

    private function loginAdmin(): void
    {
        $user = User::create([
            'name'     => 'Admin Persediaan',
            'email'    => 'admin-master-' . uniqid('', true) . '@example.test',
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

    private function seedBarang(int $count, string $unit, string $prefix): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $nama = $prefix === 'TLM' && $i === 1 ? 'Glove Nitril' : $prefix . ' Barang ' . $i;
            PersediaanBarang::create([
                'kode_barang'  => $prefix . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'nama_barang'  => $nama,
                'satuan'       => 'Buah',
                'unit_kerja'   => $unit,
                'stok_awal'    => $i,
                'stok_minimum' => 0,
                'harga_satuan' => 1500,
                'is_active'    => true,
            ]);
        }
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
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('persediaan_rak', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_rak');
            $table->string('nama_rak');
            $table->string('unit_kerja');
            $table->string('qr_token');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('persediaan_rak_barang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rak_id');
            $table->unsignedBigInteger('barang_id');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Persediaan;

use App\Models\Persediaan\PersediaanAdmin;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPemohon;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanQrAkses;
use App\Models\Persediaan\PersediaanRak;
use App\Models\Persediaan\PersediaanRakBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\PersediaanCodeService;
use App\Services\Persediaan\PersediaanRackService;
use App\Services\Persediaan\PersediaanRequestService;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\UnitKerja;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersediaanQrRakBarcodeTest extends TestCase
{
    use RefreshDatabase;

    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true) ?: 'PNGFAKEIMAGECONTENTXXXXXXXXXXXXXXXX';
        Http::fake([
            'api.qrserver.com/*' => Http::response($this->png, 200),
        ]);
        config([
            'persediaan.uploads_path' => storage_path('app/testing-persediaan-uploads'),
            'persediaan.uploads_url'  => 'https://bmn.poltekkesbanten.ac.id/uploads',
        ]);
        @mkdir(storage_path('app/testing-persediaan-uploads/persediaan/qr/akses'), 0777, true);
        @mkdir(storage_path('app/testing-persediaan-uploads/persediaan/qr/rak'), 0777, true);
        @mkdir(storage_path('app/testing-persediaan-uploads/persediaan/barcode/barang'), 0777, true);
    }

    public function test_qr_pintu_valid_user_login_menampilkan_identitas(): void
    {
        $pemohon = $this->makePemohon(UnitKerja::DIREKTORAT);
        $akses = $this->makeAkses();

        $this->actingAs($pemohon, 'persediaan')
            ->get(route('public.persediaan.akses.show', $akses->token))
            ->assertOk()
            ->assertSee($pemohon->nama)
            ->assertSee($pemohon->jabatan)
            ->assertSee('Direktorat')
            ->assertSee('Scan QR Rak');
    }

    public function test_qr_pintu_belum_login_kembali_ke_intended(): void
    {
        $pemohon = $this->makePemohon(UnitKerja::DIREKTORAT, 'Nirmala Test', 'secret123');
        $akses = $this->makeAkses();

        $this->get(route('public.persediaan.akses.show', $akses->token))
            ->assertRedirect(route('public.persediaan.login', 'direktorat'));

        $this->post(route('public.persediaan.login.attempt', 'direktorat'), [
            'login'    => $pemohon->nama,
            'password' => 'secret123',
        ])->assertRedirect(route('public.persediaan.akses.show', $akses->token));
    }

    public function test_qr_rak_direktorat_menampilkan_barang(): void
    {
        $pemohon = $this->makePemohon(UnitKerja::DIREKTORAT);
        $rak = $this->makeRak();
        $pulpen = $this->makeBarang('Pulpen', 'ATK001', UnitKerja::DIREKTORAT, 100);
        $this->assign($rak, $pulpen);

        $this->actingAs($pemohon, 'persediaan')
            ->get(route('public.persediaan.rak.show', $rak->qr_token))
            ->assertOk()
            ->assertSee('A01')
            ->assertSee('Pulpen')
            ->assertSee('100');
    }

    public function test_qr_rak_unit_non_direktorat_ditolak(): void
    {
        $pemohon = $this->makePemohon(UnitKerja::KEPERAWATAN);
        $rak = PersediaanRak::create([
            'kode_rak'   => 'K01',
            'nama_rak'   => 'Rak Kep',
            'unit_kerja' => UnitKerja::KEPERAWATAN,
            'qr_token'   => Str::lower(Str::random(40)),
            'status'     => PersediaanRak::STATUS_AKTIF,
        ]);

        $this->actingAs($pemohon, 'persediaan')
            ->get(route('public.persediaan.rak.show', $rak->qr_token))
            ->assertRedirect(route('public.persediaan.index'));
    }

    public function test_barang_unit_lain_tidak_boleh_masuk_rak_direktorat(): void
    {
        $rak = $this->makeRak();
        $kep = $this->makeBarang('Alkohol', 'ALK001', UnitKerja::KEPERAWATAN, 10);

        $this->expectException(\InvalidArgumentException::class);
        app(PersediaanRackService::class)->assignBarangs($rak, [(int) $kep->id], 1);
    }

    public function test_submit_multi_item_satu_permintaan_tiga_detail_tanpa_mengurangi_stok(): void
    {
        $pemohon = $this->makePemohon(UnitKerja::DIREKTORAT);
        $rak = $this->makeRak();
        $a = $this->makeBarang('Pulpen', 'ATK001', UnitKerja::DIREKTORAT, 100);
        $b = $this->makeBarang('Buku Agenda', 'ATK002', UnitKerja::DIREKTORAT, 20);
        $c = $this->makeBarang('Map Folder', 'ATK003', UnitKerja::DIREKTORAT, 50);
        $this->assign($rak, $a);
        $this->assign($rak, $b);
        $this->assign($rak, $c);

        $permintaan = app(PersediaanRequestService::class)->createFromRack(
            $pemohon,
            $rak,
            [(int) $a->id => 5, (int) $b->id => 2, (int) $c->id => 10],
            'idemp-test-1',
        );

        $this->assertSame(PersediaanPermintaan::STATUS_MENUNGGU, $permintaan->status);
        $this->assertSame('rack_qr', $permintaan->source);
        $this->assertSame(3, $permintaan->items()->count());
        $this->assertSame(1, PersediaanPermintaan::count());
        $this->assertSame(0, PersediaanStockMovement::where('tipe', PersediaanStockMovement::TIPE_KELUAR)->count());
        $stock = app(StockService::class);
        $this->assertSame(100, $stock->stokAkhir($a->fresh()));
        $this->assertSame(20, $stock->stokAkhir($b->fresh()));
        $this->assertSame(50, $stock->stokAkhir($c->fresh()));
    }

    public function test_approval_mengurangi_stok_tepat_sekali_termasuk_double_request(): void
    {
        $admin = $this->loginAdmin();
        $pemohon = $this->makePemohon(UnitKerja::DIREKTORAT);
        $rak = $this->makeRak();
        $barang = $this->makeBarang('Pulpen', 'ATK001', UnitKerja::DIREKTORAT, 100);
        $this->assign($rak, $barang);

        $permintaan = app(PersediaanRequestService::class)->createFromRack(
            $pemohon,
            $rak,
            [(int) $barang->id => 5],
            'idemp-approve-1',
        );

        $ttd = $this->signatureDataUrl();
        $this->post(route('persediaan.admin.permintaan.setujui', $permintaan->id), [
            'tanda_tangan_admin' => $ttd,
            'jumlah_disetujui'   => [$permintaan->items()->first()->id => 5],
        ])->assertRedirect();

        $stock = app(StockService::class);
        $this->assertSame(95, $stock->stokAkhir($barang->fresh()));
        $this->assertSame(1, PersediaanStockMovement::where('ref_id', $permintaan->id)->where('tipe', PersediaanStockMovement::TIPE_KELUAR)->count());

        $this->post(route('persediaan.admin.permintaan.setujui', $permintaan->id), [
            'tanda_tangan_admin' => $ttd,
            'jumlah_disetujui'   => [$permintaan->items()->first()->id => 5],
        ]);

        $this->assertSame(95, $stock->stokAkhir($barang->fresh()));
        $this->assertSame(1, PersediaanStockMovement::where('ref_id', $permintaan->id)->where('tipe', PersediaanStockMovement::TIPE_KELUAR)->count());
    }

    public function test_approval_stok_kurang_diblok_tidak_negatif(): void
    {
        $this->loginAdmin();
        $pemohon = $this->makePemohon(UnitKerja::DIREKTORAT);
        $rak = $this->makeRak();
        $barang = $this->makeBarang('Pulpen', 'ATK001', UnitKerja::DIREKTORAT, 10);
        $this->assign($rak, $barang);

        $permintaan = app(PersediaanRequestService::class)->createFromRack(
            $pemohon,
            $rak,
            [(int) $barang->id => 8],
            'idemp-less-1',
        );

        PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
            'qty_keluar' => 7,
            'keterangan' => 'keluar lain',
        ]);

        $this->post(route('persediaan.admin.permintaan.setujui', $permintaan->id), [
            'tanda_tangan_admin' => $this->signatureDataUrl(),
            'jumlah_disetujui'   => [$permintaan->items()->first()->id => 8],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(3, app(StockService::class)->stokAkhir($barang->fresh()));
        $this->assertSame(PersediaanPermintaan::STATUS_MENUNGGU, $permintaan->fresh()->status);
        $this->assertGreaterThanOrEqual(0, app(StockService::class)->stokAkhir($barang->fresh()));
    }

    public function test_regenerate_qr_rak_token_tetap_sama(): void
    {
        $rak = $this->makeRak();
        $token = $rak->qr_token;
        $service = app(PersediaanRackService::class);
        $service->generateRakQr($rak);
        $rak->refresh();
        $this->assertSame($token, $rak->qr_token);
        $service->generateRakQr($rak);
        $this->assertSame($token, $rak->fresh()->qr_token);
    }

    public function test_regenerate_code128_identifier_tetap_sama_dan_bisa_diulang(): void
    {
        $barang = $this->makeBarang('Wipol', 'KBR-01-004', UnitKerja::DIREKTORAT, 3);
        $codes = app(PersediaanCodeService::class);
        $codes->generateAndSave($barang);
        $payload = $barang->fresh()->barcode_payload;
        $this->assertSame('ID|' . $barang->id, $payload);

        $codes->generateAndSave($barang->fresh());
        $this->assertSame($payload, $barang->fresh()->barcode_payload);

        $this->loginAdmin();
        $this->post(route('persediaan.admin.master-barang.generate-barcodes'), ['scope' => 'all'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->post(route('persediaan.admin.master-barang.generate-barcodes'), ['scope' => 'all'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame($payload, $barang->fresh()->barcode_payload);
    }

    public function test_kode_barang_sama_antar_unit_scanner_tidak_salah_pilih(): void
    {
        $dir = $this->makeBarang('Pulpen Dir', 'ATK001', UnitKerja::DIREKTORAT, 10);
        $kep = $this->makeBarang('Pulpen Kep', 'ATK001', UnitKerja::KEPERAWATAN, 10);
        $codes = app(PersediaanCodeService::class);

        $this->assertSame((int) $dir->id, (int) $codes->resolve('DIR|ATK001')->id);
        $this->assertSame((int) $kep->id, (int) $codes->resolve('KEP|ATK001')->id);
        $this->assertNull($codes->resolve('ATK001'));
        $this->assertSame((int) $dir->id, (int) $codes->resolve('ATK001', UnitKerja::DIREKTORAT)->id);
    }

    public function test_print_barcode_memakai_layout_50mm_x_20mm(): void
    {
        $this->loginAdmin();
        $barang = $this->makeBarang('Map', 'ATK009', UnitKerja::DIREKTORAT, 1);
        app(PersediaanCodeService::class)->generateAndSave($barang);

        $this->get(route('persediaan.admin.master-barang.print-barcodes', ['ids' => [$barang->id]]))
            ->assertOk()
            ->assertSee('50mm')
            ->assertSee('20mm');
    }

    public function test_master_barang_index_existing_tetap_ada(): void
    {
        $this->loginAdmin();
        $this->makeBarang('Pulpen', 'ATK001', UnitKerja::DIREKTORAT, 5);

        $this->get(route('persediaan.admin.master-barang.index'))
            ->assertOk()
            ->assertSee('Master Barang')
            ->assertSee('Import Excel')
            ->assertSee('Pulpen');
    }

    public function test_public_permintaan_keperawatan_existing_tetap_berjalan(): void
    {
        $pemohon = $this->makePemohon(UnitKerja::KEPERAWATAN);
        $this->makeBarang('Alkohol', 'ALK001', UnitKerja::KEPERAWATAN, 12);

        $this->actingAs($pemohon, 'persediaan')
            ->get(route('public.persediaan.permintaan.create'))
            ->assertOk()
            ->assertSee('Alkohol');
    }

    public function test_pemohon_non_direktorat_tidak_melihat_barang_rak_direktorat(): void
    {
        $pemohon = $this->makePemohon(UnitKerja::KEPERAWATAN);
        $rak = $this->makeRak();
        $barang = $this->makeBarang('Pulpen', 'ATK001', UnitKerja::DIREKTORAT, 100);
        $this->assign($rak, $barang);

        $this->actingAs($pemohon, 'persediaan')
            ->get(route('public.persediaan.rak.show', $rak->qr_token))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_stok_akhir_tetap_dari_stock_movements(): void
    {
        $barang = $this->makeBarang('Pulpen', 'ATK010', UnitKerja::DIREKTORAT, 10);
        PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'tipe'       => PersediaanStockMovement::TIPE_PEMBELIAN,
            'qty_masuk'  => 4,
        ]);
        PersediaanStockMovement::create([
            'barang_id'  => $barang->id,
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
            'qty_keluar' => 3,
        ]);

        $this->assertSame(11, app(StockService::class)->stokAkhir($barang->fresh()));
    }

    public function test_idempotent_submit_tidak_duplikat_permintaan(): void
    {
        $pemohon = $this->makePemohon(UnitKerja::DIREKTORAT);
        $rak = $this->makeRak();
        $barang = $this->makeBarang('Pulpen', 'ATK001', UnitKerja::DIREKTORAT, 100);
        $this->assign($rak, $barang);
        $svc = app(PersediaanRequestService::class);

        $a = $svc->createFromRack($pemohon, $rak, [(int) $barang->id => 2], 'same-key');
        $b = $svc->createFromRack($pemohon, $rak, [(int) $barang->id => 2], 'same-key');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, PersediaanPermintaan::count());
    }

    private function makePemohon(string $unit, string $nama = 'Pemohon Uji', string $password = 'secret'): PersediaanPemohon
    {
        return PersediaanPemohon::create([
            'nama'       => $nama,
            'jabatan'    => 'Staf',
            'unit_kerja' => $unit,
            'password'   => Hash::make($password),
        ]);
    }

    private function makeBarang(string $nama, string $kode, string $unit, int $stokAwal): PersediaanBarang
    {
        return PersediaanBarang::create([
            'kode_barang'  => $kode,
            'nama_barang'  => $nama,
            'satuan'       => 'Buah',
            'unit_kerja'   => $unit,
            'stok_awal'    => $stokAwal,
            'stok_minimum' => 0,
            'harga_satuan' => 0,
            'is_active'    => true,
        ]);
    }

    private function makeRak(): PersediaanRak
    {
        return PersediaanRak::create([
            'kode_rak'   => 'A01',
            'nama_rak'   => 'ATK',
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'qr_token'   => Str::lower(Str::random(40)),
            'status'     => PersediaanRak::STATUS_AKTIF,
        ]);
    }

    private function makeAkses(): PersediaanQrAkses
    {
        return PersediaanQrAkses::create([
            'unit_kerja' => UnitKerja::DIREKTORAT,
            'nama'       => 'QR Pintu Direktorat',
            'token'      => Str::lower(Str::random(40)),
            'is_active'  => true,
        ]);
    }

    private function assign(PersediaanRak $rak, PersediaanBarang $barang): void
    {
        PersediaanRakBarang::create([
            'rak_id'      => $rak->id,
            'barang_id'   => $barang->id,
            'assigned_at' => now(),
        ]);
    }

    private function loginAdmin(): User
    {
        $user = User::create([
            'name'     => 'Admin Persediaan',
            'email'    => 'admin-prs-' . uniqid('', true) . '@example.test',
            'password' => Hash::make('secret'),
        ]);

        config([
            'persediaan.superadmin_user_ids' => [(int) $user->id],
            'persediaan.approvers.Direktorat.user_id' => (int) $user->id,
        ]);

        PersediaanAdmin::create([
            'user_id'   => $user->id,
            'role'      => PersediaanAdmin::ROLE_SUPERADMIN,
            'is_active' => true,
        ]);

        $this->withSession([
            PersediaanAdminSession::KEY_USER_ID => (int) $user->id,
            PersediaanAdminSession::KEY_ROLE    => PersediaanAdmin::ROLE_SUPERADMIN,
        ]);

        return $user;
    }

    private function signatureDataUrl(): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png . str_repeat('A', 80));
    }
}

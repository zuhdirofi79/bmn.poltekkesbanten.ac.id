<?php

declare(strict_types=1);

namespace Tests\Feature\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanAdmin;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Models\BhpKebersihan\BhpKebersihanPermintaan;
use App\Models\BhpKebersihan\BhpKebersihanStockMovement;
use App\Models\BhpKebersihan\BhpKebersihanStockOpname;
use App\Models\BhpKebersihan\BhpKebersihanStockOpnameDetail;
use App\Services\BhpKebersihan\StockService;
use App\Support\BhpKebersihan\BhpAdminSession;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BhpKebersihanStockTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->stock = $this->app->make(StockService::class);
    }

    public function test_stok_awal_20_tidak_menjadi_40(): void
    {
        $barang = $this->makeBarang(20, 'TEST STOCK 20');

        $this->assertSame(20, $this->stock->stokSaatIni($barang));
        $this->assertSame(0, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
    }

    public function test_stok_awal_1_tidak_menjadi_2(): void
    {
        $barang = $this->makeBarang(1, 'TEST STOCK 1');

        $this->assertSame(1, $this->stock->stokSaatIni($barang));
        $this->assertSame(0, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
    }

    public function test_legacy_opening_movement_tidak_dihitung_dua_kali(): void
    {
        $barang = $this->makeBarang(1, 'hit');

        BhpKebersihanStockMovement::create([
            'barang_id'    => $barang->id,
            'tipe'         => BhpKebersihanStockMovement::TIPE_MASUK,
            'sumber'       => BhpKebersihanStockMovement::SUMBER_MANUAL,
            'ref_id'       => $barang->id,
            'jumlah'       => 1,
            'stok_sebelum' => 1,
            'stok_sesudah' => 2,
            'catatan'      => BhpKebersihanStockMovement::CATATAN_STOK_AWAL,
        ]);

        $this->assertSame(1, $this->stock->stokSaatIni($barang->fresh()));
    }

    public function test_permintaan_disetujui_tidak_mengurangi_stok(): void
    {
        $barang = $this->makeBarang(20);
        $permintaan = $this->makePermintaan($barang, 5, BhpKebersihanPermintaan::STATUS_SIAP_DIAMBIL);

        $this->assertSame(20, $this->stock->stokSaatIni($barang));
        $this->assertSame(BhpKebersihanPermintaan::STATUS_SIAP_DIAMBIL, $permintaan->status);
        $this->assertSame(0, BhpKebersihanStockMovement::where('tipe', BhpKebersihanStockMovement::TIPE_KELUAR)->count());
    }

    public function test_pengambilan_mengurangi_stok_sekali(): void
    {
        $barang = $this->makeBarang(20);
        $permintaan = $this->makePermintaan($barang, 5, BhpKebersihanPermintaan::STATUS_SIAP_DIAMBIL);

        $this->stock->recordKeluar(
            $barang,
            5,
            BhpKebersihanStockMovement::SUMBER_PERMINTAAN,
            $permintaan->id,
            'Pengambilan permintaan ' . $permintaan->nomor_permintaan,
        );

        $this->assertSame(15, $this->stock->stokSaatIni($barang->fresh()));
        $this->assertSame(1, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());

        $again = $this->stock->recordKeluar(
            $barang->fresh(),
            5,
            BhpKebersihanStockMovement::SUMBER_PERMINTAAN,
            $permintaan->id,
            'Pengambilan permintaan ' . $permintaan->nomor_permintaan,
        );

        $this->assertSame(15, $this->stock->stokSaatIni($barang->fresh()));
        $this->assertSame(1, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
        $this->assertSame(5, $again->jumlah);
    }

    public function test_pengambilan_lanjutan_dan_opname(): void
    {
        $barang = $this->makeBarang(20);
        $this->stock->recordKeluar($barang, 5, BhpKebersihanStockMovement::SUMBER_PERMINTAAN, 11);
        $this->assertSame(15, $this->stock->stokSaatIni($barang->fresh()));

        $this->stock->recordKeluar($barang->fresh(), 3, BhpKebersihanStockMovement::SUMBER_PERMINTAAN, 12);
        $this->assertSame(12, $this->stock->stokSaatIni($barang->fresh()));

        $this->stock->recordKoreksiOpname($barang->fresh(), 10, 99, 'selisih -2');
        $this->assertSame(10, $this->stock->stokSaatIni($barang->fresh()));

        $koreksiCount = BhpKebersihanStockMovement::query()
            ->where('barang_id', $barang->id)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_KOREKSI)
            ->count();
        $this->assertSame(1, $koreksiCount);

        $this->stock->recordKoreksiOpname($barang->fresh(), 10, 99, 'ulang');
        $this->assertSame(1, BhpKebersihanStockMovement::query()
            ->where('barang_id', $barang->id)
            ->where('tipe', BhpKebersihanStockMovement::TIPE_KOREKSI)
            ->count());
        $this->assertSame(10, $this->stock->stokSaatIni($barang->fresh()));
    }

    public function test_keluar_melebihi_stok_ditolak(): void
    {
        $barang = $this->makeBarang(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Jumlah pengambilan melebihi stok tersedia.');

        $this->stock->recordKeluar($barang, 3, BhpKebersihanStockMovement::SUMBER_PERMINTAAN, 1);
    }

    public function test_public_form_menolak_jumlah_lebih_dari_stok(): void
    {
        $barang = $this->makeBarang(2, 'hit');

        $response = $this->from(route('public.bhp-kebersihan.barang.show', $barang->barcode_token))
            ->post(route('public.bhp-kebersihan.barang.store', $barang->barcode_token), [
                'nama_pengambil'  => 'Budi',
                'no_wa_pengambil' => '08123456789',
                'jumlah_diminta'  => 3,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('jumlah_diminta');
        $this->assertSame(
            'Jumlah pengambilan melebihi stok tersedia.',
            session('errors')->first('jumlah_diminta')
        );
        $this->assertSame(0, BhpKebersihanPermintaan::count());
        $this->assertSame(2, $this->stock->stokSaatIni($barang->fresh()));
    }

    public function test_public_page_menampilkan_stok_realtime_merk_tanpa_cache_header_stale(): void
    {
        $barang = $this->makeBarang(2, 'hit');
        $barang->merk = 'Contoh Merk';
        $barang->save();

        $response = $this->get(route('public.bhp-kebersihan.barang.show', $barang->barcode_token));

        $response->assertOk();
        $response->assertSee('hit', false);
        $response->assertSee('Contoh Merk', false);
        $response->assertSee('2', false);
        $response->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-store', strtolower($response->headers->get('Cache-Control')));
    }

    public function test_admin_store_tidak_membuat_movement_stok_awal(): void
    {
        $this->loginBhpAdmin();

        $response = $this->post(route('bhp.admin.master-barang.store'), [
            'kode_barang' => '3.05.02.01.012.KD',
            'nama_barang' => 'hit',
            'merk'        => 'Contoh Merk',
            'satuan'      => 'pcs',
            'stok_awal'   => 1,
        ]);

        $response->assertRedirect();
        $barang = BhpKebersihanBarang::where('kode_barang', '3.05.02.01.012.KD')->first();
        $this->assertNotNull($barang);
        $this->assertSame('Contoh Merk', $barang->merk);
        $this->assertSame(1, (int) $barang->stok_awal);
        $this->assertSame(1, $this->stock->stokSaatIni($barang));
        $this->assertSame(0, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
    }

    public function test_koreksi_manual_tambah_tidak_mengubah_stok_awal(): void
    {
        $barang = $this->makeBarang(1, 'hit');
        $user = $this->loginBhpAdmin();

        $movement = $this->stock->recordKoreksiManual(
            $barang,
            'tambah',
            2,
            'Penyesuaian stok hasil pengecekan fisik.',
            (int) $user->id,
        );

        $this->assertSame(1, (int) $barang->fresh()->stok_awal);
        $this->assertSame(3, $this->stock->stokSaatIni($barang->fresh()));
        $this->assertSame(1, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
        $this->assertSame(BhpKebersihanStockMovement::TIPE_KOREKSI, $movement->tipe);
        $this->assertSame(BhpKebersihanStockMovement::SUMBER_MANUAL, $movement->sumber);
        $this->assertSame(1, $movement->stok_sebelum);
        $this->assertSame(3, $movement->stok_sesudah);
        $this->assertSame(2, $movement->jumlah);
        $this->assertSame((int) $user->id, (int) $movement->created_by);
    }

    public function test_koreksi_manual_kurangi_sekali(): void
    {
        $barang = $this->makeBarang(3, 'hit');

        $this->stock->recordKoreksiManual($barang, 'kurangi', 1, 'Koreksi administrasi.', 1);

        $this->assertSame(3, (int) $barang->fresh()->stok_awal);
        $this->assertSame(2, $this->stock->stokSaatIni($barang->fresh()));
        $this->assertSame(1, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
    }

    public function test_koreksi_manual_tidak_boleh_negatif(): void
    {
        $barang = $this->makeBarang(2, 'hit');

        try {
            $this->stock->recordKoreksiManual($barang, 'kurangi', 5, 'Tidak boleh negatif.', 1);
            $this->fail('Seharusnya ditolak karena stok tidak mencukupi.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Stok tidak mencukupi. Stok tersedia saat ini: 2.', $e->getMessage());
        }

        $this->assertSame(2, $this->stock->stokSaatIni($barang->fresh()));
        $this->assertSame(0, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
    }

    public function test_koreksi_manual_idempotent_satu_movement(): void
    {
        $barang = $this->makeBarang(1, 'hit');
        $key = 'koreksi-test-' . $barang->id;

        $first = $this->stock->recordKoreksiManual($barang, 'tambah', 2, 'Stok awal salah input.', 1, $key);
        $second = $this->stock->recordKoreksiManual($barang, 'tambah', 2, 'Stok awal salah input.', 1, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(3, $this->stock->stokSaatIni($barang->fresh()));
        $this->assertSame(1, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
    }

    public function test_endpoint_koreksi_stok_tambah(): void
    {
        $this->loginBhpAdmin();
        $barang = $this->makeBarang(1, 'hit');

        $this->from(route('bhp.admin.master-barang.index'))
            ->post(route('bhp.admin.master-barang.koreksi-stok', $barang->id), [
                'arah'            => 'tambah',
                'jumlah'          => 2,
                'catatan'         => 'Penyesuaian stok hasil pengecekan fisik.',
                'idempotency_key' => 'http-koreksi-' . $barang->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, (int) $barang->fresh()->stok_awal);
        $this->assertSame(3, $this->stock->stokSaatIni($barang->fresh()));
        $this->assertSame(1, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
    }

    public function test_hapus_barang_tanpa_histori_hard_delete(): void
    {
        $this->loginBhpAdmin();
        $barang = $this->makeBarang(0, 'Barang Baru');

        $this->from(route('bhp.admin.master-barang.index'))
            ->delete(route('bhp.admin.master-barang.destroy', $barang->id))
            ->assertRedirect()
            ->assertSessionHas('success', 'Barang berhasil dihapus permanen.');

        $this->assertNull(BhpKebersihanBarang::find($barang->id));
    }

    public function test_hapus_barang_dengan_movement_ditolak(): void
    {
        $this->loginBhpAdmin();
        $barang = $this->makeBarang(1, 'hit');
        $this->stock->recordKoreksiManual($barang, 'tambah', 1, 'Koreksi administrasi.', 1);

        $this->from(route('bhp.admin.master-barang.index'))
            ->delete(route('bhp.admin.master-barang.destroy', $barang->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $fresh = BhpKebersihanBarang::find($barang->id);
        $this->assertNotNull($fresh);
        $this->assertSame('aktif', $fresh->status);
        $this->assertSame(1, BhpKebersihanStockMovement::where('barang_id', $barang->id)->count());
    }

    public function test_hapus_barang_dengan_permintaan_ditolak(): void
    {
        $this->loginBhpAdmin();
        $barang = $this->makeBarang(5, 'Floor Cleaner');
        $this->makePermintaan($barang, 1, BhpKebersihanPermintaan::STATUS_SIAP_DIAMBIL);

        $this->from(route('bhp.admin.master-barang.index'))
            ->delete(route('bhp.admin.master-barang.destroy', $barang->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull(BhpKebersihanBarang::find($barang->id));
        $this->assertSame('aktif', BhpKebersihanBarang::find($barang->id)->status);
    }

    public function test_hapus_barang_dengan_opname_ditolak(): void
    {
        $this->loginBhpAdmin();
        $barang = $this->makeBarang(5, 'Wipol');
        $opname = BhpKebersihanStockOpname::create([
            'tanggal_opname' => now()->toDateString(),
            'catatan'        => 'Test opname',
        ]);
        BhpKebersihanStockOpnameDetail::create([
            'opname_id'   => $opname->id,
            'barang_id'   => $barang->id,
            'stok_sistem' => 5,
            'stok_fisik'  => 5,
            'selisih'     => 0,
        ]);

        $this->from(route('bhp.admin.master-barang.index'))
            ->delete(route('bhp.admin.master-barang.destroy', $barang->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull(BhpKebersihanBarang::find($barang->id));
        $this->assertSame('aktif', BhpKebersihanBarang::find($barang->id)->status);
    }

    public function test_bulk_delete_campuran_histori(): void
    {
        $this->loginBhpAdmin();
        $baru = $this->makeBarang(0, 'Barang Kosong');
        $lama = $this->makeBarang(5, 'Floor Cleaner');
        $this->stock->recordKoreksiManual($lama, 'tambah', 1, 'Penyesuaian stok hasil pengecekan fisik.', 1);

        $this->post(route('bhp.admin.master-barang.bulk-destroy'), [
            'ids' => [$baru->id, $lama->id],
        ])->assertRedirect()
            ->assertSessionHas('bulk_delete_summary');

        $this->assertNull(BhpKebersihanBarang::find($baru->id));
        $this->assertNotNull(BhpKebersihanBarang::find($lama->id));
        $this->assertSame('aktif', BhpKebersihanBarang::find($lama->id)->status);
        $this->assertSame(1, BhpKebersihanStockMovement::where('barang_id', $lama->id)->count());
    }

    public function test_hapus_barang_tanpa_login_ditolak(): void
    {
        $barang = $this->makeBarang(0, 'Tanpa Login');

        $this->delete(route('bhp.admin.master-barang.destroy', $barang->id))
            ->assertRedirect(route('bhp.admin.login.show'));

        $this->assertNotNull(BhpKebersihanBarang::find($barang->id));
    }

    private function makeBarang(int $stokAwal, string $nama = 'Barang Test'): BhpKebersihanBarang
    {
        return BhpKebersihanBarang::create([
            'kode_barang'   => 'BHP-' . Str::lower(Str::random(8)),
            'nama_barang'   => $nama,
            'satuan'        => 'pcs',
            'stok_awal'     => $stokAwal,
            'barcode_token' => Str::lower(Str::random(32)),
            'status'        => 'aktif',
        ]);
    }

    private function makePermintaan(BhpKebersihanBarang $barang, int $jumlah, string $status): BhpKebersihanPermintaan
    {
        return BhpKebersihanPermintaan::create([
            'nomor_permintaan'    => 'BHP-2026-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'barang_id'           => $barang->id,
            'nama_pengambil'      => 'Tester',
            'no_wa_pengambil'     => '08123456789',
            'tanggal_pengambilan' => now()->toDateString(),
            'jumlah_diminta'      => $jumlah,
            'status'              => $status,
        ]);
    }

    private function loginBhpAdmin(): User
    {
        $user = User::create([
            'name'     => 'Admin BHP',
            'email'    => 'adminbhp-' . uniqid('', true) . '@example.test',
            'password' => Hash::make('secret'),
        ]);

        BhpKebersihanAdmin::create([
            'user_id'   => $user->id,
            'role'      => BhpKebersihanAdmin::ROLE_SUPERADMIN,
            'is_active' => true,
        ]);

        $this->withSession([
            BhpAdminSession::KEY_USER_ID => (int) $user->id,
            BhpAdminSession::KEY_ROLE    => BhpKebersihanAdmin::ROLE_SUPERADMIN,
        ]);

        return $user;
    }
}

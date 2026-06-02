<?php

declare(strict_types=1);

namespace Tests\Feature\BMN;

use App\Models\BMN\AsetBmn;
use App\Models\BMN\KategoriAset;
use App\Models\BMN\PeminjamanAsetBmn;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BmnPeminjamanCreateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_create_page_shows_kategori_filter_before_search(): void
    {
        $user = $this->makeRequesterUser();

        $response = $this->actingAs($user)->get(route('bmn.peminjaman-aset.create'));

        $response->assertOk();
        $response->assertSee('Kategori Aset BMN', false);
        $response->assertSee('-- Pilih Kategori Aset Terlebih Dahulu --', false);
        $response->assertSee('Cari aset dalam kategori terpilih...', false);
        $response->assertSee('Disarankan memilih kategori terlebih dahulu agar hasil lebih tepat.', false);

        $html = $response->getContent();
        $kategoriPos = strpos($html, 'name="kategori_id"');
        $searchPos = strpos($html, 'name="q"');
        $this->assertNotFalse($kategoriPos);
        $this->assertNotFalse($searchPos);
        $this->assertLessThan($searchPos, $kategoriPos);
    }

    public function test_filter_by_kategori_shows_only_matching_assets(): void
    {
        $user = $this->makeRequesterUser();
        $kategoriMesin = $this->makeKategori('MESIN PERALATAN NON TIK');
        $kategoriLain = $this->makeKategori('KIB A');

        $asetMesin = $this->makeAset([
            'nama_barang' => 'Mesin Lab Mesin',
            'kategori_id' => $kategoriMesin->id,
            'kode_barang' => 'MES-001',
        ]);
        $asetLain = $this->makeAset([
            'nama_barang' => 'Meja Kayu',
            'kategori_id' => $kategoriLain->id,
            'kode_barang' => 'KIB-001',
        ]);

        $response = $this->actingAs($user)->get(route('bmn.peminjaman-aset.create', [
            'kategori_id' => $kategoriMesin->id,
        ]));

        $response->assertOk();
        $response->assertSee($asetMesin->nama_barang, false);
        $response->assertDontSee($asetLain->nama_barang, false);
        $response->assertSee('aset dalam kategori terpilih', false);
    }

    public function test_search_within_kategori_narrows_results(): void
    {
        $user = $this->makeRequesterUser();
        $kategoriMesin = $this->makeKategori('MESIN PERALATAN NON TIK');

        $asetAc = $this->makeAset([
            'nama_barang' => 'AC Ruang Lab',
            'kategori_id' => $kategoriMesin->id,
            'kode_barang' => 'AC-001',
        ]);
        $asetKipas = $this->makeAset([
            'nama_barang' => 'Kipas Angin',
            'kategori_id' => $kategoriMesin->id,
            'kode_barang' => 'KF-001',
        ]);

        $response = $this->actingAs($user)->get(route('bmn.peminjaman-aset.create', [
            'kategori_id' => $kategoriMesin->id,
            'q' => 'AC',
        ]));

        $response->assertOk();
        $response->assertSee($asetAc->nama_barang, false);
        $response->assertDontSee($asetKipas->nama_barang, false);
        $response->assertSee('aset cocok pencarian dalam kategori ini', false);
    }

    public function test_pagination_preserves_filter_query_string(): void
    {
        $user = $this->makeRequesterUser();
        $kategoriMesin = $this->makeKategori('MESIN PERALATAN NON TIK');

        for ($i = 1; $i <= 21; $i++) {
            $this->makeAset([
                'nama_barang' => 'Aset Mesin '.$i,
                'kategori_id' => $kategoriMesin->id,
                'kode_barang' => 'MES-PAG-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'nup' => 'NUP-PAG-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->actingAs($user)->get(route('bmn.peminjaman-aset.create', [
            'kategori_id' => $kategoriMesin->id,
            'q' => 'Mesin',
        ]));

        $response->assertOk();
        $response->assertSee('page=2', false);
        $response->assertSee('kategori_id='.$kategoriMesin->id, false);
        $response->assertSee('q=Mesin', false);
    }

    public function test_reset_clears_all_filters(): void
    {
        $user = $this->makeRequesterUser();
        $kategoriMesin = $this->makeKategori('MESIN PERALATAN NON TIK');
        $this->makeAset([
            'nama_barang' => 'AC Ruang Lab',
            'kategori_id' => $kategoriMesin->id,
        ]);

        $response = $this->actingAs($user)->get(route('bmn.peminjaman-aset.create'));

        $response->assertOk();
        $response->assertSee(route('bmn.peminjaman-aset.create'), false);
        $response->assertDontSee('kategori_id=', false);
        $response->assertDontSee('name="q" value="AC"', false);
    }

    public function test_filter_submit_does_not_trigger_aset_ids_validation(): void
    {
        $user = $this->makeRequesterUser();
        $kategoriMesin = $this->makeKategori('MESIN PERALATAN NON TIK');
        $this->makeAset([
            'nama_barang' => 'AC Ruang Lab',
            'kategori_id' => $kategoriMesin->id,
        ]);

        $response = $this->actingAs($user)->get(route('bmn.peminjaman-aset.create', [
            'kategori_id' => $kategoriMesin->id,
        ]));

        $response->assertOk();
        $response->assertDontSee('The aset ids field is required', false);
        $response->assertDontSee('aset_ids wajib', false);
    }

    public function test_submit_peminjaman_after_filter_still_works(): void
    {
        $user = $this->makeRequesterUser();
        $kategoriMesin = $this->makeKategori('MESIN PERALATAN NON TIK');
        $aset = $this->makeAset([
            'nama_barang' => 'AC Ruang Lab',
            'kategori_id' => $kategoriMesin->id,
        ]);

        $this->actingAs($user)->get(route('bmn.peminjaman-aset.create', [
            'kategori_id' => $kategoriMesin->id,
            'q' => 'AC',
        ]))->assertOk();

        $response = $this->actingAs($user)->post(route('bmn.peminjaman-aset.store'), [
            'tujuan_peminjaman' => 'Praktikum lab',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(2)->toDateString(),
            'aset_ids' => [$aset->id],
        ]);

        $peminjaman = PeminjamanAsetBmn::query()->first();
        $this->assertNotNull($peminjaman);
        $this->assertSame(PeminjamanAsetBmn::STATUS_PENDING, $peminjaman->status);
        $response->assertRedirect(route('bmn.peminjaman-aset.show', $peminjaman->id));
    }

    public function test_empty_state_when_kategori_filter_has_no_results(): void
    {
        $user = $this->makeRequesterUser();
        $kategoriMesin = $this->makeKategori('MESIN PERALATAN NON TIK');
        $kategoriLain = $this->makeKategori('KIB A');
        $this->makeAset([
            'nama_barang' => 'Meja Kayu',
            'kategori_id' => $kategoriLain->id,
        ]);

        $response = $this->actingAs($user)->get(route('bmn.peminjaman-aset.create', [
            'kategori_id' => $kategoriMesin->id,
        ]));

        $response->assertOk();
        $response->assertSee('Tidak ada aset aktif pada kategori/filter ini.', false);
    }

    private function makeKategori(string $nama): KategoriAset
    {
        return KategoriAset::create([
            'nama_kategori' => $nama,
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeAset(array $overrides = []): AsetBmn
    {
        $satkerId = DB::table('bmn_satker')->orderBy('id')->value('id');
        $lokasiId = DB::table('lokasi_aset')->orderBy('id')->value('id');

        return AsetBmn::create(array_merge([
            'kode_barang' => 'TST-'.uniqid(),
            'nama_barang' => 'Aset Test',
            'kategori_id' => 1,
            'nilai_perolehan' => 1000,
            'tanggal_perolehan' => now()->toDateString(),
            'kondisi' => 'baik',
            'lokasi_id' => $lokasiId,
            'status' => 'aktif',
            'nup' => 'NUP-'.uniqid(),
            'satker_id' => $satkerId,
            'satker' => 'Satker Test',
        ], $overrides));
    }

    private function makeRequesterUser(): User
    {
        Role::firstOrCreate(['name' => 'jurusan', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Requester BMN',
            'email' => 'req-'.uniqid('', true).'@example.test',
            'username' => 'req'.uniqid(),
            'password' => bcrypt('secret'),
            'jurusan' => 'keperawatan',
        ]);
        $user->assignRole('jurusan');

        return $user;
    }
}

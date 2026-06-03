<?php

declare(strict_types=1);

namespace Tests\Feature\BMN;

use App\Models\BMN\AsetBmn;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PeminjamanAsetBmnDetail;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BmnPeminjamanContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_login_peminjaman_list_shows_user_email_and_no_hp(): void
    {
        $requester = $this->makeRequesterUser([
            'email' => 'peminjam@example.test',
            'no_hp' => '081234567890',
        ]);
        $peminjaman = $this->makePeminjamanForUser($requester, PeminjamanAsetBmn::SOURCE_LOGIN);
        $verifier1 = $this->makeVerifier1();

        $response = $this->actingAs($verifier1)->get(route('bmn.peminjaman-aset.index'));

        $response->assertOk();
        $response->assertSee('peminjam@example.test', false);
        $response->assertSee('081234567890', false);
        $response->assertSee('https://wa.me/6281234567890', false);
        $response->assertSee('mailto:peminjam@example.test', false);
        $response->assertSee($peminjaman->kode_peminjaman, false);
    }

    public function test_login_peminjaman_list_shows_dash_when_no_hp_empty(): void
    {
        $requester = $this->makeRequesterUser([
            'email' => 'tanpa-hp@example.test',
            'no_hp' => null,
        ]);
        $this->makePeminjamanForUser($requester, PeminjamanAsetBmn::SOURCE_LOGIN);
        $verifier1 = $this->makeVerifier1();

        $response = $this->actingAs($verifier1)->get(route('bmn.peminjaman-aset.index'));

        $response->assertOk();
        $response->assertSee('tanpa-hp@example.test', false);
        $response->assertSee('mailto:tanpa-hp@example.test', false);
        $response->assertDontSee('https://wa.me/', false);
    }

    public function test_public_peminjaman_list_shows_public_email_and_no_wa(): void
    {
        $peminjaman = $this->makePublicPeminjaman(
            'Budi Publik',
            '6289876543210',
            'budi@example.test'
        );
        $verifier2 = $this->makeVerifier2();

        $response = $this->actingAs($verifier2)->get(route('bmn.peminjaman-aset.index'));

        $response->assertOk();
        $response->assertSee('Budi Publik', false);
        $response->assertSee('budi@example.test', false);
        $response->assertSee('6289876543210', false);
        $response->assertSee('https://wa.me/6289876543210', false);
        $response->assertSee('mailto:budi@example.test', false);
        $response->assertSee($peminjaman->kode_peminjaman, false);
    }

    public function test_verifier_1_can_see_contact_on_detail_page(): void
    {
        $requester = $this->makeRequesterUser([
            'email' => 'detail@example.test',
            'no_hp' => '628111222333',
        ]);
        $peminjaman = $this->makePeminjamanForUser($requester, PeminjamanAsetBmn::SOURCE_LOGIN);
        $verifier1 = $this->makeVerifier1();

        $response = $this->actingAs($verifier1)->get(route('bmn.peminjaman-aset.show', $peminjaman->id));

        $response->assertOk();
        $response->assertSee('Hubungi WhatsApp', false);
        $response->assertSee('Kirim Email', false);
        $response->assertSee('https://wa.me/628111222333', false);
        $response->assertSee('mailto:detail@example.test', false);
        $response->assertSee($requester->name, false);
    }

    public function test_verifier_2_can_see_contact_on_detail_page(): void
    {
        $peminjaman = $this->makePublicPeminjaman(
            'Anon Publik',
            '081277788899',
            'anon@example.test'
        );
        $verifier2 = $this->makeVerifier2();

        $response = $this->actingAs($verifier2)->get(route('bmn.peminjaman-aset.show', $peminjaman->id));

        $response->assertOk();
        $response->assertSee('Hubungi WhatsApp', false);
        $response->assertSee('Kirim Email', false);
        $response->assertSee('https://wa.me/6281277788899', false);
        $response->assertSee('mailto:anon@example.test', false);
        $response->assertSee('Anon Publik', false);
    }

    public function test_index_table_is_responsive_with_contact_columns(): void
    {
        $requester = $this->makeRequesterUser([
            'email' => 'responsive@example.test',
            'no_hp' => '628123',
        ]);
        $this->makePeminjamanForUser($requester, PeminjamanAsetBmn::SOURCE_LOGIN);
        $verifier1 = $this->makeVerifier1();

        $response = $this->actingAs($verifier1)->get(route('bmn.peminjaman-aset.index'));

        $response->assertOk();
        $response->assertSee('table-responsive', false);
        $response->assertSee('table-peminjaman-bmn', false);
        $response->assertSee('No WA', false);
        $response->assertSee('Email', false);
    }

    public function test_model_accessors_resolve_login_and_public_contacts(): void
    {
        $requester = $this->makeRequesterUser([
            'email' => 'model@example.test',
            'no_hp' => '0812000111222',
        ]);
        $loginPeminjaman = $this->makePeminjamanForUser($requester, PeminjamanAsetBmn::SOURCE_LOGIN);
        $loginPeminjaman->load('user');

        $this->assertSame($requester->name, $loginPeminjaman->nama_peminjam);
        $this->assertSame('model@example.test', $loginPeminjaman->email_peminjam);
        $this->assertSame('0812000111222', $loginPeminjaman->wa_peminjam);
        $this->assertSame('https://wa.me/628120001111222', $loginPeminjaman->wa_peminjam_link);

        $publicPeminjaman = $this->makePublicPeminjaman(
            'Public User',
            '628999888777',
            'public@example.test'
        );

        $this->assertSame('Public User', $publicPeminjaman->nama_peminjam);
        $this->assertSame('public@example.test', $publicPeminjaman->email_peminjam);
        $this->assertSame('628999888777', $publicPeminjaman->wa_peminjam);
        $this->assertSame('https://wa.me/628999888777', $publicPeminjaman->wa_peminjam_link);
    }

    public function test_search_includes_peminjam_contact_fields(): void
    {
        $peminjaman = $this->makePublicPeminjaman(
            'Cari Nama',
            '628555666777',
            'cari@example.test'
        );
        $verifier1 = $this->makeVerifier1();

        $this->actingAs($verifier1)
            ->get(route('bmn.peminjaman-aset.index', ['q' => 'cari@example.test']))
            ->assertOk()
            ->assertSee($peminjaman->kode_peminjaman, false);

        $this->actingAs($verifier1)
            ->get(route('bmn.peminjaman-aset.index', ['q' => '628555666777']))
            ->assertOk()
            ->assertSee($peminjaman->kode_peminjaman, false);
    }

    private function makePeminjamanForUser(User $requester, string $source): PeminjamanAsetBmn
    {
        $aset = $this->makeAset();

        $peminjaman = PeminjamanAsetBmn::create([
            'kode_peminjaman' => 'PJM-BMN-TEST-'.uniqid(),
            'user_id' => $requester->id,
            'jurusan' => 'keperawatan',
            'tujuan_peminjaman' => 'Uji kontak peminjam',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(2)->toDateString(),
            'status' => PeminjamanAsetBmn::STATUS_PENDING,
            // Workflow baru: pengajuan baru selalu start di screening_rumah_tangga (Pak Singgih).
            'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA,
            'source' => $source,
            'verification_status' => PeminjamanAsetBmn::VERIFICATION_NOT_REQUIRED,
        ]);

        PeminjamanAsetBmnDetail::create([
            'peminjaman_aset_bmn_id' => $peminjaman->id,
            'peminjaman_id' => $peminjaman->id,
            'aset_bmn_id' => $aset->id,
            'aset_id' => $aset->id,
            'kondisi_awal' => 'baik',
            'status_item' => 'dipinjam',
        ]);

        return $peminjaman;
    }

    private function makePublicPeminjaman(string $nama, string $wa, string $email): PeminjamanAsetBmn
    {
        $aset = $this->makeAset();

        $peminjaman = PeminjamanAsetBmn::create([
            'kode_peminjaman' => 'PJM-BMN-PUB-'.uniqid(),
            'user_id' => null,
            'tujuan_peminjaman' => 'Uji kontak public',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(2)->toDateString(),
            'status' => PeminjamanAsetBmn::STATUS_PENDING,
            // Workflow baru: pengajuan baru selalu start di screening_rumah_tangga (Pak Singgih).
            'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA,
            'source' => PeminjamanAsetBmn::SOURCE_PUBLIC,
            'public_nama' => $nama,
            'public_no_wa' => $wa,
            'public_email' => $email,
            'verification_status' => PeminjamanAsetBmn::VERIFICATION_VERIFIED,
        ]);

        PeminjamanAsetBmnDetail::create([
            'peminjaman_aset_bmn_id' => $peminjaman->id,
            'peminjaman_id' => $peminjaman->id,
            'aset_bmn_id' => $aset->id,
            'aset_id' => $aset->id,
            'kondisi_awal' => 'baik',
            'status_item' => 'dipinjam',
        ]);

        return $peminjaman;
    }

    private function makeAset(): AsetBmn
    {
        $satkerId = DB::table('bmn_satker')->orderBy('id')->value('id');
        $lokasiId = DB::table('lokasi_aset')->orderBy('id')->value('id');

        return AsetBmn::create([
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
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRequesterUser(array $overrides = []): User
    {
        Role::firstOrCreate(['name' => 'jurusan', 'guard_name' => 'web']);

        $user = User::create(array_merge([
            'name' => 'Requester BMN',
            'email' => 'req-'.uniqid('', true).'@example.test',
            'username' => 'req'.uniqid(),
            'password' => bcrypt('secret'),
            'jurusan' => 'keperawatan',
        ], $overrides));
        $user->assignRole('jurusan');

        return $user;
    }

    private function makeVerifier1(): User
    {
        return $this->makeFixedIdUser(
            User::BMN_VERIFIER_1_ID,
            'Pak Hendri',
            'hendri-'.uniqid().'@example.test',
            'hendri'.uniqid()
        );
    }

    private function makeVerifier2(): User
    {
        return $this->makeFixedIdUser(
            User::BMN_VERIFIER_2_ID,
            'Pak Singgih',
            'singgih-'.uniqid().'@example.test',
            'singgih'.uniqid()
        );
    }

    private function makeFixedIdUser(int $id, string $name, string $email, string $username): User
    {
        $existing = User::query()->find($id);
        if ($existing !== null) {
            return $existing;
        }

        DB::table('users')->insert([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}

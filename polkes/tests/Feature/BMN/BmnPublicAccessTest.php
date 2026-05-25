<?php

declare(strict_types=1);

namespace Tests\Feature\BMN;

use App\Models\BMN\AsetBmn;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PeminjamanAsetBmnDetail;
use App\Models\BMN\PengembalianAsetBmn;
use App\Services\BMN\BmnWaNumberNormalizer;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BmnPublicAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Mail::fake();
    }

    public function test_guest_can_open_peminjaman_create_without_redirect_to_login(): void
    {
        $response = $this->get(route('bmn.peminjaman-aset.create'));

        $response->assertOk();
        $response->assertDontSee('login');
    }

    public function test_guest_cannot_access_peminjaman_index(): void
    {
        $response = $this->get(route('bmn.peminjaman-aset.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_pengembalian_index(): void
    {
        $response = $this->get(route('bmn.pengembalian-aset.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_wa_normalizer_converts_08_to_62(): void
    {
        $this->assertSame('628123456789', BmnWaNumberNormalizer::normalize('08123456789'));
        $this->assertSame('628123456789', BmnWaNumberNormalizer::normalize('+62 812-3456-789'));
    }

    public function test_guest_peminjaman_store_creates_public_record_and_redirects_to_verification(): void
    {
        $aset = $this->makeAset();

        $response = $this->post(route('bmn.peminjaman-aset.store'), [
            'public_nama' => 'Budi Publik',
            'public_no_wa' => '081234567890',
            'public_email' => 'budi@example.test',
            'tujuan_peminjaman' => 'Kegiatan luar',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(3)->toDateString(),
            'aset_ids' => [$aset->id],
        ]);

        $peminjaman = PeminjamanAsetBmn::query()->first();
        $this->assertNotNull($peminjaman);
        $this->assertNull($peminjaman->user_id);
        $this->assertSame(PeminjamanAsetBmn::SOURCE_PUBLIC, $peminjaman->source);
        $this->assertSame(PeminjamanAsetBmn::VERIFICATION_PENDING, $peminjaman->verification_status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1, $peminjaman->approval_stage);
        $this->assertSame('6281234567890', $peminjaman->public_no_wa);

        $response->assertRedirect(route('bmn.public-verification.show', [
            'type' => 'peminjaman',
            'id' => $peminjaman->id,
            'token' => $peminjaman->public_token,
        ]));
    }

    public function test_otp_verification_marks_public_peminjaman_verified(): void
    {
        $aset = $this->makeAset();
        $plainEmailOtp = '123456';
        $plainWaOtp = '654321';

        $peminjaman = PeminjamanAsetBmn::create([
            'kode_peminjaman' => 'PJM-BMN-TEST-001',
            'user_id' => null,
            'tujuan_peminjaman' => 'Test',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDay()->toDateString(),
            'status' => PeminjamanAsetBmn::STATUS_PENDING,
            'source' => PeminjamanAsetBmn::SOURCE_PUBLIC,
            'public_nama' => 'Anon',
            'public_no_wa' => '628111',
            'public_email' => 'anon@example.test',
            'public_token' => str_repeat('a', 64),
            'public_email_otp' => Hash::make($plainEmailOtp),
            'public_wa_otp' => Hash::make($plainWaOtp),
            'public_otp_expires_at' => now()->addMinutes(10),
            'verification_status' => PeminjamanAsetBmn::VERIFICATION_PENDING,
        ]);

        PeminjamanAsetBmnDetail::create([
            'peminjaman_aset_bmn_id' => $peminjaman->id,
            'peminjaman_id' => $peminjaman->id,
            'aset_bmn_id' => $aset->id,
            'aset_id' => $aset->id,
            'kondisi_awal' => 'baik',
            'status_item' => 'dipinjam',
        ]);

        $response = $this->post(route('bmn.public-verification.verify', [
            'type' => 'peminjaman',
            'id' => $peminjaman->id,
            'token' => $peminjaman->public_token,
        ]), [
            'email_otp' => $plainEmailOtp,
            'wa_otp' => $plainWaOtp,
        ]);

        $response->assertRedirect();
        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::VERIFICATION_VERIFIED, $peminjaman->verification_status);
        $this->assertNotNull($peminjaman->public_verified_at);
    }

    public function test_wrong_verification_token_returns_404(): void
    {
        $peminjaman = PeminjamanAsetBmn::create([
            'kode_peminjaman' => 'PJM-BMN-TEST-002',
            'user_id' => null,
            'tujuan_peminjaman' => 'Test',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDay()->toDateString(),
            'status' => PeminjamanAsetBmn::STATUS_PENDING,
            'source' => PeminjamanAsetBmn::SOURCE_PUBLIC,
            'public_token' => str_repeat('b', 64),
            'verification_status' => PeminjamanAsetBmn::VERIFICATION_PENDING,
        ]);

        $response = $this->get(route('bmn.public-verification.show', [
            'type' => 'peminjaman',
            'id' => $peminjaman->id,
            'token' => str_repeat('x', 64),
        ]));

        $response->assertNotFound();
    }

    public function test_login_user_peminjaman_store_keeps_login_flow(): void
    {
        $user = $this->makeRequesterUser();
        $aset = $this->makeAset();

        $response = $this->actingAs($user)->post(route('bmn.peminjaman-aset.store'), [
            'tujuan_peminjaman' => 'Praktikum',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(2)->toDateString(),
            'aset_ids' => [$aset->id],
        ]);

        $peminjaman = PeminjamanAsetBmn::query()->first();
        $this->assertNotNull($peminjaman);
        $this->assertSame((int) $user->id, (int) $peminjaman->user_id);
        $this->assertSame(PeminjamanAsetBmn::SOURCE_LOGIN, $peminjaman->source);
        $this->assertSame(PeminjamanAsetBmn::VERIFICATION_NOT_REQUIRED, $peminjaman->verification_status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1, $peminjaman->approval_stage);

        $response->assertRedirect(route('bmn.peminjaman-aset.show', $peminjaman->id));
    }

    public function test_guest_pengembalian_store_creates_pending_public_record(): void
    {
        $aset = $this->makeAset();
        $peminjaman = PeminjamanAsetBmn::create([
            'kode_peminjaman' => 'PJM-BMN-RET-001',
            'user_id' => null,
            'tujuan_peminjaman' => 'Test',
            'tanggal_pinjam' => now()->subDays(2)->toDateString(),
            'rencana_kembali' => now()->addDay()->toDateString(),
            'status' => PeminjamanAsetBmn::STATUS_DIPINJAM,
            'source' => PeminjamanAsetBmn::SOURCE_PUBLIC,
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

        $response = $this->post(route('bmn.pengembalian-aset.store'), [
            'kode_peminjaman' => $peminjaman->kode_peminjaman,
            'public_nama' => 'Budi Kembali',
            'public_no_wa' => '081299988877',
            'public_email' => 'kembali@example.test',
            'tanggal_kembali' => now()->toDateString(),
            'catatan_pengembalian' => 'Sudah dikembalikan',
        ]);

        $pengembalian = PengembalianAsetBmn::query()->first();
        $this->assertNotNull($pengembalian);
        $this->assertSame(PengembalianAsetBmn::SOURCE_PUBLIC, $pengembalian->source);
        $this->assertSame('pending', $pengembalian->verification_status);
        $this->assertSame(PengembalianAsetBmn::ADMIN_STATUS_PENDING, $pengembalian->admin_status);

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_DIPINJAM, $peminjaman->status);

        $response->assertRedirect(route('bmn.public-verification.show', [
            'type' => 'pengembalian',
            'id' => $pengembalian->id,
            'token' => $pengembalian->public_token,
        ]));
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

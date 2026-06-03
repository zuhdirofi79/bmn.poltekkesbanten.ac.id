<?php

declare(strict_types=1);

namespace Tests\Feature\BMN;

use App\Models\BMN\AsetBmn;
use App\Models\BMN\BmnSewaPengajuan;
use App\Models\BMN\BmnSewaTarif;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PeminjamanAsetBmnDetail;
use App\Models\BMN\PengembalianAsetBmn;
use App\Services\BMN\BmnWaNumberNormalizer;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        // Workflow baru: pengajuan baru SELALU masuk ke screening_rumah_tangga (Pak Singgih).
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA, $peminjaman->approval_stage);
        $this->assertSame('6281234567890', $peminjaman->public_no_wa);

        $response->assertRedirect(route('bmn.public-verification.show', [
            'type' => 'peminjaman',
            'id' => $peminjaman->id,
            'token' => $peminjaman->public_token,
        ]));
    }

    public function test_email_only_otp_verification_marks_public_peminjaman_verified(): void
    {
        // WhatsApp OTP sementara dinonaktifkan — verifikasi cukup pakai email.
        $aset = $this->makeAset();
        $plainEmailOtp = '123456';

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
            'public_wa_otp' => null,
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

        // Sengaja TIDAK mengirim `wa_otp` — endpoint harus tetap sukses karena
        // WA OTP dinonaktifkan dan input `wa_otp` bersifat nullable.
        $response = $this->post(route('bmn.public-verification.verify', [
            'type' => 'peminjaman',
            'id' => $peminjaman->id,
            'token' => $peminjaman->public_token,
        ]), [
            'email_otp' => $plainEmailOtp,
        ]);

        $response->assertRedirect();
        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::VERIFICATION_VERIFIED, $peminjaman->verification_status);
        $this->assertNotNull($peminjaman->public_verified_at);
        $this->assertNotNull($peminjaman->public_email_verified_at);
    }

    public function test_invalid_wa_format_is_rejected(): void
    {
        $aset = $this->makeAset();

        // Nomor WA mengandung huruf — harus ditolak oleh format validator.
        $response = $this->post(route('bmn.peminjaman-aset.store'), [
            'public_nama' => 'Budi',
            'public_no_wa' => 'abcde12345',
            'public_email' => 'budi@example.test',
            'tujuan_peminjaman' => 'Tes',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDay()->toDateString(),
            'aset_ids' => [$aset->id],
        ]);

        $response->assertSessionHasErrors('public_no_wa');
        $this->assertSame(0, PeminjamanAsetBmn::query()->count());
    }

    public function test_sewa_email_verification_full_flow(): void
    {
        // 1. Submit sewa → redirect ke halaman verifikasi email (bukan success).
        // 2. Verifikasi email → record di-mark verified, dialihkan ke success page.
        // 3. WhatsApp OTP tidak diminta sama sekali (dinonaktifkan).
        $tarif = BmnSewaTarif::create([
            'kategori' => 'Test',
            'nama_layanan' => 'Sewa Email Verif',
            'satuan' => 'per hari',
            'tarif_umum' => 100000,
            'tarif_pegawai' => 50000,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $this->post(route('public.sewa.umum.store'), [
            'nama_lengkap' => 'Penyewa Tes',
            'nomor_wa' => '081234567890',
            'email' => 'sewa-verif@example.test',
            'tarif_id' => $tarif->id,
            'tanggal_mulai_sewa' => now()->toDateString(),
            'tanggal_rencana_kembali' => now()->addDay()->toDateString(),
            'tujuan_peminjaman' => 'Acara',
            'surat_resmi' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
            'bukti_bayar' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect();

        /** @var BmnSewaPengajuan $pengajuan */
        $pengajuan = BmnSewaPengajuan::query()->firstOrFail();

        $this->assertSame(BmnSewaPengajuan::VERIFICATION_PENDING, $pengajuan->verification_status);
        $this->assertNotNull($pengajuan->public_email_otp);
        $this->assertNull($pengajuan->public_wa_otp, 'WA OTP harus null saat WA OTP dinonaktifkan.');

        // Tidak ada cara membaca plain OTP dari record (hash). Untuk test, set
        // ulang dengan hash known supaya bisa di-verify lewat endpoint.
        $plainOtp = '987654';
        $pengajuan->forceFill([
            'public_email_otp' => Hash::make($plainOtp),
            'public_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->post(route('bmn.public-verification.verify', [
            'type' => 'sewa',
            'id' => $pengajuan->id,
            'token' => $pengajuan->public_token,
        ]), [
            'email_otp' => $plainOtp,
        ]);

        // Sukses → redirect ke halaman success sewa.
        $response->assertRedirect(route('public.sewa.success', $pengajuan->id));

        $pengajuan->refresh();
        $this->assertSame(BmnSewaPengajuan::VERIFICATION_VERIFIED, $pengajuan->verification_status);
        $this->assertNotNull($pengajuan->public_email_verified_at);
        $this->assertTrue($pengajuan->isEmailVerified());
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
        // Workflow baru: pengajuan baru SELALU masuk ke screening_rumah_tangga (Pak Singgih).
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA, $peminjaman->approval_stage);

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

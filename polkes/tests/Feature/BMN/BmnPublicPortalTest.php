<?php

declare(strict_types=1);

namespace Tests\Feature\BMN;

use App\Models\BMN\AsetBmn;
use App\Models\BMN\BmnSewaPengajuan;
use App\Models\BMN\BmnSewaTarif;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PeminjamanAsetBmnDetail;
use App\Models\BMN\PeminjamanAsetBmnDocument;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BmnPublicPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_landing_page_shows_four_tiles(): void
    {
        $response = $this->get(route('public.landing'));

        $response->assertOk();
        $response->assertSee('Dashboard Utama Sistem Informasi BMN', false);
        $response->assertSee('SISTEM INFORMASI', false);
        $response->assertSee('Aset', false);
        $response->assertSee('Peminjaman Internal', false);
        $response->assertSee('Sewa', false);
        $response->assertSee('Pemeliharaan', false);
        $response->assertSee('Layanan Tersedia', false);
    }

    public function test_landing_aset_tile_links_to_login(): void
    {
        $response = $this->get(route('public.landing'));

        $response->assertSee(url('login'), false);
    }

    public function test_login_page_does_not_show_public_peminjaman_buttons(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('Ajukan Peminjaman Aset BMN', false);
        $response->assertDontSee('Ajukan Pengembalian Aset BMN', false);
    }

    public function test_peminjaman_internal_submenu(): void
    {
        $response = $this->get(route('public.peminjaman-internal.index'));

        $response->assertOk();
        $response->assertSee('Ajukan Peminjaman Internal', false);
        $response->assertSee('Ajukan Pengembalian Internal', false);
    }

    public function test_public_pinjam_form_does_not_show_aset_table(): void
    {
        $response = $this->get(route('public.peminjaman-internal.pinjam'));

        $response->assertOk();
        $response->assertSee('Barang yang Diajukan', false);
        $response->assertSee('Tambah File', false);
        $response->assertSee('Bisa upload lebih dari 1 file', false);
        $response->assertDontSee('kode_barang', false);
        $response->assertDontSee('NUP', false);
        $response->assertDontSee('aset_ids', false);
    }

    public function test_public_pinjam_submit_creates_record_without_aset_details(): void
    {
        $response = $this->post(route('public.peminjaman-internal.pinjam.store'), [
            'public_nama' => 'Budi Publik',
            'public_no_wa' => '081234567890',
            'public_email' => 'budi@example.test',
            'requested_barang' => 'Laptop dan proyektor',
            'tujuan_peminjaman' => 'Rapat internal',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(3)->toDateString(),
            'nota_dinas_files' => [
                UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            ],
        ]);

        $response->assertRedirect();

        $peminjaman = PeminjamanAsetBmn::query()->first();
        $this->assertNotNull($peminjaman);
        $this->assertNull($peminjaman->user_id);
        $this->assertSame(PeminjamanAsetBmn::SOURCE_PUBLIC, $peminjaman->source);
        $this->assertSame('Laptop dan proyektor', $peminjaman->requested_barang);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA, $peminjaman->approval_stage);
        $this->assertSame(0, $peminjaman->details()->count());
        $this->assertNotNull($peminjaman->nota_dinas_path);
        $this->assertSame(1, $peminjaman->documents()->count());
    }

    public function test_public_pinjam_submit_stores_multiple_nota_dinas_files(): void
    {
        $file1 = UploadedFile::fake()->create('nota-1.pdf', 100, 'application/pdf');
        $file2 = UploadedFile::fake()->create('nota-2.pdf', 120, 'application/pdf');

        $response = $this->post(route('public.peminjaman-internal.pinjam.store'), [
            'public_nama' => 'Budi Publik',
            'public_no_wa' => '081234567890',
            'public_email' => 'budi-multi@example.test',
            'requested_barang' => 'Laptop dan proyektor',
            'tujuan_peminjaman' => 'Rapat internal',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(3)->toDateString(),
            'nota_dinas_files' => [$file1, $file2],
        ]);

        $response->assertRedirect();

        $peminjaman = PeminjamanAsetBmn::query()->where('public_email', 'budi-multi@example.test')->first();
        $this->assertNotNull($peminjaman);
        $this->assertSame('nota-1.pdf', $peminjaman->nota_dinas_original_name);
        $this->assertNotNull($peminjaman->nota_dinas_path);
        $this->assertTrue(is_file(bmn_nota_dinas_path(basename((string) $peminjaman->nota_dinas_path))));

        $documents = PeminjamanAsetBmnDocument::query()
            ->where('peminjaman_aset_bmn_id', $peminjaman->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $documents);
        $this->assertSame('nota-1.pdf', $documents[0]->original_name);
        $this->assertSame('nota-2.pdf', $documents[1]->original_name);
        $this->assertTrue(is_file(bmn_nota_dinas_path(basename($documents[0]->file_path))));
        $this->assertTrue(is_file(bmn_nota_dinas_path(basename($documents[1]->file_path))));
    }

    public function test_rumah_tangga_can_screen_and_forward_to_hendri(): void
    {
        $peminjaman = $this->makePublicInternalPeminjaman();
        $aset = $this->makeAset();
        $rumahTangga = $this->makeRumahTanggaUser(User::BMN_RUMAH_TANGGA_SINGGIH_ID);

        $this->actingAs($rumahTangga)
            ->get(route('bmn.peminjaman-aset.screening', $peminjaman->id))
            ->assertOk()
            ->assertSee('Laptop', false);

        $this->actingAs($rumahTangga)
            ->post(route('bmn.peminjaman-aset.screening.store', $peminjaman->id), [
                'screening_result' => 'tersedia',
                'aset_ids' => [$aset->id],
                'screening_note' => 'Barang tersedia',
            ])
            ->assertRedirect(route('bmn.peminjaman-aset.show', $peminjaman->id));

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI, $peminjaman->approval_stage);
        $this->assertSame(PeminjamanAsetBmn::SCREENING_TERSEDIA, $peminjaman->screening_status);
        $this->assertSame(1, $peminjaman->details()->count());
    }

    public function test_hendri_can_approve_final_screening_flow(): void
    {
        $peminjaman = $this->makePublicInternalPeminjaman([
            'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI,
            'screening_status' => PeminjamanAsetBmn::SCREENING_TERSEDIA,
        ]);
        $aset = $this->makeAset();
        PeminjamanAsetBmnDetail::create([
            'peminjaman_aset_bmn_id' => $peminjaman->id,
            'peminjaman_id' => $peminjaman->id,
            'aset_bmn_id' => $aset->id,
            'aset_id' => $aset->id,
            'kondisi_awal' => 'baik',
            'status_item' => 'dipinjam',
        ]);

        $hendri = $this->makeVerifier1();

        $this->actingAs($hendri)
            ->post(route('bmn.peminjaman-aset.approve-final-hendri', $peminjaman->id), [
                'note' => 'Disetujui',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_APPROVED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_COMPLETED, $peminjaman->approval_stage);
    }

    public function test_sewa_umum_does_not_show_pegawai_discount(): void
    {
        BmnSewaTarif::create([
            'kategori' => 'Test',
            'nama_layanan' => 'Layanan Test',
            'satuan' => 'per hari',
            'tarif_umum' => 100000,
            'tarif_pegawai' => 50000,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('public.sewa.umum'));

        $response->assertOk();
        $response->assertSee('100.000', false);
        $response->assertDontSee('Diskon 50%', false);
        $response->assertDontSee('50.000', false);
        $response->assertDontSee('data-pegawai', false);
    }

    public function test_sewa_pegawai_shows_discount_badge_and_pegawai_tarif(): void
    {
        BmnSewaTarif::create([
            'kategori' => 'Test',
            'nama_layanan' => 'Layanan Pegawai',
            'satuan' => 'per hari',
            'tarif_umum' => 200000,
            'tarif_pegawai' => 100000,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('public.sewa.pegawai'));

        $response->assertOk();
        $response->assertSee('Diskon 50%', false);
        $response->assertSee('100.000', false);
        $response->assertSee('200.000', false);
    }

    public function test_sewa_submit_redirects_to_email_verification(): void
    {
        // WhatsApp OTP dinonaktifkan; email verification WAJIB. Setelah submit,
        // user harus diarahkan ke halaman verifikasi email (bukan langsung
        // success). Halaman success di-cover di test berikutnya setelah email
        // diverifikasi.
        $tarif = BmnSewaTarif::create([
            'kategori' => 'Test',
            'nama_layanan' => 'Sewa Test',
            'satuan' => 'per hari',
            'tarif_umum' => 100000,
            'tarif_pegawai' => 50000,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $this->post(route('public.sewa.umum.store'), [
            'nama_lengkap' => 'Penyewa Umum',
            'nomor_wa' => '081234567890',
            'email' => 'sewa@example.test',
            'tarif_id' => $tarif->id,
            'tanggal_mulai_sewa' => now()->toDateString(),
            'tanggal_rencana_kembali' => now()->addDays(2)->toDateString(),
            'tujuan_peminjaman' => 'Acara',
            'surat_resmi' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
            'bukti_bayar' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect();

        $pengajuan = BmnSewaPengajuan::query()->first();
        $this->assertNotNull($pengajuan);
        $this->assertSame('6281234567890', $pengajuan->nomor_wa);
        // Token verifikasi harus terisi oleh BmnPublicVerificationService.
        $this->assertNotNull($pengajuan->public_token);
        $this->assertSame(64, strlen((string) $pengajuan->public_token));
        $this->assertSame(BmnSewaPengajuan::VERIFICATION_PENDING, $pengajuan->verification_status);

        // Halaman verifikasi publik harus accessible & menampilkan instruksi email.
        $this->get(route('bmn.public-verification.show', [
            'type' => 'sewa',
            'id' => $pengajuan->id,
            'token' => $pengajuan->public_token,
        ]))->assertOk()
            ->assertSee('Email wajib diverifikasi', false)
            ->assertSee('Verifikasi WhatsApp sementara dinonaktifkan', false);
    }

    public function test_sewa_success_visible_after_email_verified(): void
    {
        // Setelah verifikasi email berhasil, halaman success sewa masih dapat
        // diakses dan menampilkan kontak admin koordinasi (Bu Retno).
        $tarif = BmnSewaTarif::create([
            'kategori' => 'Test',
            'nama_layanan' => 'Sewa Test',
            'satuan' => 'per hari',
            'tarif_umum' => 100000,
            'tarif_pegawai' => 50000,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $pengajuan = BmnSewaPengajuan::create([
            'kode_sewa' => 'SW-BMN-TEST-001',
            'jenis_penyewa' => BmnSewaPengajuan::JENIS_UMUM,
            'nama_lengkap' => 'Penyewa Umum',
            'nomor_wa' => '6281234567890',
            'email' => 'sewa@example.test',
            'tarif_id' => $tarif->id,
            'tanggal_mulai_sewa' => now()->toDateString(),
            'tanggal_rencana_kembali' => now()->addDays(2)->toDateString(),
            'tujuan_peminjaman' => 'Acara',
            'payment_status' => BmnSewaPengajuan::PAYMENT_PENDING,
            'status' => BmnSewaPengajuan::STATUS_PAYMENT_REVIEW,
            'public_email_verified_at' => now(),
            'public_verified_at' => now(),
            'verification_status' => BmnSewaPengajuan::VERIFICATION_VERIFIED,
        ]);

        $this->get(route('public.sewa.success', $pengajuan->id))
            ->assertOk()
            ->assertSee('813-1511-9830', false)
            ->assertSee($pengajuan->kode_sewa, false);
    }

    public function test_user_helpers_rumah_tangga_and_final_approver(): void
    {
        $munawir = $this->makeRumahTanggaUser(User::BMN_RUMAH_TANGGA_MUNAWIR_ID);
        $singgih = $this->makeRumahTanggaUser(User::BMN_RUMAH_TANGGA_SINGGIH_ID);
        $hendri = $this->makeVerifier1();

        $this->assertTrue($munawir->isRumahTanggaBmn());
        $this->assertTrue($singgih->isRumahTanggaBmn());
        $this->assertTrue($hendri->isFinalBmnApprover());
        $this->assertFalse($hendri->isRumahTanggaBmn());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublicInternalPeminjaman(array $overrides = []): PeminjamanAsetBmn
    {
        return PeminjamanAsetBmn::create(array_merge([
            'kode_peminjaman' => 'PJM-BMN-INT-'.uniqid(),
            'user_id' => null,
            'requested_barang' => 'Laptop',
            'tujuan_peminjaman' => 'Rapat',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(2)->toDateString(),
            'status' => PeminjamanAsetBmn::STATUS_PENDING,
            'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA,
            'screening_status' => PeminjamanAsetBmn::SCREENING_PENDING,
            'source' => PeminjamanAsetBmn::SOURCE_PUBLIC,
            'public_nama' => 'Public User',
            'public_no_wa' => '6281234567890',
            'public_email' => 'public@example.test',
            'verification_status' => PeminjamanAsetBmn::VERIFICATION_VERIFIED,
        ], $overrides));
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

    private function makeRumahTanggaUser(int $id): User
    {
        return $this->makeFixedIdUser($id, 'Rumah Tangga '.$id, 'rt'.$id.'@example.test', 'rt'.$id);
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

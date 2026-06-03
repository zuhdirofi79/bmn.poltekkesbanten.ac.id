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

/**
 * Workflow approval BARU peminjaman aset BMN:
 *   Pengajuan -> Pak Singgih (screening_rumah_tangga) -> Pak Hendri (final_hendri) -> completed.
 */
class BmnPeminjamanApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_create_peminjaman_sets_pending_and_screening_rumah_tangga_stage(): void
    {
        $requester = $this->makeRequesterUser();
        $aset = $this->makeAset();

        $this->actingAs($requester)->post(route('bmn.peminjaman-aset.store'), [
            'tujuan_peminjaman' => 'Praktikum lab',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(2)->toDateString(),
            'aset_ids' => [$aset->id],
        ]);

        $peminjaman = PeminjamanAsetBmn::query()->first();
        $this->assertNotNull($peminjaman);
        $this->assertSame(PeminjamanAsetBmn::STATUS_PENDING, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA, $peminjaman->approval_stage);
        $this->assertNull($peminjaman->screened_by);
        $this->assertNull($peminjaman->approved_by);
    }

    public function test_singgih_approval_advances_to_final_hendri(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $singgih = $this->makeSinggih();

        $this->actingAs($singgih)
            ->post(route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id), [
                'note' => 'Stok tersedia',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_PENDING, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI, $peminjaman->approval_stage);
        $this->assertSame(User::BMN_VERIFIER_2_ID, (int) $peminjaman->screened_by);
        $this->assertSame(PeminjamanAsetBmn::SCREENING_TERSEDIA, $peminjaman->screening_status);
        $this->assertNotNull($peminjaman->screened_at);
    }

    public function test_hendri_final_approval_completes_workflow(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $singgih = $this->makeSinggih();
        $hendri = $this->makeHendri();

        $this->actingAs($singgih)
            ->post(route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id), [
                'note' => 'Stok tersedia',
            ])
            ->assertRedirect();

        $this->actingAs($hendri)
            ->post(route('bmn.peminjaman-aset.approve-hendri', $peminjaman->id), [
                'note' => 'Disetujui final',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_APPROVED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_COMPLETED, $peminjaman->approval_stage);
        $this->assertSame(User::BMN_VERIFIER_1_ID, (int) $peminjaman->approved_by);
        $this->assertNotNull($peminjaman->approved_at);
        $this->assertNotNull($peminjaman->final_report_at);
    }

    public function test_singgih_can_reject_at_screening_stage(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $singgih = $this->makeSinggih();

        $this->actingAs($singgih)
            ->post(route('bmn.peminjaman-aset.reject-singgih', $peminjaman->id), [
                'rejected_reason' => 'Barang tidak tersedia',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_REJECTED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_REJECTED, $peminjaman->approval_stage);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA, $peminjaman->rejected_stage);
        $this->assertSame('Barang tidak tersedia', $peminjaman->rejected_reason);
        $this->assertSame(PeminjamanAsetBmn::SCREENING_TIDAK_TERSEDIA, $peminjaman->screening_status);
    }

    public function test_hendri_can_reject_at_final_stage(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $singgih = $this->makeSinggih();
        $hendri = $this->makeHendri();

        $this->actingAs($singgih)
            ->post(route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id))
            ->assertRedirect();

        $this->actingAs($hendri)
            ->post(route('bmn.peminjaman-aset.reject-hendri', $peminjaman->id), [
                'rejected_reason' => 'Tidak sesuai kebijakan',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_REJECTED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_REJECTED, $peminjaman->approval_stage);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI, $peminjaman->rejected_stage);
        $this->assertSame('Tidak sesuai kebijakan', $peminjaman->rejected_reason);
    }

    public function test_hendri_cannot_approve_before_singgih(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $hendri = $this->makeHendri();

        $this->actingAs($hendri)
            ->post(route('bmn.peminjaman-aset.approve-hendri', $peminjaman->id))
            ->assertSessionHas('error');

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_PENDING, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA, $peminjaman->approval_stage);
    }

    public function test_singgih_cannot_approve_after_passing_to_hendri(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $singgih = $this->makeSinggih();

        $this->actingAs($singgih)
            ->post(route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id))
            ->assertRedirect();

        // Stage sekarang = final_hendri; Singgih tidak boleh approve lagi.
        $this->actingAs($singgih)
            ->post(route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id))
            ->assertSessionHas('error');

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI, $peminjaman->approval_stage);
    }

    public function test_non_authorized_users_cannot_approve(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $requester = $this->makeRequesterUser();

        $this->actingAs($requester)
            ->post(route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id))
            ->assertForbidden();

        $this->actingAs($requester)
            ->post(route('bmn.peminjaman-aset.approve-hendri', $peminjaman->id))
            ->assertForbidden();

        $this->actingAs($requester)
            ->post(route('bmn.peminjaman-aset.reject-singgih', $peminjaman->id), [
                'rejected_reason' => 'x',
            ])
            ->assertForbidden();

        $this->actingAs($requester)
            ->post(route('bmn.peminjaman-aset.reject-hendri', $peminjaman->id), [
                'rejected_reason' => 'x',
            ])
            ->assertForbidden();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA, $peminjaman->approval_stage);
    }

    public function test_admin_with_can_approve_bmn_can_finalize_as_hendri_proxy(): void
    {
        // Skenario: admin BMN umum (bukan Pak Hendri) tetap boleh memberi approval final
        // selama punya can_approve_bmn = 1. Spec: "user id 3235 atau can_approve_bmn = 1".
        $peminjaman = $this->makePendingPeminjaman();
        $singgih = $this->makeSinggih();

        $this->actingAs($singgih)
            ->post(route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id))
            ->assertRedirect();

        $admin = $this->makeAdminApprover();

        $this->actingAs($admin)
            ->post(route('bmn.peminjaman-aset.approve-hendri', $peminjaman->id))
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_APPROVED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_COMPLETED, $peminjaman->approval_stage);
    }

    public function test_legacy_data_at_verifikator_1_is_treated_as_awaiting_singgih(): void
    {
        // Data lama yang masih bertahap "verifikator_1" harus tetap bisa di-approve oleh
        // Pak Singgih (spec point 9: legacy verifikator_1 = "Menunggu Pak Singgih").
        $peminjaman = $this->makePendingPeminjaman(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1);
        $singgih = $this->makeSinggih();

        $this->actingAs($singgih)
            ->post(route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id))
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI, $peminjaman->approval_stage);
    }

    public function test_legacy_data_at_verifikator_2_is_treated_as_awaiting_hendri(): void
    {
        // Data lama bertahap "verifikator_2" harus tetap bisa di-approve final oleh Pak Hendri
        // (spec point 9: legacy verifikator_2 / final_report = "Menunggu Pak Hendri").
        $peminjaman = $this->makePendingPeminjaman(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2);
        $hendri = $this->makeHendri();

        $this->actingAs($hendri)
            ->post(route('bmn.peminjaman-aset.approve-hendri', $peminjaman->id))
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_APPROVED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_COMPLETED, $peminjaman->approval_stage);
    }

    public function test_show_page_shows_button_for_singgih_at_screening_stage(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $singgih = $this->makeSinggih();

        $response = $this->actingAs($singgih)->get(route('bmn.peminjaman-aset.show', $peminjaman->id));

        $response->assertOk();
        $response->assertSee('Setujui sebagai Pak Singgih', false);
        $response->assertDontSee('Setujui Final Pak Hendri', false);
    }

    public function test_show_page_shows_button_for_hendri_at_final_stage(): void
    {
        $peminjaman = $this->makePendingPeminjaman(PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI);
        $hendri = $this->makeHendri();

        $response = $this->actingAs($hendri)->get(route('bmn.peminjaman-aset.show', $peminjaman->id));

        $response->assertOk();
        $response->assertSee('Setujui Final Pak Hendri', false);
        $response->assertDontSee('Setujui sebagai Pak Singgih', false);
    }

    public function test_show_page_does_not_show_approve_buttons_to_regular_admin(): void
    {
        // Admin biasa (tanpa can_approve_bmn) tidak boleh melihat tombol approve.
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Admin Biasa',
            'email' => 'adm-'.uniqid().'@example.test',
            'username' => 'adm'.uniqid(),
            'password' => bcrypt('secret'),
        ]);
        $admin->assignRole('admin');

        $peminjaman = $this->makePendingPeminjaman();

        $response = $this->actingAs($admin)->get(route('bmn.peminjaman-aset.show', $peminjaman->id));

        $response->assertOk();
        $response->assertDontSee('Setujui sebagai Pak Singgih', false);
        $response->assertDontSee('Setujui Final Pak Hendri', false);
        $response->assertSee('Menunggu persetujuan verifikator terkait', false);
    }

    private function makePendingPeminjaman(string $stage = PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA): PeminjamanAsetBmn
    {
        $requester = $this->makeRequesterUser();
        $aset = $this->makeAset();

        $peminjaman = PeminjamanAsetBmn::create([
            'kode_peminjaman' => 'PJM-BMN-TEST-'.uniqid(),
            'user_id' => $requester->id,
            'jurusan' => 'keperawatan',
            'tujuan_peminjaman' => 'Uji approval',
            'tanggal_pinjam' => now()->toDateString(),
            'rencana_kembali' => now()->addDays(2)->toDateString(),
            'status' => PeminjamanAsetBmn::STATUS_PENDING,
            'approval_stage' => $stage,
            'source' => PeminjamanAsetBmn::SOURCE_LOGIN,
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

    private function makeHendri(): User
    {
        return $this->makeFixedIdUser(
            User::BMN_VERIFIER_1_ID,
            'Pak Hendri',
            'hendri-'.uniqid().'@example.test',
            'hendri'.uniqid()
        );
    }

    private function makeSinggih(): User
    {
        return $this->makeFixedIdUser(
            User::BMN_VERIFIER_2_ID,
            'Pak Singgih',
            'singgih-'.uniqid().'@example.test',
            '199705042025211037'
        );
    }

    private function makeAdminApprover(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Admin BMN',
            'email' => 'adm-'.uniqid().'@example.test',
            'username' => 'adm'.uniqid(),
            'password' => bcrypt('secret'),
            'can_approve_bmn' => true,
        ]);
        $user->assignRole('admin');

        return $user;
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

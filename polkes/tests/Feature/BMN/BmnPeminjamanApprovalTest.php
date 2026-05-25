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

class BmnPeminjamanApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_create_peminjaman_sets_pending_and_verifikator_1_stage(): void
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
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1, $peminjaman->approval_stage);
        $this->assertNull($peminjaman->verifier_1_id);
        $this->assertNull($peminjaman->verifier_2_id);
        $this->assertNull($peminjaman->final_report_by);
    }

    public function test_full_two_verifier_approval_flow(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $verifier1 = $this->makeVerifier1();
        $verifier2 = $this->makeVerifier2();

        $this->actingAs($verifier1)
            ->post(route('bmn.peminjaman-aset.approve-stage-1', $peminjaman->id), [
                'note' => 'OK tahap 1',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_PENDING, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2, $peminjaman->approval_stage);
        $this->assertSame(User::BMN_VERIFIER_1_ID, (int) $peminjaman->verifier_1_id);
        $this->assertNotNull($peminjaman->verifier_1_approved_at);

        $this->actingAs($verifier2)
            ->post(route('bmn.peminjaman-aset.approve-stage-2', $peminjaman->id), [
                'note' => 'OK tahap 2',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_PENDING, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_REPORT, $peminjaman->approval_stage);
        $this->assertSame(User::BMN_VERIFIER_2_ID, (int) $peminjaman->verifier_2_id);
        $this->assertNotNull($peminjaman->verifier_2_approved_at);

        $this->actingAs($verifier1)
            ->post(route('bmn.peminjaman-aset.final-report', $peminjaman->id), [
                'note' => 'Pelaporan selesai',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_APPROVED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_COMPLETED, $peminjaman->approval_stage);
        $this->assertSame(User::BMN_VERIFIER_1_ID, (int) $peminjaman->final_report_by);
        $this->assertNotNull($peminjaman->final_report_at);
        $this->assertNotNull($peminjaman->approved_at);

        $aset = AsetBmn::query()->find($peminjaman->details()->value('aset_bmn_id'));
        $this->assertNotNull($aset);
        $this->assertSame('aktif', $aset->status);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $verifier1->assignRole('admin');
        $verifier1->forceFill(['can_approve_bmn' => true])->save();

        $this->actingAs($verifier1)
            ->post(route('bmn.peminjaman-aset.mark-dipinjam', $peminjaman->id))
            ->assertRedirect();

        $peminjaman->refresh();
        $aset->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_DIPINJAM, $peminjaman->status);
        $this->assertSame('dipinjam', $aset->status);
    }

    public function test_reject_at_stage_1(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $verifier1 = $this->makeVerifier1();

        $this->actingAs($verifier1)
            ->post(route('bmn.peminjaman-aset.reject-stage', $peminjaman->id), [
                'rejected_reason' => 'Dokumen tidak lengkap',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_REJECTED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_REJECTED, $peminjaman->approval_stage);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1, $peminjaman->rejected_stage);
        $this->assertSame('Dokumen tidak lengkap', $peminjaman->rejected_reason);
    }

    public function test_reject_at_stage_2(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $verifier1 = $this->makeVerifier1();
        $verifier2 = $this->makeVerifier2();

        $this->actingAs($verifier1)
            ->post(route('bmn.peminjaman-aset.approve-stage-1', $peminjaman->id));

        $this->actingAs($verifier2)
            ->post(route('bmn.peminjaman-aset.reject-stage', $peminjaman->id), [
                'rejected_reason' => 'Tidak sesuai kebijakan',
            ])
            ->assertRedirect();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::STATUS_REJECTED, $peminjaman->status);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_REJECTED, $peminjaman->approval_stage);
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2, $peminjaman->rejected_stage);
    }

    public function test_non_verifier_cannot_approve_or_finalize(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $requester = $this->makeRequesterUser();

        $this->actingAs($requester)
            ->post(route('bmn.peminjaman-aset.approve-stage-1', $peminjaman->id))
            ->assertForbidden();

        $this->actingAs($requester)
            ->post(route('bmn.peminjaman-aset.approve-stage-2', $peminjaman->id))
            ->assertForbidden();

        $this->actingAs($requester)
            ->post(route('bmn.peminjaman-aset.final-report', $peminjaman->id))
            ->assertForbidden();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1, $peminjaman->approval_stage);
    }

    public function test_verifier_cannot_skip_stage(): void
    {
        $peminjaman = $this->makePendingPeminjaman();
        $verifier2 = $this->makeVerifier2();

        $this->actingAs($verifier2)
            ->post(route('bmn.peminjaman-aset.approve-stage-2', $peminjaman->id))
            ->assertForbidden();

        $peminjaman->refresh();
        $this->assertSame(PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1, $peminjaman->approval_stage);
    }

    private function makePendingPeminjaman(): PeminjamanAsetBmn
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
            'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1,
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

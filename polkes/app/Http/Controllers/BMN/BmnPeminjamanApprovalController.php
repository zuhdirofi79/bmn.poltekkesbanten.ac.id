<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\BmnPeminjamanApprovalNoteRequest;
use App\Http\Requests\BMN\BmnPeminjamanRejectStageRequest;
use App\Models\BMN\PeminjamanAsetBmn;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Workflow approval 2 verifikator berurutan + final report peminjaman aset BMN.
 */
class BmnPeminjamanApprovalController extends Controller
{
    public function approveStage1(BmnPeminjamanApprovalNoteRequest $request, PeminjamanAsetBmn $peminjaman): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isBmnVerifier1(), 403);

        $this->assertPendingAtStage($peminjaman, PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1);

        $note = trim((string) ($request->validated()['note'] ?? ''));

        try {
            DB::transaction(function () use ($peminjaman, $user, $note): void {
                /** @var PeminjamanAsetBmn $locked */
                $locked = PeminjamanAsetBmn::query()->lockForUpdate()->findOrFail($peminjaman->id);
                $this->assertPendingAtStage($locked, PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1);

                $locked->update([
                    'verifier_1_id' => User::BMN_VERIFIER_1_ID,
                    'verifier_1_approved_at' => Carbon::now(),
                    'verifier_1_note' => $note !== '' ? $note : null,
                    'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menyetujui tahap 1: '.$e->getMessage());
        }

        return back()->with('success', 'Tahap 1 disetujui. Menunggu persetujuan Verifikator 2 (Pak Singgih).');
    }

    public function approveStage2(BmnPeminjamanApprovalNoteRequest $request, PeminjamanAsetBmn $peminjaman): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isBmnVerifier2(), 403);

        $this->assertPendingAtStage($peminjaman, PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2);

        $note = trim((string) ($request->validated()['note'] ?? ''));

        try {
            DB::transaction(function () use ($peminjaman, $note): void {
                /** @var PeminjamanAsetBmn $locked */
                $locked = PeminjamanAsetBmn::query()->lockForUpdate()->findOrFail($peminjaman->id);
                $this->assertPendingAtStage($locked, PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2);

                $locked->update([
                    'verifier_2_id' => User::BMN_VERIFIER_2_ID,
                    'verifier_2_approved_at' => Carbon::now(),
                    'verifier_2_note' => $note !== '' ? $note : null,
                    'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_REPORT,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menyetujui tahap 2: '.$e->getMessage());
        }

        return back()->with('success', 'Tahap 2 disetujui. Menunggu final report Verifikator 1 (Pak Hendri).');
    }

    public function finalReport(BmnPeminjamanApprovalNoteRequest $request, PeminjamanAsetBmn $peminjaman): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->isBmnVerifier1(), 403);

        $this->assertPendingAtStage($peminjaman, PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_REPORT);

        $note = trim((string) ($request->validated()['note'] ?? ''));

        try {
            DB::transaction(function () use ($peminjaman, $user, $note): void {
                /** @var PeminjamanAsetBmn $locked */
                $locked = PeminjamanAsetBmn::query()->lockForUpdate()->findOrFail($peminjaman->id);
                $this->assertPendingAtStage($locked, PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_REPORT);

                $now = Carbon::now();

                $locked->update([
                    'final_report_by' => User::BMN_VERIFIER_1_ID,
                    'final_report_at' => $now,
                    'final_report_note' => $note !== '' ? $note : null,
                    'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_COMPLETED,
                    'status' => PeminjamanAsetBmn::STATUS_APPROVED,
                    'approved_by' => $user->id,
                    'approved_at' => $now,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal finalisasi pelaporan: '.$e->getMessage());
        }

        return back()->with(
            'success',
            'Pelaporan difinalisasi. Peminjaman '.$peminjaman->kode_peminjaman.' disetujui. Silakan tandai sebagai dipinjam saat aset diserahkan.'
        );
    }

    public function rejectStage(BmnPeminjamanRejectStageRequest $request, PeminjamanAsetBmn $peminjaman): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $stage = (string) $peminjaman->approval_stage;
        $allowed = (
            ($stage === PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1 && $user->isBmnVerifier1())
            || ($stage === PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2 && $user->isBmnVerifier2())
        );
        abort_unless($allowed, 403);

        if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_PENDING) {
            return back()->with('error', 'Transaksi tidak lagi berstatus pending.');
        }

        $reason = trim((string) $request->validated()['rejected_reason']);

        try {
            DB::transaction(function () use ($peminjaman, $user, $stage, $reason): void {
                /** @var PeminjamanAsetBmn $locked */
                $locked = PeminjamanAsetBmn::query()->lockForUpdate()->findOrFail($peminjaman->id);

                if ($locked->status !== PeminjamanAsetBmn::STATUS_PENDING) {
                    throw new \RuntimeException('Transaksi tidak lagi berstatus pending.');
                }

                if ($locked->approval_stage !== $stage) {
                    throw new \RuntimeException('Tahap approval tidak sesuai.');
                }

                $locked->update([
                    'approval_stage' => PeminjamanAsetBmn::APPROVAL_STAGE_REJECTED,
                    'status' => PeminjamanAsetBmn::STATUS_REJECTED,
                    'rejected_stage' => $stage,
                    'rejected_reason' => $reason,
                    'rejected_by' => $user->id,
                    'rejected_at' => Carbon::now(),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menolak peminjaman: '.$e->getMessage());
        }

        return back()->with('success', 'Peminjaman '.$peminjaman->kode_peminjaman.' ditolak.');
    }

    private function assertPendingAtStage(PeminjamanAsetBmn $peminjaman, string $expectedStage): void
    {
        if ($peminjaman->status !== PeminjamanAsetBmn::STATUS_PENDING) {
            abort(403, 'Transaksi tidak berstatus pending.');
        }

        if ($peminjaman->approval_stage !== $expectedStage) {
            abort(403, 'Tahap approval tidak sesuai atau sudah diproses.');
        }
    }
}

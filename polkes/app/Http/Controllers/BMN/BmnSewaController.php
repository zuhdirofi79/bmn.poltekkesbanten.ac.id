<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Models\BMN\BmnSewaPengajuan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BmnSewaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->hasRole('admin') || $user->canApproveBmn()), 403);

        $list = BmnSewaPengajuan::query()
            ->with(['tarif:id,nama_layanan,kategori,satuan,tarif_umum,tarif_pegawai', 'adminPaymentBy:id,name'])
            ->orderByDesc('id')
            ->paginate(15);

        return view('bmn.sewa.index', compact('list'));
    }

    public function show(Request $request, int $id): View
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->hasRole('admin') || $user->canApproveBmn()), 403);

        $pengajuan = BmnSewaPengajuan::query()
            ->with(['tarif', 'adminPaymentBy:id,name'])
            ->findOrFail($id);

        $coordinationText = $this->buildCoordinationText($pengajuan);

        return view('bmn.sewa.show', compact('pengajuan', 'coordinationText'));
    }

    public function confirmPayment(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->hasRole('admin') || $user->canApproveBmn()), 403);

        $note = trim((string) $request->input('admin_payment_note', ''));

        DB::transaction(function () use ($id, $user, $note): void {
            /** @var BmnSewaPengajuan $pengajuan */
            $pengajuan = BmnSewaPengajuan::query()->lockForUpdate()->findOrFail($id);

            $pengajuan->update([
                'payment_status' => BmnSewaPengajuan::PAYMENT_CONFIRMED,
                'status' => BmnSewaPengajuan::STATUS_PAYMENT_CONFIRMED,
                'admin_payment_by' => $user->id,
                'admin_payment_at' => Carbon::now(),
                'admin_payment_note' => $note !== '' ? $note : null,
            ]);
        });

        return back()->with('success', 'Pembayaran dikonfirmasi.');
    }

    public function rejectPayment(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->hasRole('admin') || $user->canApproveBmn()), 403);

        $note = trim((string) $request->input('admin_payment_note', ''));
        if ($note === '') {
            return back()->with('error', 'Catatan penolakan wajib diisi.');
        }

        DB::transaction(function () use ($id, $user, $note): void {
            /** @var BmnSewaPengajuan $pengajuan */
            $pengajuan = BmnSewaPengajuan::query()->lockForUpdate()->findOrFail($id);

            $pengajuan->update([
                'payment_status' => BmnSewaPengajuan::PAYMENT_REJECTED,
                'status' => 'rejected',
                'admin_payment_by' => $user->id,
                'admin_payment_at' => Carbon::now(),
                'admin_payment_note' => $note,
            ]);
        });

        return back()->with('success', 'Pembayaran ditolak.');
    }

    public function markCoordinated(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->hasRole('admin') || $user->canApproveBmn()), 403);

        DB::transaction(function () use ($id): void {
            /** @var BmnSewaPengajuan $pengajuan */
            $pengajuan = BmnSewaPengajuan::query()->lockForUpdate()->findOrFail($id);

            if ($pengajuan->payment_status !== BmnSewaPengajuan::PAYMENT_CONFIRMED) {
                throw new \RuntimeException('Koordinasi hanya setelah pembayaran dikonfirmasi.');
            }

            $pengajuan->update([
                'coordination_status' => 'sent_to_team',
                'status' => 'coordinated',
            ]);
        });

        return back()->with('success', 'Status koordinasi diperbarui.');
    }

    private function buildCoordinationText(BmnSewaPengajuan $pengajuan): string
    {
        $tarif = $pengajuan->tarif;

        return implode("\n", [
            'Koordinasi Sewa BMN',
            'Kode: '.$pengajuan->kode_sewa,
            'Nama: '.$pengajuan->nama_lengkap,
            'WA: '.$pengajuan->nomor_wa,
            'Email: '.$pengajuan->email,
            'Layanan: '.($tarif->nama_layanan ?? '-').' ('.($tarif->kategori ?? '-').')',
            'Mulai: '.optional($pengajuan->tanggal_mulai_sewa)->format('Y-m-d'),
            'Kembali: '.optional($pengajuan->tanggal_rencana_kembali)->format('Y-m-d'),
            'Tujuan: '.$pengajuan->tujuan_peminjaman,
        ]);
    }
}

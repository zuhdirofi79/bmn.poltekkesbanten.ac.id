<?php

declare(strict_types=1);

namespace App\Http\Controllers\SewaAdmin;

use App\Http\Controllers\Controller;
use App\Models\BMN\BmnSewaPengajuan;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman daftar + detail + aksi (verifikasi pembayaran, koordinasi)
 * khusus modul Admin Sewa BMN.
 *
 * Aturan akses tombol:
 *   - Konfirmasi/Tolak Pembayaran  : HANYA Bu Indah (canVerifySewaPayment)
 *   - Update Koordinasi (sent/done): Bu Retno + Pak Singgih (canCoordinateSewa)
 *   - Teruskan ke Bu Indah         : Bu Retno (isRetnoSewaCoordinator)
 *
 * Middleware 'sewa.admin' di route group sudah memastikan hanya 3 user yang
 * berhak masuk; method-method di sini melapisi otorisasi per-aksi.
 */
class SewaAdminSewaController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $quick = (string) $request->query('quick', '');
        $quickMeta = $this->resolveQuickFilter($quick);

        $list = BmnSewaPengajuan::query()
            ->with([
                'tarif:id,nama_layanan,kategori,satuan,tarif_umum,tarif_pegawai',
                'adminPaymentBy:id,name',
                'coordinationBy:id,name',
            ])
            ->sewaFilter($filters)
            ->when($quickMeta !== null, function ($q) use ($quickMeta): void {
                ($quickMeta['apply'])($q);
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('sewa_admin.sewa.index', [
            'list' => $list,
            'filters' => $filters,
            'quick' => $quick,
            'quickLabel' => $quickMeta['label'] ?? '',
            'statusOptions' => $this->statusOptions(),
            'paymentOptions' => $this->paymentOptions(),
            'jenisOptions' => $this->jenisOptions(),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $pengajuan = BmnSewaPengajuan::query()
            ->with([
                'tarif',
                'adminPaymentBy:id,name',
                'paymentForwardedBy:id,name',
                'coordinationBy:id,name',
            ])
            ->findOrFail($id);

        return view('sewa_admin.sewa.show', [
            'pengajuan' => $pengajuan,
            'waMessage' => $this->buildWaMessage($pengajuan),
            'waUrl' => $this->buildWaUrl($pengajuan),
        ]);
    }

    /**
     * Bu Retno meneruskan pengajuan ke Bu Indah untuk diverifikasi pembayarannya.
     */
    public function forwardToVerifier(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isRetnoSewaCoordinator(), 403, 'Hanya Bu Retno yang dapat meneruskan ke Bu Indah.');

        DB::transaction(function () use ($id, $user): void {
            /** @var BmnSewaPengajuan $pengajuan */
            $pengajuan = BmnSewaPengajuan::query()->lockForUpdate()->findOrFail($id);

            if ($pengajuan->payment_status !== BmnSewaPengajuan::PAYMENT_PENDING) {
                throw new \RuntimeException('Hanya pengajuan dengan pembayaran pending yang dapat diteruskan.');
            }

            $pengajuan->update([
                'payment_forwarded_by' => (int) $user->id,
                'payment_forwarded_at' => Carbon::now(),
                'status' => BmnSewaPengajuan::STATUS_PAYMENT_REVIEW,
            ]);
        });

        return back()->with('success', 'Pengajuan diteruskan ke Bu Indah untuk verifikasi pembayaran.');
    }

    /**
     * Bu Indah mengkonfirmasi bahwa pembayaran sudah masuk rekening BLU.
     */
    public function confirmPayment(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canVerifySewaPayment(), 403, 'Hanya Bu Indah yang dapat mengkonfirmasi pembayaran.');

        $note = trim((string) $request->input('admin_payment_note', ''));

        DB::transaction(function () use ($id, $user, $note): void {
            /** @var BmnSewaPengajuan $pengajuan */
            $pengajuan = BmnSewaPengajuan::query()->lockForUpdate()->findOrFail($id);

            $pengajuan->update([
                'payment_status' => BmnSewaPengajuan::PAYMENT_CONFIRMED,
                'status' => BmnSewaPengajuan::STATUS_PAYMENT_CONFIRMED,
                'admin_payment_by' => (int) $user->id,
                'admin_payment_at' => Carbon::now(),
                'admin_payment_note' => $note !== '' ? $note : null,
            ]);
        });

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    /**
     * Bu Indah menolak pembayaran (mis. dana belum masuk).
     */
    public function rejectPayment(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canVerifySewaPayment(), 403, 'Hanya Bu Indah yang dapat menolak pembayaran.');

        $note = trim((string) $request->input('admin_payment_note', ''));
        if ($note === '') {
            return back()->with('error', 'Catatan penolakan wajib diisi.');
        }

        DB::transaction(function () use ($id, $user, $note): void {
            /** @var BmnSewaPengajuan $pengajuan */
            $pengajuan = BmnSewaPengajuan::query()->lockForUpdate()->findOrFail($id);

            $pengajuan->update([
                'payment_status' => BmnSewaPengajuan::PAYMENT_REJECTED,
                'status' => BmnSewaPengajuan::STATUS_REJECTED,
                'admin_payment_by' => (int) $user->id,
                'admin_payment_at' => Carbon::now(),
                'admin_payment_note' => $note,
            ]);
        });

        return back()->with('success', 'Pembayaran ditolak.');
    }

    /**
     * Bu Retno / Pak Singgih: tandai sudah diteruskan ke tim teknis.
     */
    public function markSentToTeam(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canCoordinateSewa(), 403, 'Hanya koordinator sewa yang dapat memperbarui koordinasi.');

        $note = trim((string) $request->input('coordination_note', ''));

        DB::transaction(function () use ($id, $user, $note): void {
            /** @var BmnSewaPengajuan $pengajuan */
            $pengajuan = BmnSewaPengajuan::query()->lockForUpdate()->findOrFail($id);

            if ($pengajuan->payment_status !== BmnSewaPengajuan::PAYMENT_CONFIRMED) {
                throw new \RuntimeException('Koordinasi hanya bisa dilakukan setelah pembayaran terkonfirmasi.');
            }

            $pengajuan->update([
                'coordination_status' => BmnSewaPengajuan::COORD_SENT_TO_TEAM,
                'coordination_by' => (int) $user->id,
                'coordination_at' => Carbon::now(),
                'coordination_note' => $note !== '' ? $note : $pengajuan->coordination_note,
                'status' => BmnSewaPengajuan::STATUS_COORDINATED,
            ]);
        });

        return back()->with('success', 'Status koordinasi diperbarui: diteruskan ke tim.');
    }

    /**
     * Bu Retno / Pak Singgih: tandai koordinasi sudah selesai (hari H sukses).
     */
    public function markCoordinationDone(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->canCoordinateSewa(), 403, 'Hanya koordinator sewa yang dapat memperbarui koordinasi.');

        $note = trim((string) $request->input('coordination_note', ''));

        DB::transaction(function () use ($id, $user, $note): void {
            /** @var BmnSewaPengajuan $pengajuan */
            $pengajuan = BmnSewaPengajuan::query()->lockForUpdate()->findOrFail($id);

            if ($pengajuan->payment_status !== BmnSewaPengajuan::PAYMENT_CONFIRMED) {
                throw new \RuntimeException('Koordinasi hanya bisa diselesaikan setelah pembayaran terkonfirmasi.');
            }

            $pengajuan->update([
                'coordination_status' => BmnSewaPengajuan::COORD_DONE,
                'coordination_by' => (int) $user->id,
                'coordination_at' => Carbon::now(),
                'coordination_note' => $note !== '' ? $note : $pengajuan->coordination_note,
                'status' => BmnSewaPengajuan::STATUS_COMPLETED,
            ]);
        });

        return back()->with('success', 'Koordinasi ditandai selesai.');
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        return [
            'status' => (string) $request->query('status', ''),
            'payment_status' => (string) $request->query('payment_status', ''),
            'jenis_penyewa' => (string) $request->query('jenis_penyewa', ''),
            'bulan' => (string) $request->query('bulan', ''),
            'q' => (string) $request->query('q', ''),
        ];
    }

    /**
     * Quick filter shortcut yang dipakai oleh sub-menu sidebar:
     *   - baru     : pengajuan baru masuk (pembayaran masih pending & belum diteruskan)
     *   - menunggu : sudah diteruskan Bu Retno, sedang menunggu verifikasi Bu Indah
     *   - terbayar : pembayaran sudah dikonfirmasi Bu Indah (siap koordinasi)
     *   - berjalan : sudah dikoordinasikan ke tim, belum selesai
     *   - selesai  : koordinasi sudah ditandai selesai (atau status = completed)
     *
     * Mengembalikan null jika $key bukan whitelist.
     *
     * @return array{label:string, apply:\Closure}|null
     */
    private function resolveQuickFilter(string $key): ?array
    {
        $map = [
            'baru' => [
                'label' => 'Pengajuan Baru',
                'apply' => function ($q): void {
                    $q->where('payment_status', BmnSewaPengajuan::PAYMENT_PENDING)
                        ->whereNull('payment_forwarded_at');
                },
            ],
            'menunggu' => [
                'label' => 'Menunggu Verifikasi Pembayaran',
                'apply' => function ($q): void {
                    $q->where('payment_status', BmnSewaPengajuan::PAYMENT_PENDING)
                        ->whereNotNull('payment_forwarded_at');
                },
            ],
            'terbayar' => [
                'label' => 'Sudah Terbayar',
                'apply' => function ($q): void {
                    $q->where('payment_status', BmnSewaPengajuan::PAYMENT_CONFIRMED);
                },
            ],
            'berjalan' => [
                'label' => 'Sedang Berjalan',
                'apply' => function ($q): void {
                    $q->where('payment_status', BmnSewaPengajuan::PAYMENT_CONFIRMED)
                        ->where('coordination_status', BmnSewaPengajuan::COORD_SENT_TO_TEAM);
                },
            ],
            'selesai' => [
                'label' => 'Selesai',
                'apply' => function ($q): void {
                    $q->where(function ($sub): void {
                        $sub->where('coordination_status', BmnSewaPengajuan::COORD_DONE)
                            ->orWhere('status', BmnSewaPengajuan::STATUS_COMPLETED);
                    });
                },
            ],
        ];

        return $map[$key] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            BmnSewaPengajuan::STATUS_PENDING => 'Pending',
            BmnSewaPengajuan::STATUS_PAYMENT_REVIEW => 'Diteruskan ke Bu Indah',
            BmnSewaPengajuan::STATUS_PAYMENT_CONFIRMED => 'Pembayaran Terkonfirmasi',
            BmnSewaPengajuan::STATUS_COORDINATED => 'Koordinasi Berjalan',
            BmnSewaPengajuan::STATUS_APPROVED => 'Disetujui',
            BmnSewaPengajuan::STATUS_REJECTED => 'Ditolak',
            BmnSewaPengajuan::STATUS_COMPLETED => 'Selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentOptions(): array
    {
        return [
            BmnSewaPengajuan::PAYMENT_PENDING => 'Menunggu',
            BmnSewaPengajuan::PAYMENT_CONFIRMED => 'Terbayar',
            BmnSewaPengajuan::PAYMENT_REJECTED => 'Ditolak',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function jenisOptions(): array
    {
        return [
            BmnSewaPengajuan::JENIS_UMUM => 'Umum',
            BmnSewaPengajuan::JENIS_PEGAWAI => 'Pegawai',
        ];
    }

    private function buildWaMessage(BmnSewaPengajuan $pengajuan): string
    {
        return implode("\n", [
            'Halo Bapak/Ibu '.$pengajuan->nama_lengkap.',',
            '',
            'Kami dari Admin Sewa BMN Poltekkes Banten ingin menginformasikan pengajuan sewa Anda:',
            '- Kode: '.$pengajuan->kode_sewa,
            '- Layanan: '.(optional($pengajuan->tarif)->nama_layanan ?? '-'),
            '- Mulai: '.optional($pengajuan->tanggal_mulai_sewa)->format('d-m-Y'),
            '- Status Pembayaran: '.$pengajuan->paymentStatusLabel(),
            '',
            'Mohon konfirmasi balasan untuk koordinasi lebih lanjut. Terima kasih.',
        ]);
    }

    private function buildWaUrl(BmnSewaPengajuan $pengajuan): string
    {
        $no = preg_replace('/\D+/', '', (string) $pengajuan->nomor_wa);
        if ($no === null || $no === '') {
            return '';
        }
        if (str_starts_with($no, '0')) {
            $no = '62'.substr($no, 1);
        }

        return 'https://wa.me/'.$no.'?text='.rawurlencode($this->buildWaMessage($pengajuan));
    }
}

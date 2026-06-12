<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\SignatureService;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\PersediaanAccess;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\UnitKerja;
use App\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PermintaanController extends Controller
{
    public function __construct(
        private StockService $stock,
        private SignatureService $signature,
    ) {
    }

    public function index(Request $request): View
    {
        $lockedUnit = PersediaanAccess::lockedUnitForApprover($request);
        $isSuperadmin = PersediaanAccess::isSuperadmin($request);

        $permintaan = PersediaanAccess::scopePermintaanQuery(
            PersediaanPermintaan::query()->with('pemohon'),
            $request,
        )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('nomor_permintaan', 'like', '%' . $request->input('q') . '%'))
            ->orderByRaw("FIELD(status,'menunggu_persetujuan','diajukan','disetujui','barang_dikeluarkan','selesai','ditolak','draft')")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $units = $isSuperadmin ? UnitKerja::forPublic() : ($lockedUnit ? [$lockedUnit] : UnitKerja::forPublic());

        return view('persediaan_admin.permintaan.index', [
            'permintaan'   => $permintaan,
            'units'        => $units,
            'lockedUnit'   => $lockedUnit,
            'isSuperadmin' => $isSuperadmin,
        ]);
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $admin = $this->requireAdmin($request);
        if ($admin instanceof RedirectResponse) {
            return $admin;
        }

        $userId = (int) $admin->id;

        $permintaan = PersediaanPermintaan::query()
            ->with(['items', 'pemohon', 'approver'])
            ->whereKey($id)
            ->first();

        if ($permintaan === null) {
            abort(404);
        }

        if (! PersediaanAccess::canViewPermintaan($userId, $permintaan)) {
            abort(403, 'Anda tidak memiliki akses ke permintaan ini.');
        }

        $canApprove = PersediaanAccess::canApprovePermintaan($userId, $permintaan);
        $canIssue = $permintaan->status === PersediaanPermintaan::STATUS_DISETUJUI
            && (PersediaanAccess::isSuperadminUserId($userId)
                || (int) ($permintaan->approver_user_id ?? 0) === $userId);

        return view('persediaan_admin.permintaan.show', [
            'permintaan'   => $permintaan,
            'canApprove'   => $canApprove,
            'canIssue'     => $canIssue,
        ]);
    }

    public function setujui(Request $request, int $id): RedirectResponse
    {
        $admin = $this->requireAdmin($request);
        if ($admin instanceof RedirectResponse) {
            return $admin;
        }

        $userId = (int) $admin->id;
        $permintaan = $this->findAccessible($request, $id, ['items'], $userId);

        if (! PersediaanAccess::canApprovePermintaan($userId, $permintaan)) {
            return back()->with('error', 'Anda tidak berhak menyetujui permintaan ini.');
        }

        $data = $request->validate([
            'tanda_tangan_admin'        => ['required', 'string'],
            'jumlah_disetujui'          => ['array'],
            'jumlah_disetujui.*'        => ['nullable', 'integer', 'min:0'],
            'approval_note'             => ['nullable', 'string', 'max:1000'],
        ]);

        $ttd = $this->signature->storeBase64($data['tanda_tangan_admin']);
        if ($ttd === null) {
            return back()->with('error', 'Tanda tangan admin tidak valid.');
        }

        DB::transaction(function () use ($permintaan, $data, $ttd, $userId) {
            foreach ($permintaan->items as $item) {
                $approved = $data['jumlah_disetujui'][$item->id] ?? $item->jumlah_diminta;
                $item->jumlah_disetujui = max(0, (int) $approved);
                $item->save();
            }

            $permintaan->status = PersediaanPermintaan::STATUS_DISETUJUI;
            $permintaan->approved_by = $userId;
            $permintaan->approved_at = Carbon::now();
            $permintaan->tanda_tangan_admin_path = $ttd;
            $permintaan->approval_note = $data['approval_note'] ?? null;
            $permintaan->save();

            foreach ($permintaan->items as $item) {
                $qty = (int) ($item->jumlah_disetujui ?? 0);
                if ($qty <= 0 || ! $item->barang_id) {
                    continue;
                }

                $this->stock->recordPermintaanKeluar([
                    'barang_id'  => $item->barang_id,
                    'unit_kerja' => $permintaan->unit_kerja,
                    'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
                    'ref_table'  => 'persediaan_permintaan',
                    'ref_id'     => $permintaan->id,
                    'qty_keluar' => $qty,
                    'keterangan' => 'Pengeluaran permintaan ' . $permintaan->nomor_permintaan,
                    'created_by' => $userId,
                ]);
            }
        });

        return back()->with('success', 'Permintaan disetujui dan ditandatangani.');
    }

    public function tolak(Request $request, int $id): RedirectResponse
    {
        $admin = $this->requireAdmin($request);
        if ($admin instanceof RedirectResponse) {
            return $admin;
        }

        $userId = (int) $admin->id;
        $permintaan = $this->findAccessible($request, $id, [], $userId);

        if (! PersediaanAccess::canApprovePermintaan($userId, $permintaan)) {
            return back()->with('error', 'Anda tidak berhak menolak permintaan ini.');
        }

        $data = $request->validate([
            'catatan_admin' => ['required', 'string', 'max:1000'],
        ]);

        $permintaan->status = PersediaanPermintaan::STATUS_DITOLAK;
        $permintaan->catatan_admin = $data['catatan_admin'];
        $permintaan->approval_note = $data['catatan_admin'];
        $permintaan->rejected_by = $userId;
        $permintaan->rejected_at = Carbon::now();
        $permintaan->save();

        return back()->with('success', 'Permintaan ditolak.');
    }

    /**
     * Keluarkan barang -> status selesai (stok sudah berkurang saat disetujui).
     */
    public function keluarkan(Request $request, int $id): RedirectResponse
    {
        $admin = $this->requireAdmin($request);
        if ($admin instanceof RedirectResponse) {
            return $admin;
        }

        $userId = (int) $admin->id;
        $permintaan = $this->findAccessible($request, $id, ['items'], $userId);

        if ($permintaan->status !== PersediaanPermintaan::STATUS_DISETUJUI) {
            return back()->with('error', 'Hanya permintaan berstatus disetujui yang dapat dikeluarkan.');
        }

        if (! PersediaanAccess::isSuperadminUserId($userId)
            && (int) ($permintaan->approver_user_id ?? 0) !== $userId) {
            return back()->with('error', 'Anda tidak berhak mengeluarkan barang permintaan ini.');
        }

        DB::transaction(function () use ($permintaan, $userId) {
            foreach ($permintaan->items as $item) {
                $qty = (int) ($item->jumlah_disetujui ?? $item->jumlah_diminta);
                if ($qty <= 0 || ! $item->barang_id) {
                    continue;
                }

                $this->stock->recordPermintaanKeluar([
                    'barang_id'  => $item->barang_id,
                    'unit_kerja' => $permintaan->unit_kerja,
                    'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
                    'ref_table'  => 'persediaan_permintaan',
                    'ref_id'     => $permintaan->id,
                    'qty_keluar' => $qty,
                    'keterangan' => 'Pengeluaran barang permintaan ' . $permintaan->nomor_permintaan,
                    'created_by' => $userId,
                ]);
            }

            $permintaan->status = PersediaanPermintaan::STATUS_SELESAI;
            $permintaan->save();
        });

        return back()->with('success', 'Barang dikeluarkan, permintaan selesai.');
    }

    public function pdf(Request $request, int $id): Response|RedirectResponse
    {
        $admin = $this->requireAdmin($request);
        if ($admin instanceof RedirectResponse) {
            return $admin;
        }

        $userId = (int) $admin->id;
        $permintaan = $this->findAccessible($request, $id, ['items', 'pemohon'], $userId);

        $pdf = Pdf::loadView('persediaan.permintaan.pdf', [
            'permintaan' => $permintaan,
            'pemohon'    => $permintaan->pemohon,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Permintaan-' . $permintaan->nomor_permintaan . '.pdf');
    }

    /**
     * @return User|RedirectResponse
     */
    private function requireAdmin(Request $request): User|RedirectResponse
    {
        $admin = PersediaanAdminSession::resolveUser($request);

        if ($admin === null) {
            return redirect()
                ->route('persediaan.admin.login.show')
                ->with('error', 'Silakan login admin persediaan terlebih dahulu.');
        }

        return $admin;
    }

    /**
     * @param  list<string>  $with
     */
    private function findAccessible(Request $request, int $id, array $with, int $userId): PersediaanPermintaan
    {
        $permintaan = PersediaanPermintaan::query()
            ->with($with)
            ->whereKey($id)
            ->first();

        abort_if($permintaan === null, 404);

        abort_if(
            ! PersediaanAccess::canViewPermintaan($userId, $permintaan),
            403,
            'Anda tidak memiliki akses ke permintaan ini.',
        );

        return $permintaan;
    }
}

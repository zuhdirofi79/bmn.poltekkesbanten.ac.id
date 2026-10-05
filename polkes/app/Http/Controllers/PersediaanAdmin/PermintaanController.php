<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanPermintaan;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\PersediaanDeletionService;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PermintaanController extends Controller
{
    public function __construct(
        private StockService $stock,
        private SignatureService $signature,
        private PersediaanDeletionService $deletion,
    ) {
    }

    public function index(Request $request): View
    {
        $lockedUnit = PersediaanAccess::lockedUnitForApprover($request);
        $isSuperadmin = PersediaanAccess::isSuperadmin($request);

        $lifecycle = $this->lifecycle($request);
        $base = match ($lifecycle) {
            'sampah' => PersediaanPermintaan::onlyTrashed(),
            'semua'  => PersediaanPermintaan::withTrashed(),
            default  => PersediaanPermintaan::query(),
        };

        $permintaan = PersediaanAccess::scopePermintaanQuery(
            $base->with('pemohon')->withCount('items'),
            $request,
        )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('nomor_permintaan', 'like', '%' . $request->input('q') . '%'))
            ->orderByRaw("CASE status
                WHEN 'menunggu_persetujuan' THEN 0
                WHEN 'diajukan' THEN 1
                WHEN 'disetujui' THEN 2
                WHEN 'barang_dikeluarkan' THEN 3
                WHEN 'selesai' THEN 4
                WHEN 'ditolak' THEN 5
                WHEN 'draft' THEN 6
                ELSE 7 END")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $units = $isSuperadmin ? UnitKerja::forPublic() : ($lockedUnit ? [$lockedUnit] : UnitKerja::forPublic());

        return view('persediaan_admin.permintaan.index', [
            'permintaan'   => $permintaan,
            'units'        => $units,
            'lockedUnit'   => $lockedUnit,
            'isSuperadmin' => $isSuperadmin,
            'lifecycle'    => $lifecycle,
        ]);
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $admin = $this->requireAdmin($request);
        if ($admin instanceof RedirectResponse) {
            return $admin;
        }

        $userId = (int) $admin->id;

        $permintaan = $this->findAccessible($request, $id, ['items.barang', 'pemohon', 'approver', 'rak'], $userId, true);

        $canApprove = ! $permintaan->trashed() && PersediaanAccess::canApprovePermintaan($userId, $permintaan);
        $canIssue = ! $permintaan->trashed()
            && $permintaan->status === PersediaanPermintaan::STATUS_DISETUJUI
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

        try {
            DB::transaction(function () use ($permintaan, $data, $ttd, $userId) {
                $locked = PersediaanPermintaan::query()
                    ->whereKey($permintaan->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null || ! $locked->isPending()) {
                    throw new \RuntimeException('Permintaan sudah diproses.');
                }

                $items = $locked->items()->get();
                $barangIds = $items->pluck('barang_id')->filter()->map(fn ($id) => (int) $id)->all();
                $barangs = \App\Models\Persediaan\PersediaanBarang::query()
                    ->whereIn('id', $barangIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $lines = [];
                foreach ($items as $item) {
                    $approved = $data['jumlah_disetujui'][$item->id] ?? $item->jumlah_diminta;
                    $qty = max(0, (int) $approved);
                    $item->jumlah_disetujui = $qty;
                    $barang = $item->barang_id ? $barangs->get((int) $item->barang_id) : null;
                    if ($qty > 0 && $barang !== null) {
                        $lines[] = ['barang' => $barang, 'qty' => $qty];
                    }
                }

                $insufficient = $this->stock->insufficientItemLabels($lines);
                if ($insufficient !== []) {
                    throw new \DomainException(
                        'Stok tidak mencukupi: ' . implode('; ', $insufficient) . '. Persetujuan dibatalkan, stok tidak berubah.'
                    );
                }

                foreach ($items as $item) {
                    $item->save();
                }

                $locked->status = PersediaanPermintaan::STATUS_DISETUJUI;
                $locked->approved_by = $userId;
                $locked->approved_at = Carbon::now();
                $locked->tanda_tangan_admin_path = $ttd;
                $locked->approval_note = $data['approval_note'] ?? null;
                $locked->save();

                foreach ($items as $item) {
                    $qty = (int) ($item->jumlah_disetujui ?? 0);
                    if ($qty <= 0 || ! $item->barang_id) {
                        continue;
                    }

                    $this->stock->recordPermintaanKeluar([
                        'barang_id'  => $item->barang_id,
                        'unit_kerja' => $locked->unit_kerja,
                        'tipe'       => PersediaanStockMovement::TIPE_KELUAR,
                        'ref_table'  => 'persediaan_permintaan',
                        'ref_id'     => $locked->id,
                        'qty_keluar' => $qty,
                        'keterangan' => 'Pengeluaran permintaan ' . $locked->nomor_permintaan,
                        'created_by' => $userId,
                    ]);
                }
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

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
        $permintaan = $this->findAccessible($request, $id, ['items', 'pemohon'], $userId, true);

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

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $permintaan = $this->findManaged($request, $id, false);
        $permintaan->delete();

        return back()->with('success', 'Permintaan berhasil di-soft delete.');
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $permintaan = $this->findManaged($request, $id, true);
        if ($this->deletion->permintaanIdsAffectingStock([$permintaan->id]) !== []) {
            return back()->with('error', PersediaanDeletionService::PERMINTAAN_BLOCKED);
        }

        $paths = [$permintaan->tanda_tangan_pemohon_path, $permintaan->tanda_tangan_admin_path];

        try {
            DB::transaction(function () use ($permintaan): void {
                $permintaan->items()->delete();
                $permintaan->forceDelete();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Data gagal dihapus. Silakan coba kembali.');
        }

        $this->deletion->deleteExclusiveSignatures($paths);

        return back()->with('success', 'Permintaan berhasil dihapus permanen.');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $permintaan = $this->findManaged($request, $id, true);
        if (! $permintaan->trashed()) {
            return back()->with('error', 'Permintaan ini tidak berada di sampah.');
        }

        $permintaan->restore();

        return back()->with('success', 'Permintaan berhasil dipulihkan.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        if ($ids === []) {
            return back()->with('error', 'Pilih data terlebih dahulu.');
        }

        $foundIds = $this->managedQuery($request)->whereIn('id', $ids)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($foundIds === []) {
            return back()->with('error', 'Data tidak ditemukan atau berada di luar akses Anda.');
        }

        try {
            DB::transaction(function () use ($foundIds): void {
                PersediaanPermintaan::query()->whereIn('id', $foundIds)->delete();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Data gagal dihapus. Silakan coba kembali.');
        }

        $message = count($foundIds) . ' permintaan berhasil di-soft delete.';
        $missing = count($ids) - count($foundIds);
        if ($missing > 0) {
            $message .= ' ' . $missing . ' data tidak ditemukan.';
        }

        return back()->with('success', $message);
    }

    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        if ($ids === []) {
            return back()->with('error', 'Pilih data terlebih dahulu.');
        }

        $found = $this->managedQuery($request, true)->whereIn('id', $ids)->get();
        if ($found->isEmpty()) {
            return back()->with('error', 'Data tidak ditemukan atau berada di luar akses Anda.');
        }

        $blockedLookup = array_flip($this->deletion->permintaanIdsAffectingStock(
            $found->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
        ));
        $safe = $found->reject(static fn (PersediaanPermintaan $row): bool => isset($blockedLookup[$row->id]));
        $paths = [];
        foreach ($safe as $row) {
            $paths[] = $row->tanda_tangan_pemohon_path;
            $paths[] = $row->tanda_tangan_admin_path;
        }

        try {
            DB::transaction(function () use ($safe): void {
                $safeIds = $safe->pluck('id')->all();
                if ($safeIds === []) {
                    return;
                }

                if (Schema::hasTable('persediaan_permintaan_items')) {
                    DB::table('persediaan_permintaan_items')->whereIn('permintaan_id', $safeIds)->delete();
                }
                PersediaanPermintaan::withTrashed()->whereIn('id', $safeIds)->forceDelete();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Data gagal dihapus. Silakan coba kembali.');
        }

        $this->deletion->deleteExclusiveSignatures($paths);

        $done = $safe->count();
        $blocked = count($blockedLookup);
        $parts = [];
        if ($done > 0) {
            $parts[] = $done . ' permintaan berhasil dihapus permanen.';
        }
        if ($blocked > 0) {
            $parts[] = $blocked . ' permintaan tidak dapat dihapus karena sudah memengaruhi stok.';
        }
        $missing = count($ids) - $found->count();
        if ($missing > 0) {
            $parts[] = $missing . ' data tidak ditemukan.';
        }

        return back()->with($done > 0 ? 'success' : 'error', implode(' ', $parts));
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $ids = $this->deletion->normalizeIds((array) $request->input('ids', []));
        if ($ids === []) {
            return back()->with('error', 'Pilih data terlebih dahulu.');
        }

        $foundIds = $this->managedQuery($request, true)->onlyTrashed()->whereIn('id', $ids)->pluck('id')->all();
        if ($foundIds === []) {
            return back()->with('error', 'Data tidak ditemukan atau berada di luar akses Anda.');
        }

        PersediaanPermintaan::onlyTrashed()->whereIn('id', $foundIds)->restore();

        return back()->with('success', count($foundIds) . ' permintaan berhasil dipulihkan.');
    }

    /**
     * @param  list<string>  $with
     */
    private function findAccessible(Request $request, int $id, array $with, int $userId, bool $withTrashed = false): PersediaanPermintaan
    {
        $query = $withTrashed ? PersediaanPermintaan::withTrashed() : PersediaanPermintaan::query();
        $permintaan = $query
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

    private function findManaged(Request $request, int $id, bool $withTrashed): PersediaanPermintaan
    {
        $userId = PersediaanAdminSession::userId($request);
        abort_if($userId === null, 403);

        return $this->findAccessible($request, $id, [], $userId, $withTrashed);
    }

    /**
     * @return Builder<PersediaanPermintaan>
     */
    private function managedQuery(Request $request, bool $withTrashed = false): Builder
    {
        $query = $withTrashed ? PersediaanPermintaan::withTrashed() : PersediaanPermintaan::query();

        return PersediaanAccess::scopePermintaanQuery($query, $request);
    }

    private function lifecycle(Request $request): string
    {
        $lifecycle = (string) $request->input('lifecycle', 'aktif');

        return in_array($lifecycle, ['aktif', 'sampah', 'semua'], true) ? $lifecycle : 'aktif';
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanRak;
use App\Services\Persediaan\PersediaanRackService;
use App\Support\Persediaan\PersediaanAccess;
use App\Support\Persediaan\PersediaanAdminSession;
use App\Support\Persediaan\RackAccess;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MasterRakController extends Controller
{
    public function __construct(private PersediaanRackService $racks)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $query = PersediaanRak::query()
            ->withCount('assignments')
            ->whereIn('unit_kerja', RackAccess::enabledUnits())
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_rak', 'like', $term)->orWhere('nama_rak', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        $raks = $query->orderBy('kode_rak')->paginate(20)->withQueryString();
        $akses = $this->racks->ensureAkses(UnitKerja::DIREKTORAT, PersediaanAdminSession::userId($request));

        return view('persediaan_admin.master-rak.index', [
            'raks'     => $raks,
            'akses'    => $akses,
            'aksesUrl' => $this->racks->aksesPublicUrl($akses->token),
            'aksesQr'  => $this->racks->qrFileUrl($akses->qr_path),
            'filters'  => $request->only(['q', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $data = $request->validate([
            'kode_rak'      => ['required', 'string', 'max:50', 'unique:persediaan_rak,kode_rak'],
            'nama_rak'      => ['required', 'string', 'max:150'],
            'lokasi_detail' => ['nullable', 'string', 'max:255'],
            'unit_kerja'    => ['required', Rule::in(RackAccess::enabledUnits())],
        ]);

        $this->racks->createRak($data, PersediaanAdminSession::userId($request));

        return back()->with('success', 'Rak berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $rak = $this->findEnabledRak($id);
        $data = $request->validate([
            'kode_rak'      => ['required', 'string', 'max:50', Rule::unique('persediaan_rak', 'kode_rak')->ignore($rak->id)],
            'nama_rak'      => ['required', 'string', 'max:150'],
            'lokasi_detail' => ['nullable', 'string', 'max:255'],
            'unit_kerja'    => ['required', Rule::in(RackAccess::enabledUnits())],
            'status'        => ['required', Rule::in([PersediaanRak::STATUS_AKTIF, PersediaanRak::STATUS_NONAKTIF])],
        ]);

        $this->racks->updateRak($rak, $data, PersediaanAdminSession::userId($request));

        return back()->with('success', 'Rak berhasil diperbarui.');
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $rak = $this->findEnabledRak($id);
        $items = $this->racks->itemsWithStock($rak);
        $occupied = $rak->assignments()->pluck('barang_id')->all();

        $candidates = PersediaanBarang::query()
            ->where('unit_kerja', $rak->unit_kerja)
            ->where('is_active', true)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)->orWhere('nama_barang', 'like', $term));
            })
            ->orderBy('kode_barang')
            ->limit(80)
            ->get();

        $otherRaks = PersediaanRak::query()
            ->where('unit_kerja', $rak->unit_kerja)
            ->where('id', '!=', $rak->id)
            ->where('status', PersediaanRak::STATUS_AKTIF)
            ->orderBy('kode_rak')
            ->get();

        return view('persediaan_admin.master-rak.show', [
            'rak'        => $rak,
            'items'      => $items,
            'candidates' => $candidates,
            'occupied'   => $occupied,
            'otherRaks'  => $otherRaks,
            'qrUrl'      => $this->racks->qrFileUrl($rak->qr_path),
            'publicUrl'  => $this->racks->rakPublicUrl($rak->qr_token),
        ]);
    }

    public function assignBarang(Request $request, int $id): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $rak = $this->findEnabledRak($id);
        $data = $request->validate([
            'barang_id'   => ['required_without:barang_ids', 'integer'],
            'barang_ids'  => ['required_without:barang_id', 'array'],
            'barang_ids.*'=> ['integer'],
        ]);

        $ids = $data['barang_ids'] ?? [(int) $data['barang_id']];

        try {
            $this->racks->assignBarangs($rak, $ids, PersediaanAdminSession::userId($request));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Barang rak berhasil diperbarui.');
    }

    public function removeBarang(Request $request, int $id): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $rak = $this->findEnabledRak($id);
        $data = $request->validate([
            'barang_id' => ['required', 'integer'],
        ]);

        $this->racks->removeBarang($rak, (int) $data['barang_id'], PersediaanAdminSession::userId($request));

        return back()->with('success', 'Barang dikeluarkan dari rak.');
    }

    public function mutasi(Request $request): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $data = $request->validate([
            'barang_id' => ['required', 'integer'],
            'to_rak_id' => ['required', 'integer'],
            'catatan'   => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->racks->moveBarang(
                (int) $data['barang_id'],
                (int) $data['to_rak_id'],
                PersediaanAdminSession::userId($request),
                $data['catatan'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Barang dipindahkan ke rak lain. Histori mutasi tersimpan.');
    }

    public function generateQr(Request $request, int $id): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $rak = $this->findEnabledRak($id);
        $tokenBefore = (string) $rak->qr_token;

        try {
            $this->racks->generateRakQr($rak);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal generate QR rak: ' . $e->getMessage());
        }

        $rak->refresh();
        if ($tokenBefore !== '' && $tokenBefore !== (string) $rak->qr_token) {
            return back()->with('error', 'Token QR berubah secara tidak terduga.');
        }

        return back()->with('success', 'QR rak ' . $rak->kode_rak . ' berhasil dibuat ulang (token tetap sama).');
    }

    public function generateAllQr(Request $request): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        @set_time_limit(180);
        $raks = PersediaanRak::query()->whereIn('unit_kerja', RackAccess::enabledUnits())->get();
        $ok = 0;
        $fail = 0;
        foreach ($raks as $rak) {
            $tokenBefore = (string) $rak->qr_token;
            try {
                $this->racks->generateRakQr($rak);
                if ($tokenBefore !== '' && $tokenBefore !== (string) $rak->fresh()?->qr_token) {
                    $fail++;
                    continue;
                }
                $ok++;
            } catch (\Throwable $e) {
                report($e);
                $fail++;
            }
        }

        $msg = "QR rak dibuat ulang untuk {$ok} rak (token tidak berubah).";
        if ($fail > 0) {
            $msg .= " {$fail} gagal.";
        }

        return back()->with($fail > 0 ? 'error' : 'success', $msg);
    }

    public function downloadQr(Request $request, int $id): BinaryFileResponse|RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $rak = $this->findEnabledRak($id);
        $path = $rak->qr_path ? \App\Support\Persediaan\PublicUploadPath::firstExistingFile($rak->qr_path) : null;
        if ($path === null) {
            return back()->with('error', 'File QR belum ada. Generate QR terlebih dahulu.');
        }

        return response()->download($path, 'qr-rak-' . $rak->kode_rak . '.png');
    }

    public function print(Request $request): View|RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $query = PersediaanRak::query()->whereIn('unit_kerja', RackAccess::enabledUnits());
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }
        $raks = $query->orderBy('kode_rak')->get();

        $items = $raks->map(fn (PersediaanRak $rak) => [
            'rak'      => $rak,
            'imageUrl' => $this->racks->qrFileUrl($rak->qr_path),
        ])->filter(fn ($row) => $row['imageUrl'] !== null);

        if ($items->isEmpty()) {
            return back()->with('error', 'Belum ada QR rak yang siap dicetak. Generate QR terlebih dahulu.');
        }

        return view('persediaan_admin.master-rak.print-qr', [
            'items' => $items,
        ]);
    }

    public function generateAksesQr(Request $request): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $akses = $this->racks->ensureAkses(UnitKerja::DIREKTORAT, PersediaanAdminSession::userId($request));
        $tokenBefore = (string) $akses->token;

        try {
            $this->racks->generateAksesQr($akses);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal generate QR pintu: ' . $e->getMessage());
        }

        $akses->refresh();
        if ($tokenBefore !== '' && $tokenBefore !== (string) $akses->token) {
            return back()->with('error', 'Token QR pintu berubah secara tidak terduga.');
        }

        return back()->with('success', 'QR Pintu berhasil dibuat ulang (token tetap sama).');
    }

    public function rotateAksesToken(Request $request): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $akses = $this->racks->ensureAkses(UnitKerja::DIREKTORAT, PersediaanAdminSession::userId($request));
        $this->racks->rotateAksesToken($akses, PersediaanAdminSession::userId($request));

        return back()->with('success', 'Token QR Pintu diganti. QR lama tidak berlaku.');
    }

    public function rotateRakToken(Request $request, int $id): RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $rak = $this->findEnabledRak($id);
        $this->racks->rotateRakToken($rak, PersediaanAdminSession::userId($request));

        return back()->with('success', 'Token QR rak diganti. QR lama tidak berlaku.');
    }

    public function downloadAksesQr(Request $request): BinaryFileResponse|RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $akses = $this->racks->ensureAkses(UnitKerja::DIREKTORAT, PersediaanAdminSession::userId($request));
        $path = $akses->qr_path ? \App\Support\Persediaan\PublicUploadPath::firstExistingFile($akses->qr_path) : null;
        if ($path === null) {
            return back()->with('error', 'File QR Pintu belum ada. Generate QR terlebih dahulu.');
        }

        return response()->download($path, 'qr-pintu-direktorat.png');
    }

    public function printAkses(Request $request): View|RedirectResponse
    {
        $gate = $this->authorizeRak($request);
        if ($gate instanceof RedirectResponse) {
            return $gate;
        }

        $akses = $this->racks->ensureAkses(UnitKerja::DIREKTORAT, PersediaanAdminSession::userId($request));
        $imageUrl = $this->racks->qrFileUrl($akses->qr_path);
        if ($imageUrl === null) {
            return back()->with('error', 'File QR Pintu belum ada. Generate QR terlebih dahulu.');
        }

        return view('persediaan_admin.master-rak.print-akses', [
            'akses'    => $akses,
            'imageUrl' => $imageUrl,
            'publicUrl'=> $this->racks->aksesPublicUrl($akses->token),
        ]);
    }

    private function authorizeRak(Request $request): ?RedirectResponse
    {
        if (! RackAccess::adminCanManage($request)) {
            abort(403, 'Master Rak hanya untuk unit yang mengaktifkan sistem rak (Direktorat).');
        }

        return null;
    }

    private function findEnabledRak(int $id): PersediaanRak
    {
        $rak = PersediaanRak::query()->whereKey($id)->firstOrFail();
        if (! RackAccess::isEnabledForUnit((string) $rak->unit_kerja)) {
            abort(404);
        }

        return $rak;
    }
}

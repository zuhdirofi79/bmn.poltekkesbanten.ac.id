<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\StoreMutasiRequest;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\LokasiAset;
use App\Models\BMN\MutasiAset;
use App\Observers\BMN\AsetBmnObserver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MutasiController extends Controller
{
    public function index(Request $request): View
    {
        $asetId = $request->get('aset_id');

        $query = MutasiAset::query()
            ->with(['aset', 'dariLokasi', 'keLokasi'])
            ->orderByDesc('tanggal_mutasi')
            ->orderByDesc('id');

        if ($asetId) {
            $query->where('aset_id', $asetId);
        }

        $mutasi = $query->paginate(25)->appends($request->query());

        return view('bmn.mutasi.index', compact('mutasi', 'asetId'));
    }

    public function create(Request $request): View
    {
        $asetQuery = AsetBmn::query()
            ->with(['lokasiMaster', 'kategori'])
            ->where('status', 'aktif')
            ->orderBy('kode_barang');

        if ($request->filled('aset_id')) {
            $asetQuery->where('id', $request->get('aset_id'));
        }

        $asetList = $asetQuery->get();
        $lokasi = LokasiAset::orderBy('nama_lokasi')->get();
        $selectedAsetId = $request->get('aset_id');

        return view('bmn.mutasi.create', compact('asetList', 'lokasi', 'selectedAsetId'));
    }

    public function store(StoreMutasiRequest $request): RedirectResponse
    {
        $data = $request->validated();

        /** @var AsetBmn $aset */
        $aset = AsetBmn::query()->findOrFail($data['aset_id']);
        if ($aset->status !== 'aktif') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Hanya aset berstatus aktif yang dapat dimutasikan.');
        }

        DB::transaction(function () use ($data): void {
            $aset = AsetBmn::query()->lockForUpdate()->findOrFail($data['aset_id']);

            $dariId = $aset->lokasi_id;
            $dariNama = optional(LokasiAset::query()->find($dariId))->nama_lokasi ?? ('ID '.$dariId);
            $ke = LokasiAset::query()->find($data['ke_lokasi_id']);
            $keNama = $ke ? $ke->nama_lokasi : (string) $data['ke_lokasi_id'];

            MutasiAset::create([
                'aset_id' => $aset->id,
                'dari_lokasi_id' => $dariId,
                'ke_lokasi_id' => $data['ke_lokasi_id'],
                'tanggal_mutasi' => $data['tanggal_mutasi'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $aset->withoutEvents(function () use ($aset, $data): void {
                $aset->lokasi_id = $data['ke_lokasi_id'];
                $aset->save();
            });

            $fresh = AsetBmn::query()->findOrFail($aset->id);
            AsetBmnObserver::logMutasi(
                $fresh,
                (int) $dariId,
                (int) $data['ke_lokasi_id'],
                $dariNama,
                $keNama,
                $data['keterangan'] ?? null,
                ['tanggal_mutasi' => $data['tanggal_mutasi']]
            );
        });

        return redirect()
            ->route('bmn.mutasi.index')
            ->with('success', 'Mutasi lokasi aset berhasil disimpan.');
    }
}

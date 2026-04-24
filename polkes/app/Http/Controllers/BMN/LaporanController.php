<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Models\BMN\AsetBmn;
use App\Models\BMN\LokasiAset;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaporanController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['lokasi_id', 'lokasi', 'kondisi', 'status']);

        $query = AsetBmn::query()
            ->with(['kategori', 'lokasiMaster'])
            ->filter($filters);

        $totalNilai = (clone $query)->sum('nilai_perolehan');

        $aset = $query
            ->orderBy('kode_barang')
            ->paginate(50)
            ->appends($filters);
        $lokasiList = LokasiAset::orderBy('nama_lokasi')->get();

        return view('bmn.laporan.index', compact('aset', 'lokasiList', 'filters', 'totalNilai'));
    }
}

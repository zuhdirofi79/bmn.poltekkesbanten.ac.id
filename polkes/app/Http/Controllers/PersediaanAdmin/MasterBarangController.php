<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterBarangController extends Controller
{
    public function index(Request $request): View
    {
        $barang = PersediaanBarang::query()
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_kerja', $request->input('unit')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('kode_barang', 'like', $term)->orWhere('nama_barang', 'like', $term));
            })
            ->orderBy('unit_kerja')->orderBy('kode_barang')
            ->paginate(20)->withQueryString();

        return view('persediaan_admin.master-barang.index', [
            'barang'  => $barang,
            'units'   => UnitKerja::all(),
            'filters' => $request->only(['unit', 'q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $barang = PersediaanBarang::create($data + ['is_active' => true]);

            if ((int) $data['stok_awal'] > 0) {
                PersediaanStockMovement::create([
                    'barang_id'  => $barang->id,
                    'unit_kerja' => $barang->unit_kerja,
                    'tipe'       => PersediaanStockMovement::TIPE_STOK_AWAL,
                    'ref_table'  => 'persediaan_barang',
                    'ref_id'     => $barang->id,
                    'qty_masuk'  => (int) $data['stok_awal'],
                    'keterangan' => 'Stok awal master barang',
                ]);
            }
        });

        return back()->with('success', 'Barang berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $barang = PersediaanBarang::findOrFail($id);
        $data = $this->validateData($request, $id);

        $barang->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $barang = PersediaanBarang::findOrFail($id);
        // Non-destruktif: nonaktifkan agar histori stok tetap utuh.
        $barang->is_active = false;
        $barang->save();

        return back()->with('success', 'Barang dinonaktifkan.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'kode_barang'  => ['required', 'string', 'max:100'],
            'nama_barang'  => ['required', 'string', 'max:200'],
            'satuan'       => ['required', 'string', 'max:50'],
            'unit_kerja'   => ['required', Rule::in(UnitKerja::all())],
            'stok_awal'    => ['required', 'integer', 'min:0'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);
    }
}

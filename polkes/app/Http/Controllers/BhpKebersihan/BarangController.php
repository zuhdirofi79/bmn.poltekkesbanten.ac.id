<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihan;

use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Models\BhpKebersihan\BhpKebersihanPermintaan;
use App\Services\BhpKebersihan\NomorPermintaanService;
use App\Services\BhpKebersihan\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BarangController extends Controller
{
    public function __construct(
        private StockService $stock,
        private NomorPermintaanService $nomor,
    ) {
    }

    public function show(string $token): View
    {
        $barang = BhpKebersihanBarang::where('barcode_token', $token)
            ->where('status', 'aktif')
            ->firstOrFail();

        $stokTersedia = $this->stock->stokSaatIni($barang);

        return view('bhp_kebersihan.public.barang', [
            'barang'       => $barang,
            'stokTersedia' => $stokTersedia,
            'today'        => Carbon::now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $barang = BhpKebersihanBarang::where('barcode_token', $token)
            ->where('status', 'aktif')
            ->firstOrFail();

        $stokTersedia = $this->stock->stokSaatIni($barang);

        $data = $request->validate([
            'nama_pengambil'     => ['required', 'string', 'max:200'],
            'no_wa_pengambil'    => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            'jumlah_diminta'     => ['required', 'integer', 'min:1', 'max:' . max(1, $stokTersedia)],
            'catatan'            => ['nullable', 'string', 'max:2000'],
        ], [
            'no_wa_pengambil.regex' => 'Nomor WhatsApp harus format Indonesia yang valid (contoh: 08123456789).',
            'jumlah_diminta.max'    => 'Jumlah melebihi stok tersedia (' . $stokTersedia . ').',
        ]);

        $permintaan = DB::transaction(function () use ($barang, $data) {
            return BhpKebersihanPermintaan::create([
                'nomor_permintaan'   => $this->nomor->generate(),
                'barang_id'          => $barang->id,
                'nama_pengambil'     => $data['nama_pengambil'],
                'no_wa_pengambil'    => preg_replace('/[^0-9+]/', '', $data['no_wa_pengambil']),
                'tanggal_pengambilan'=> Carbon::today(),
                'jumlah_diminta'     => (int) $data['jumlah_diminta'],
                'catatan'            => isset($data['catatan']) ? trim($data['catatan']) : null,
                'status'             => BhpKebersihanPermintaan::STATUS_PENDING,
            ]);
        });

        return redirect()->route('public.bhp-kebersihan.success', $permintaan->nomor_permintaan)
            ->with('nomor_permintaan', $permintaan->nomor_permintaan);
    }

    public function success(string $nomor): View
    {
        $permintaan = BhpKebersihanPermintaan::where('nomor_permintaan', $nomor)->firstOrFail();

        return view('bhp_kebersihan.public.success', [
            'permintaan' => $permintaan,
        ]);
    }
}

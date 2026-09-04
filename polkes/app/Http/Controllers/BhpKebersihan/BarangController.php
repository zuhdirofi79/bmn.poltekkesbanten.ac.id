<?php

declare(strict_types=1);

namespace App\Http\Controllers\BhpKebersihan;

use App\Http\Controllers\Controller;
use App\Models\BhpKebersihan\BhpKebersihanBarang;
use App\Models\BhpKebersihan\BhpKebersihanPermintaan;
use App\Services\BhpKebersihan\BarcodeService;
use App\Services\BhpKebersihan\NomorPermintaanService;
use App\Services\BhpKebersihan\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BarangController extends Controller
{
    public function __construct(
        private StockService $stock,
        private NomorPermintaanService $nomor,
        private BarcodeService $barcode,
    ) {
    }

    public function show(string $token): Response
    {
        $barang = BhpKebersihanBarang::where('barcode_token', $token)
            ->where('status', 'aktif')
            ->firstOrFail();

        $stokTersedia = $this->stock->stokSaatIni($barang);

        $html = view('bhp_kebersihan.public.barang', [
            'barang'       => $barang,
            'stokTersedia' => $stokTersedia,
            'today'        => Carbon::now()->format('Y-m-d'),
            'barcodeUrl'   => $this->barcode->fileUrl($barang),
            'fotoUrl'      => $barang->fotoUrl(),
        ])->render();

        return response($html, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
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
            'jumlah_diminta'     => ['required', 'integer', 'min:1'],
            'catatan'            => ['nullable', 'string', 'max:2000'],
        ], [
            'no_wa_pengambil.regex' => 'Nomor WhatsApp harus format Indonesia yang valid (contoh: 08123456789).',
        ]);

        if ((int) $data['jumlah_diminta'] > $stokTersedia) {
            return back()
                ->withErrors(['jumlah_diminta' => 'Jumlah pengambilan melebihi stok tersedia.'])
                ->withInput();
        }

        try {
            $permintaan = DB::transaction(function () use ($barang, $data) {
                $locked = BhpKebersihanBarang::query()->whereKey($barang->id)->lockForUpdate()->firstOrFail();
                $stok = $this->stock->stokSaatIni($locked);

                if ((int) $data['jumlah_diminta'] > $stok) {
                    throw new \InvalidArgumentException('Jumlah pengambilan melebihi stok tersedia.');
                }

                return BhpKebersihanPermintaan::create([
                    'nomor_permintaan'   => $this->nomor->generate(),
                    'barang_id'          => $locked->id,
                    'nama_pengambil'     => $data['nama_pengambil'],
                    'no_wa_pengambil'    => preg_replace('/[^0-9+]/', '', $data['no_wa_pengambil']),
                    'tanggal_pengambilan'=> Carbon::today(),
                    'jumlah_diminta'     => (int) $data['jumlah_diminta'],
                    'catatan'            => isset($data['catatan']) ? trim($data['catatan']) : null,
                    'status'             => BhpKebersihanPermintaan::STATUS_PENDING,
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['jumlah_diminta' => $e->getMessage()])
                ->withInput();
        }

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

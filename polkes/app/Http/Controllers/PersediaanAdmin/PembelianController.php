<?php

declare(strict_types=1);

namespace App\Http\Controllers\PersediaanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanPembelian;
use App\Models\Persediaan\PersediaanPembelianItem;
use App\Models\Persediaan\PersediaanStockMovement;
use App\Services\Persediaan\StockService;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PembelianController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(): View
    {
        $pembelian = PersediaanPembelian::orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(15);

        return view('persediaan_admin.pembelian.index', [
            'pembelian' => $pembelian,
        ]);
    }

    public function create(): View
    {
        $barangs = PersediaanBarang::where('is_active', true)
            ->orderBy('unit_kerja')->orderBy('kode_barang')->get();

        return view('persediaan_admin.pembelian.create', [
            'units'      => UnitKerja::all(),
            'barangJson' => $barangs->groupBy('unit_kerja')->map(function ($g) {
                return $g->map(fn ($b) => [
                    'id'      => $b->id,
                    'kode'    => $b->kode_barang,
                    'nama'    => $b->nama_barang,
                    'satuan'  => $b->satuan,
                    'harga'   => (float) $b->harga_satuan,
                    'payload' => app(\App\Services\Persediaan\PersediaanCodeService::class)->payloadFor($b),
                ])->values();
            }),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nomor_faktur'           => ['required', 'string', 'max:100'],
            'tanggal'                => ['required', 'date'],
            'penyedia'               => ['nullable', 'string', 'max:150'],
            'unit_kerja'             => ['required', 'in:' . implode(',', UnitKerja::all())],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.barang_id'      => ['nullable', 'integer'],
            'items.*.kode_barang'    => ['nullable', 'string', 'max:100'],
            'items.*.nama_barang'    => ['nullable', 'string', 'max:200'],
            'items.*.satuan'         => ['nullable', 'string', 'max:50'],
            'items.*.harga_satuan'   => ['nullable', 'numeric', 'min:0'],
            'items.*.jumlah'         => ['nullable', 'integer', 'min:0'],
            'faktur'                 => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'bast'                   => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'invoice'                => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'foto_barang'            => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $rows = $this->normalizeItems($data['items'], $data['unit_kerja']);
        if (empty($rows)) {
            return back()->withInput()->withErrors(['items' => 'Minimal satu item dengan jumlah > 0.']);
        }

        $paths = [
            'faktur_path'      => $this->storeFile($request, 'faktur'),
            'bast_path'        => $this->storeFile($request, 'bast'),
            'invoice_path'     => $this->storeFile($request, 'invoice'),
            'foto_barang_path' => $this->storeFile($request, 'foto_barang'),
        ];

        DB::transaction(function () use ($data, $rows, $paths, $request) {
            $total = array_sum(array_column($rows, 'subtotal'));

            $pembelian = PersediaanPembelian::create(array_merge([
                'nomor_faktur' => $data['nomor_faktur'],
                'penyedia'     => $data['penyedia'] ?? null,
                'tanggal'      => $data['tanggal'],
                'unit_kerja'   => $data['unit_kerja'],
                'total'        => $total,
                'created_by'   => $request->user()->id,
            ], $paths));

            foreach ($rows as $row) {
                PersediaanPembelianItem::create([
                    'pembelian_id' => $pembelian->id,
                    'barang_id'    => $row['barang_id'],
                    'kode_barang'  => $row['kode_barang'],
                    'nama_barang'  => $row['nama_barang'],
                    'satuan'       => $row['satuan'],
                    'harga_satuan' => $row['harga_satuan'],
                    'jumlah'       => $row['jumlah'],
                    'subtotal'     => $row['subtotal'],
                ]);

                if ($row['barang_id']) {
                    $this->stock->recordMovement([
                        'barang_id'  => $row['barang_id'],
                        'unit_kerja' => $data['unit_kerja'],
                        'tipe'       => PersediaanStockMovement::TIPE_PEMBELIAN,
                        'ref_table'  => 'persediaan_pembelian',
                        'ref_id'     => $pembelian->id,
                        'qty_masuk'  => $row['jumlah'],
                        'keterangan' => 'Pembelian faktur ' . $pembelian->nomor_faktur,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }
        });

        return redirect()->route('persediaan.admin.pembelian.index')
            ->with('success', 'Pembelian disimpan. Stok bertambah otomatis.');
    }

    public function show(int $id): View
    {
        $pembelian = PersediaanPembelian::with('items')->findOrFail($id);

        return view('persediaan_admin.pembelian.show', [
            'pembelian' => $pembelian,
        ]);
    }

    public function importTemplate()
    {
        $headings = ['kode_barang', 'nama_barang', 'satuan', 'harga_satuan', 'jumlah'];
        $sample = [['ATK001', 'Pulpen', 'Buah', 2500, 100]];

        return Excel::download(new class($headings, $sample) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(private array $headings, private array $rows) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        }, 'template-pembelian-persediaan.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nomor_faktur' => ['required', 'string', 'max:100'],
            'tanggal'      => ['required', 'date'],
            'penyedia'     => ['nullable', 'string', 'max:150'],
            'unit_kerja'   => ['required', 'in:' . implode(',', UnitKerja::all())],
            'file'         => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $sheets = Excel::toCollection(null, $request->file('file'));
        $sheet = $sheets->first() ?? collect();

        // Lewati baris header
        $items = [];
        foreach ($sheet as $idx => $row) {
            if ($idx === 0) {
                continue;
            }
            $items[] = [
                'kode_barang'  => (string) ($row[0] ?? ''),
                'nama_barang'  => (string) ($row[1] ?? ''),
                'satuan'       => (string) ($row[2] ?? 'Buah'),
                'harga_satuan' => (float) ($row[3] ?? 0),
                'jumlah'       => (int) ($row[4] ?? 0),
            ];
        }

        $rows = $this->normalizeItems($items, $data['unit_kerja']);
        if (empty($rows)) {
            return back()->with('error', 'Tidak ada baris valid pada file Excel.');
        }

        DB::transaction(function () use ($data, $rows, $request) {
            $total = array_sum(array_column($rows, 'subtotal'));
            $pembelian = PersediaanPembelian::create([
                'nomor_faktur' => $data['nomor_faktur'],
                'penyedia'     => $data['penyedia'] ?? null,
                'tanggal'      => $data['tanggal'],
                'unit_kerja'   => $data['unit_kerja'],
                'total'        => $total,
                'created_by'   => $request->user()->id,
            ]);

            foreach ($rows as $row) {
                PersediaanPembelianItem::create([
                    'pembelian_id' => $pembelian->id,
                    'barang_id'    => $row['barang_id'],
                    'kode_barang'  => $row['kode_barang'],
                    'nama_barang'  => $row['nama_barang'],
                    'satuan'       => $row['satuan'],
                    'harga_satuan' => $row['harga_satuan'],
                    'jumlah'       => $row['jumlah'],
                    'subtotal'     => $row['subtotal'],
                ]);

                if ($row['barang_id']) {
                    $this->stock->recordMovement([
                        'barang_id'  => $row['barang_id'],
                        'unit_kerja' => $data['unit_kerja'],
                        'tipe'       => PersediaanStockMovement::TIPE_PEMBELIAN,
                        'ref_table'  => 'persediaan_pembelian',
                        'ref_id'     => $pembelian->id,
                        'qty_masuk'  => $row['jumlah'],
                        'keterangan' => 'Import pembelian faktur ' . $pembelian->nomor_faktur,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }
        });

        return redirect()->route('persediaan.admin.pembelian.index')
            ->with('success', 'Import pembelian berhasil. Stok bertambah otomatis.');
    }

    /**
     * Normalisasi & cocokkan item ke master barang (per unit) agar barang_id terisi
     * untuk pergerakan stok otomatis.
     *
     * @return list<array{barang_id:?int,kode_barang:string,nama_barang:string,satuan:string,harga_satuan:float,jumlah:int,subtotal:float}>
     */
    private function normalizeItems(array $items, string $unit): array
    {
        $master = PersediaanBarang::where('unit_kerja', $unit)->get()->keyBy(fn ($b) => strtoupper($b->kode_barang));
        $masterById = PersediaanBarang::where('unit_kerja', $unit)->get()->keyBy('id');

        $rows = [];
        foreach ($items as $row) {
            $jumlah = (int) ($row['jumlah'] ?? 0);
            if ($jumlah <= 0) {
                continue;
            }

            $barang = null;
            if (! empty($row['barang_id']) && $masterById->has((int) $row['barang_id'])) {
                $barang = $masterById->get((int) $row['barang_id']);
            } elseif (! empty($row['kode_barang']) && $master->has(strtoupper((string) $row['kode_barang']))) {
                $barang = $master->get(strtoupper((string) $row['kode_barang']));
            }

            $kode = $barang->kode_barang ?? (string) ($row['kode_barang'] ?? '');
            $nama = $barang->nama_barang ?? (string) ($row['nama_barang'] ?? '');
            $satuan = $row['satuan'] ?? ($barang->satuan ?? 'Buah');
            $harga = (float) ($row['harga_satuan'] ?? ($barang->harga_satuan ?? 0));

            if ($kode === '' && $nama === '') {
                continue;
            }

            $rows[] = [
                'barang_id'    => $barang->id ?? null,
                'kode_barang'  => $kode,
                'nama_barang'  => $nama,
                'satuan'       => $satuan,
                'harga_satuan' => $harga,
                'jumlah'       => $jumlah,
                'subtotal'     => $harga * $jumlah,
            ];
        }

        return $rows;
    }

    private function storeFile(Request $request, string $field): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $dir = public_path('uploads/persediaan/pembelian');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $request->file($field);
        $name = $field . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $name);

        return 'uploads/persediaan/pembelian/' . $name;
    }
}

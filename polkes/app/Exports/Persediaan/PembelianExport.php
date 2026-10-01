<?php

declare(strict_types=1);

namespace App\Exports\Persediaan;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PembelianExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithColumnFormatting, WithEvents
{
    /**
     * @param  list<array<int,mixed>>  $rows
     */
    public function __construct(private array $rows)
    {
    }

    /**
     * @param  Collection<int,mixed>|\Illuminate\Database\Eloquent\Collection<int,mixed>  $pembelians
     */
    public static function fromModels($pembelians): self
    {
        $rows = [];
        $no = 1;

        foreach ($pembelians as $pembelian) {
            foreach ($pembelian->items as $item) {
                $tanggal = $pembelian->tanggal;
                $rows[] = [
                    $no++,
                    $pembelian->nomor_faktur,
                    $tanggal ? $tanggal->format('d/m/Y') : '',
                    $pembelian->penyedia,
                    $pembelian->unit_kerja,
                    $item->kode_barang,
                    $item->nama_barang,
                    $item->satuan,
                    (float) $item->harga_satuan,
                    (int) $item->jumlah,
                    (float) $item->subtotal,
                    (float) $pembelian->total,
                ];
            }
        }

        return new self($rows);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'No. Faktur',
            'Tanggal',
            'Penyedia',
            'Unit Kerja',
            'Kode Barang',
            'Nama Barang',
            'Satuan',
            'Harga Satuan',
            'Jumlah',
            'Subtotal',
            'Total Transaksi',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'I' => '#,##0.00',
            'J' => '#,##0',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            },
        ];
    }
}

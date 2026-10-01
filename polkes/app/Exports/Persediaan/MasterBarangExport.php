<?php

declare(strict_types=1);

namespace App\Exports\Persediaan;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MasterBarangExport extends DefaultValueBinder implements
    FromQuery,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithColumnFormatting,
    WithEvents,
    WithCustomValueBinder
{
    private int $rowNumber = 0;

    /**
     * @param  Builder<\App\Models\Persediaan\PersediaanBarang>  $query
     */
    public function __construct(private Builder $query)
    {
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'Nama Barang',
            'Unit Kerja',
            'Satuan',
            'Stok Awal',
            'Harga',
            'Status',
            'Rak',
        ];
    }

    /**
     * @param  \App\Models\Persediaan\PersediaanBarang  $barang
     * @return list<int|float|string>
     */
    public function map($barang): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            (string) $barang->kode_barang,
            (string) $barang->nama_barang,
            (string) $barang->unit_kerja,
            (string) $barang->satuan,
            (int) $barang->stok_awal,
            (float) $barang->harga_satuan,
            $barang->is_active ? 'Aktif' : 'Nonaktif',
            (string) (optional(optional($barang->rakAssignment)->rak)->kode_rak ?? ''),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'F' => '#,##0',
            'G' => '#,##0.00',
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'B' && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $range = $sheet->calculateWorksheetDimension();
                $sheet->setAutoFilter($range);
                $this->styleSheet($sheet, $range);
            },
        ];
    }

    private function styleSheet(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A5F'],
            ],
        ]);

        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'D0D5DD'],
                ],
            ],
        ]);
    }
}

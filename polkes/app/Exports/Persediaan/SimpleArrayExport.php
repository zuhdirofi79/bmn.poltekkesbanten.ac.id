<?php

declare(strict_types=1);

namespace App\Exports\Persediaan;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export Excel generik dari array baris yang SUDAH difilter di controller.
 * Single source of truth filter tetap di controller (mengikuti pola AsetBmnExport).
 */
class SimpleArrayExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  array<int,array<int,mixed>>  $rows
     * @param  list<string>  $headings
     */
    public function __construct(
        private array $rows,
        private array $headings,
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use RuntimeException;

final class DuplicateFakturException extends RuntimeException
{
    public function __construct(private string $nomorFaktur)
    {
        parent::__construct('Transaksi dengan No. Faktur ' . $nomorFaktur . ' sudah terdaftar.');
    }

    public function nomorFaktur(): string
    {
        return $this->nomorFaktur;
    }
}

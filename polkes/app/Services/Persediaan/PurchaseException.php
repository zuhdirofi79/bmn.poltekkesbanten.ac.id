<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use RuntimeException;

/** Pesan ini aman ditampilkan ke admin. Bukan error SQL. */
final class PurchaseException extends RuntimeException
{
}

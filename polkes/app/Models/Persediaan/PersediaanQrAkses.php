<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanQrAkses extends Model
{
    protected $table = 'persediaan_qr_akses';

    protected $fillable = [
        'unit_kerja',
        'nama',
        'token',
        'qr_path',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

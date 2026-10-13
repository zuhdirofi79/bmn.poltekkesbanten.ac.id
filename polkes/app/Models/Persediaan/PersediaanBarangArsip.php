<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanBarangArsip extends Model
{
    protected $table = 'persediaan_barang_arsip';

    protected $fillable = [
        'original_barang_id',
        'kode_barang',
        'nama_barang',
        'satuan',
        'unit_kerja',
        'stok_awal',
        'stok_minimum',
        'harga_satuan',
        'is_active',
        'barcode_payload',
        'rak_id',
        'soft_deleted_at',
        'archived_by',
        'archived_at',
    ];

    protected $casts = [
        'stok_awal'       => 'integer',
        'stok_minimum'    => 'integer',
        'harga_satuan'    => 'decimal:2',
        'is_active'       => 'boolean',
        'soft_deleted_at' => 'datetime',
        'archived_at'     => 'datetime',
    ];
}

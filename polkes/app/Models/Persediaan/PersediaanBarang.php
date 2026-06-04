<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanBarang extends Model
{
    protected $table = 'persediaan_barang';

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'satuan',
        'unit_kerja',
        'stok_awal',
        'stok_minimum',
        'harga_satuan',
        'is_active',
    ];

    protected $casts = [
        'stok_awal'    => 'integer',
        'stok_minimum' => 'integer',
        'harga_satuan' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    public function movements()
    {
        return $this->hasMany(PersediaanStockMovement::class, 'barang_id');
    }
}

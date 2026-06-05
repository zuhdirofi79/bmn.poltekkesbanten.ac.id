<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanStockOpnameItem extends Model
{
    protected $table = 'persediaan_stock_opname_items';

    protected $fillable = [
        'opname_id',
        'barang_id',
        'kode_barang',
        'nama_barang',
        'satuan',
        'stok_sistem',
        'stok_fisik',
        'selisih',
        'keterangan',
    ];

    protected $casts = [
        'stok_sistem' => 'integer',
        'stok_fisik'  => 'integer',
        'selisih'     => 'integer',
    ];

    public function opname()
    {
        return $this->belongsTo(PersediaanStockOpname::class, 'opname_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\BhpKebersihan;

use Illuminate\Database\Eloquent\Model;

class BhpKebersihanStockOpnameDetail extends Model
{
    protected $table = 'bhp_kebersihan_stock_opname_details';

    protected $fillable = [
        'opname_id',
        'barang_id',
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

    public function barang()
    {
        return $this->belongsTo(BhpKebersihanBarang::class, 'barang_id');
    }

    public function opname()
    {
        return $this->belongsTo(BhpKebersihanStockOpname::class, 'opname_id');
    }
}

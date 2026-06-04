<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanStockOpnameItem extends Model
{
    public const KONDISI_SESUAI = 'sesuai';
    public const KONDISI_KURANG = 'selisih_kurang';
    public const KONDISI_LEBIH = 'selisih_lebih';
    public const KONDISI_RUSAK = 'rusak';
    public const KONDISI_KADALUARSA = 'kadaluarsa';

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
        'kondisi',
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

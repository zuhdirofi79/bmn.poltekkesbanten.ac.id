<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanPembelianItem extends Model
{
    protected $table = 'persediaan_pembelian_items';

    protected $fillable = [
        'pembelian_id',
        'barang_id',
        'kode_barang',
        'nama_barang',
        'satuan',
        'harga_satuan',
        'jumlah',
        'subtotal',
    ];

    protected $casts = [
        'harga_satuan' => 'decimal:2',
        'jumlah'       => 'integer',
        'subtotal'     => 'decimal:2',
    ];

    public function pembelian()
    {
        return $this->belongsTo(PersediaanPembelian::class, 'pembelian_id');
    }

    public function barang()
    {
        return $this->belongsTo(PersediaanBarang::class, 'barang_id')->withTrashed();
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanPembelian extends Model
{
    protected $table = 'persediaan_pembelian';

    protected $fillable = [
        'nomor_faktur',
        'penyedia',
        'tanggal',
        'unit_kerja',
        'total',
        'faktur_path',
        'bast_path',
        'invoice_path',
        'foto_barang_path',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total'   => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(PersediaanPembelianItem::class, 'pembelian_id');
    }
}

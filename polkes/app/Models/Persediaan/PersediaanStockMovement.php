<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanStockMovement extends Model
{
    public const TIPE_STOK_AWAL = 'stok_awal';
    public const TIPE_PEMBELIAN = 'pembelian';
    public const TIPE_KELUAR = 'permintaan_keluar';
    public const TIPE_OPNAME = 'opname_adjustment';

    protected $table = 'persediaan_stock_movements';

    protected $fillable = [
        'barang_id',
        'unit_kerja',
        'tipe',
        'ref_table',
        'ref_id',
        'qty_masuk',
        'qty_keluar',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'qty_masuk'  => 'integer',
        'qty_keluar' => 'integer',
    ];

    public function barang()
    {
        return $this->belongsTo(PersediaanBarang::class, 'barang_id');
    }
}

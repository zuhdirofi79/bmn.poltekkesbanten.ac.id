<?php

declare(strict_types=1);

namespace App\Models\BhpKebersihan;

use Illuminate\Database\Eloquent\Model;

class BhpKebersihanStockMovement extends Model
{
    public const TIPE_MASUK = 'masuk';
    public const TIPE_KELUAR = 'keluar';
    public const TIPE_KOREKSI = 'koreksi_opname';

    public const SUMBER_PERMINTAAN = 'permintaan';
    public const SUMBER_OPNAME = 'opname';
    public const SUMBER_MANUAL = 'manual';

    protected $table = 'bhp_kebersihan_stock_movements';

    protected $fillable = [
        'barang_id',
        'tipe',
        'sumber',
        'ref_id',
        'jumlah',
        'stok_sebelum',
        'stok_sesudah',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'jumlah'       => 'integer',
        'stok_sebelum' => 'integer',
        'stok_sesudah' => 'integer',
    ];

    public function barang()
    {
        return $this->belongsTo(BhpKebersihanBarang::class, 'barang_id');
    }
}

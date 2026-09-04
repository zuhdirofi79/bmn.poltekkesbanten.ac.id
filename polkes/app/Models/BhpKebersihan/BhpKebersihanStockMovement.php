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

    public const CATATAN_STOK_AWAL = 'Stok awal master barang';

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

    public function creator()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    public function signedDelta(): int
    {
        return (int) $this->stok_sesudah - (int) $this->stok_sebelum;
    }

    public function jenisLabel(): string
    {
        if ($this->tipe === self::TIPE_KOREKSI && $this->sumber === self::SUMBER_MANUAL) {
            return 'Koreksi Manual';
        }
        if ($this->tipe === self::TIPE_KOREKSI) {
            return 'Koreksi Opname';
        }
        if ($this->tipe === self::TIPE_MASUK) {
            return 'Barang Masuk';
        }
        if ($this->tipe === self::TIPE_KELUAR) {
            return 'Barang Keluar';
        }

        return (string) $this->tipe;
    }
}

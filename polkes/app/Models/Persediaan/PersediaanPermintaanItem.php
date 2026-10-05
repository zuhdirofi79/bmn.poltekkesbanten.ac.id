<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanPermintaanItem extends Model
{
    protected $table = 'persediaan_permintaan_items';

    protected $fillable = [
        'permintaan_id',
        'barang_id',
        'kode_barang',
        'nama_barang',
        'satuan',
        'stok_tersedia',
        'jumlah_diminta',
        'jumlah_disetujui',
    ];

    protected $casts = [
        'stok_tersedia'    => 'integer',
        'jumlah_diminta'   => 'integer',
        'jumlah_disetujui' => 'integer',
    ];

    public function permintaan()
    {
        return $this->belongsTo(PersediaanPermintaan::class, 'permintaan_id')->withTrashed();
    }

    public function barang()
    {
        return $this->belongsTo(PersediaanBarang::class, 'barang_id')->withTrashed();
    }
}

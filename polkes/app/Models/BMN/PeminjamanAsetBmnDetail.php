<?php

declare(strict_types=1);

namespace App\Models\BMN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris detail aset pada satu transaksi peminjaman BMN.
 *
 * Tabel saat ini menyimpan FK utama + alias legacy:
 *   - peminjaman_aset_bmn_id (UTAMA, NOT NULL)  ↔ peminjaman_id  (alias)
 *   - aset_bmn_id            (UTAMA, NOT NULL)  ↔ aset_id        (alias)
 *
 * Saat insert wajib mengisi keduanya agar kompatibel dengan kode lama.
 * Relasi dan query utama tetap memakai *_aset_bmn_id.
 *
 * @property int $id
 * @property int $peminjaman_aset_bmn_id
 * @property int $peminjaman_id
 * @property int $aset_bmn_id
 * @property int $aset_id
 * @property string $kondisi_awal
 * @property string|null $kondisi_kembali
 * @property string|null $status_item
 * @property string|null $catatan
 */
class PeminjamanAsetBmnDetail extends Model
{
    protected $table = 'peminjaman_aset_bmn_detail';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'peminjaman_aset_bmn_id',
        'peminjaman_id',
        'aset_bmn_id',
        'aset_id',
        'kondisi_awal',
        'kondisi_kembali',
        'status_item',
        'catatan',
    ];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(PeminjamanAsetBmn::class, 'peminjaman_aset_bmn_id');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetBmn::class, 'aset_bmn_id');
    }
}

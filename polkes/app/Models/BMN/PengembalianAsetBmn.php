<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengembalian aset BMN. 1-to-1 dengan PeminjamanAsetBmn.
 *
 * Catatan: tabel pengembalian saat ini TIDAK menyimpan kolom kondisi_kembali.
 * Kondisi pengembalian per item disimpan di peminjaman_aset_bmn_detail.kondisi_kembali.
 *
 * @property int $id
 * @property int $peminjaman_aset_bmn_id
 * @property int|null $diterima_by
 * @property \Illuminate\Support\Carbon $tanggal_kembali
 * @property string|null $catatan_pengembalian
 * @property string|null $bukti_foto_path
 * @property string|null $bukti_foto_original_name
 */
class PengembalianAsetBmn extends Model
{
    protected $table = 'pengembalian_aset_bmn';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'peminjaman_aset_bmn_id',
        'diterima_by',
        'tanggal_kembali',
        'catatan_pengembalian',
        'bukti_foto_path',
        'bukti_foto_original_name',
    ];

    protected $casts = [
        'tanggal_kembali' => 'date',
    ];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(PeminjamanAsetBmn::class, 'peminjaman_aset_bmn_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }
}

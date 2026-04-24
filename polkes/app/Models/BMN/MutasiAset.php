<?php

declare(strict_types=1);

namespace App\Models\BMN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiAset extends Model
{
    protected $table = 'mutasi_aset';

    protected $fillable = [
        'aset_id',
        'dari_lokasi_id',
        'ke_lokasi_id',
        'tanggal_mutasi',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_mutasi' => 'date',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetBmn::class, 'aset_id');
    }

    public function dariLokasi(): BelongsTo
    {
        return $this->belongsTo(LokasiAset::class, 'dari_lokasi_id');
    }

    public function keLokasi(): BelongsTo
    {
        return $this->belongsTo(LokasiAset::class, 'ke_lokasi_id');
    }
}

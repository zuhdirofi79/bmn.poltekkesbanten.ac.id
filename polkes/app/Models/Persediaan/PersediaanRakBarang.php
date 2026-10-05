<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersediaanRakBarang extends Model
{
    protected $table = 'persediaan_rak_barang';

    protected $fillable = [
        'rak_id',
        'barang_id',
        'assigned_at',
        'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function rak(): BelongsTo
    {
        return $this->belongsTo(PersediaanRak::class, 'rak_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(PersediaanBarang::class, 'barang_id')->withTrashed();
    }
}

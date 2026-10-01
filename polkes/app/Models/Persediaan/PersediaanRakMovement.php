<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersediaanRakMovement extends Model
{
    protected $table = 'persediaan_rak_movements';

    protected $fillable = [
        'barang_id',
        'from_rak_id',
        'to_rak_id',
        'moved_by',
        'moved_at',
        'catatan',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(PersediaanBarang::class, 'barang_id');
    }

    public function fromRak(): BelongsTo
    {
        return $this->belongsTo(PersediaanRak::class, 'from_rak_id');
    }

    public function toRak(): BelongsTo
    {
        return $this->belongsTo(PersediaanRak::class, 'to_rak_id');
    }
}

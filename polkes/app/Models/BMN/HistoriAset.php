<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriAset extends Model
{
    protected $table = 'histori_aset';

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'aset_id',
        'aksi',
        'deskripsi',
        'data_lama',
        'data_baru',
        'user_id',
    ];

    protected $casts = [
        'data_lama' => 'array',
        'data_baru' => 'array',
        'created_at' => 'datetime',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetBmn::class, 'aset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

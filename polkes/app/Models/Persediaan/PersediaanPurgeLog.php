<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersediaanPurgeLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PURGED = 'purged';
    public const STATUS_FAILED = 'failed';

    protected $table = 'persediaan_purge_logs';

    protected $fillable = [
        'batch_id',
        'original_barang_id',
        'kode_barang',
        'nama_barang',
        'unit_kerja',
        'status',
        'history_archived',
        'message',
        'processed_at',
    ];

    protected $casts = [
        'history_archived' => 'boolean',
        'processed_at'     => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PersediaanPurgeBatch::class, 'batch_id');
    }
}

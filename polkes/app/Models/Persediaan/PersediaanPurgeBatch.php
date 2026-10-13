<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersediaanPurgeBatch extends Model
{
    public const MODE_SINGLE = 'single';
    public const MODE_SELECTED = 'selected';
    public const MODE_ALL = 'all';

    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';

    protected $table = 'persediaan_purge_batches';

    protected $fillable = [
        'public_id',
        'admin_id',
        'mode',
        'status',
        'total',
        'processed',
        'succeeded',
        'failed',
        'step_token',
        'locked_at',
    ];

    protected $casts = [
        'total'     => 'integer',
        'processed' => 'integer',
        'succeeded' => 'integer',
        'failed'    => 'integer',
        'locked_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(PersediaanPurgeLog::class, 'batch_id');
    }
}

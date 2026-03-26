<?php

namespace App;

use App\Services\BhpNotificationService;
use Illuminate\Database\Eloquent\Model;

class BarangBHP_bahan extends Model
{
    protected $table = 'barangbhp_bahan';
    protected $guarded = [];
    public $timestamps = false;

    protected static function booted()
    {
        static::saved(function ($model) {
            if (app()->runningInConsole()) {
                return;
            }
            try {
                BhpNotificationService::clearCacheByJurusan($model->jurusan ?? null);
            } catch (\Throwable $e) {
                // Never let cache busting break saves.
            }
        });

        static::deleted(function ($model) {
            if (app()->runningInConsole()) {
                return;
            }
            try {
                BhpNotificationService::clearCacheByJurusan($model->jurusan ?? null);
            } catch (\Throwable $e) {
            }
        });
    }

    /**
     * Prepared for when ruangan_id is added to barangbhp_bahan table.
     * Until then, $item->ruangan returns null — blade uses optional().
     */
    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }
}

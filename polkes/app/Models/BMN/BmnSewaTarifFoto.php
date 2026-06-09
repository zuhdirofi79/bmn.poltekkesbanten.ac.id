<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BmnSewaTarifFoto extends Model
{
    protected $table = 'bmn_sewa_tarif_fotos';

    public const FOTO_DIR = 'uploads/foto_bmn';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tarif_id',
        'foto_path',
        'foto_original_name',
        'caption',
        'sort_order',
        'is_primary',
        'uploaded_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(BmnSewaTarif::class, 'tarif_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fotoUrl(): ?string
    {
        $path = trim((string) ($this->foto_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($normalized, self::FOTO_DIR.'/')) {
            return bmn_upload_url(substr($normalized, strlen(self::FOTO_DIR) + 1));
        }

        return asset($normalized);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumen Nota Dinas / Surat Permohonan untuk peminjaman BMN (multi-file).
 *
 * @property int $id
 * @property int $peminjaman_aset_bmn_id
 * @property string $file_path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property int|null $uploaded_by
 */
class PeminjamanAsetBmnDocument extends Model
{
    protected $table = 'peminjaman_aset_bmn_documents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'peminjaman_aset_bmn_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(PeminjamanAsetBmn::class, 'peminjaman_aset_bmn_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * URL publik file PDF, kompatibel dengan path uploads/nota_dinas/.
     */
    public function fileUrl(): ?string
    {
        $path = trim((string) ($this->file_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, PeminjamanAsetBmn::NOTA_DINAS_DIR.'/')) {
            return bmn_nota_dinas_url(basename($normalized));
        }

        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }

        if (str_starts_with($normalized, 'bmn/nota-dinas/')) {
            return asset('storage/'.$normalized);
        }

        return asset('storage/'.$normalized);
    }

    /**
     * @return bool|null true=ada, false=hilang, null=tidak bisa dicek
     */
    public function fileExistsLocally(): ?bool
    {
        $path = trim((string) ($this->file_path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, PeminjamanAsetBmn::NOTA_DINAS_DIR.'/')) {
            return is_file(bmn_nota_dinas_path(basename($normalized)));
        }

        return null;
    }
}

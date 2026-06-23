<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
     * URL publik file PDF via asset() — path relatif dari public/.
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

        return asset(ltrim($path, '/'));
    }

    /**
     * Path absolut di disk untuk response()->file() / download().
     */
    public function absoluteFilePath(): ?string
    {
        $path = trim((string) ($this->file_path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, PeminjamanAsetBmn::NOTA_DINAS_DIR.'/')) {
            return bmn_nota_dinas_path(basename($normalized));
        }

        if (str_starts_with($normalized, 'storage/')) {
            return public_path($normalized);
        }

        if (str_starts_with($normalized, 'bmn/nota-dinas/')) {
            return public_path('storage/'.$normalized);
        }

        return public_path($normalized);
    }

    /**
     * @return bool|null true=ada, false=hilang, null=tidak bisa dicek
     */
    public function fileExistsLocally(): ?bool
    {
        $absolute = $this->absoluteFilePath();
        if ($absolute === null) {
            return null;
        }

        $path = trim((string) ($this->file_path ?? ''));
        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, PeminjamanAsetBmn::NOTA_DINAS_DIR.'/')) {
            return is_file($absolute);
        }

        if (str_starts_with($normalized, 'storage/') || str_starts_with($normalized, 'bmn/nota-dinas/')) {
            return null;
        }

        return is_file($absolute);
    }

    public function formattedFileSize(): ?string
    {
        $size = $this->file_size;
        if ($size === null || $size <= 0) {
            return null;
        }

        if ($size >= 1048576) {
            return number_format($size / 1048576, 1).' MB';
        }

        return number_format($size / 1024, 0).' KB';
    }

    public function viewResponse(): BinaryFileResponse
    {
        $absolute = $this->absoluteFilePath();
        abort_if($absolute === null || ! is_file($absolute), 404, 'File tidak ditemukan.');

        return response()->file($absolute, [
            'Content-Type' => $this->mime_type ?: 'application/pdf',
        ]);
    }

    public function downloadResponse(): BinaryFileResponse
    {
        $absolute = $this->absoluteFilePath();
        abort_if($absolute === null || ! is_file($absolute), 404, 'File tidak ditemukan.');

        $name = trim((string) ($this->original_name ?? ''));
        if ($name === '') {
            $name = basename($absolute);
        }

        return response()->download($absolute, $name);
    }
}

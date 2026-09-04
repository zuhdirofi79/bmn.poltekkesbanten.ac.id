<?php

declare(strict_types=1);

namespace App\Models\BhpKebersihan;

use App\Services\BhpKebersihan\BarcodeService;
use App\Services\BhpKebersihan\FotoService;
use App\Support\BhpKebersihan\PublicUploadPath;
use Illuminate\Database\Eloquent\Model;

class BhpKebersihanBarang extends Model
{
    protected $table = 'bhp_kebersihan_barang';

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'merk',
        'satuan',
        'stok_awal',
        'harga_satuan',
        'barcode_token',
        'barcode_path',
        'foto_path',
        'foto_original_name',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'stok_awal'     => 'integer',
        'harga_satuan'  => 'decimal:2',
    ];

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }

    public function permintaan()
    {
        return $this->hasMany(BhpKebersihanPermintaan::class, 'barang_id');
    }

    public function movements()
    {
        return $this->hasMany(BhpKebersihanStockMovement::class, 'barang_id');
    }

    public function barcodeUrl(): string
    {
        return url('/public/bhp-kebersihan/barang/' . $this->barcode_token);
    }

    public function barcodeFileUrl(): ?string
    {
        return app(BarcodeService::class)->fileUrl($this);
    }

    public function hasBarcodeFile(): bool
    {
        return app(BarcodeService::class)->hasValidFile($this);
    }

    public function fotoUrl(): ?string
    {
        $relative = PublicUploadPath::normalizeRelative($this->foto_path);
        if ($relative === null) {
            return null;
        }

        PublicUploadPath::ensureOnPublicDisk($relative);
        if (PublicUploadPath::firstExistingFile($relative) === null) {
            return null;
        }

        $url = PublicUploadPath::publicUrl($relative);

        return $url !== '' ? $url : null;
    }

    public function hasFotoFile(): bool
    {
        return app(FotoService::class)->hasFile($this->foto_path);
    }
}

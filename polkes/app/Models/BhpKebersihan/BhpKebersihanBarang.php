<?php

declare(strict_types=1);

namespace App\Models\BhpKebersihan;

use Illuminate\Database\Eloquent\Model;

class BhpKebersihanBarang extends Model
{
    protected $table = 'bhp_kebersihan_barang';

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'satuan',
        'stok_awal',
        'harga_satuan',
        'barcode_token',
        'barcode_path',
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
        if ($this->barcode_path === null || $this->barcode_path === '') {
            return null;
        }

        if (! is_file(public_path($this->barcode_path))) {
            return null;
        }

        return asset($this->barcode_path);
    }

    public function hasBarcodeFile(): bool
    {
        return $this->barcode_path !== null
            && $this->barcode_path !== ''
            && is_file(public_path($this->barcode_path));
    }
}

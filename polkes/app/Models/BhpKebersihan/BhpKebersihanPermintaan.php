<?php

declare(strict_types=1);

namespace App\Models\BhpKebersihan;

use Illuminate\Database\Eloquent\Model;

class BhpKebersihanPermintaan extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_SIAP_DIAMBIL = 'siap_diambil';
    public const STATUS_SUDAH_DIAMBIL = 'sudah_diambil';

    protected $table = 'bhp_kebersihan_permintaan';

    protected $fillable = [
        'nomor_permintaan',
        'barang_id',
        'nama_pengambil',
        'no_wa_pengambil',
        'tanggal_pengambilan',
        'jumlah_diminta',
        'catatan',
        'status',
        'verified_by',
        'verified_at',
        'verification_note',
        'processed_by',
        'processed_at',
        'process_note',
    ];

    protected $casts = [
        'tanggal_pengambilan' => 'date',
        'jumlah_diminta'      => 'integer',
        'verified_at'         => 'datetime',
        'processed_at'        => 'datetime',
    ];

    public function barang()
    {
        return $this->belongsTo(BhpKebersihanBarang::class, 'barang_id');
    }

    public function verifier()
    {
        return $this->belongsTo(\App\User::class, 'verified_by');
    }

    public function processor()
    {
        return $this->belongsTo(\App\User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canVerify(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canProcessPickup(): bool
    {
        return in_array($this->status, [self::STATUS_DISETUJUI, self::STATUS_SIAP_DIAMBIL], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING        => 'Menunggu Verifikasi',
            self::STATUS_DISETUJUI      => 'Disetujui',
            self::STATUS_DITOLAK        => 'Ditolak',
            self::STATUS_SIAP_DIAMBIL   => 'Siap Diambil',
            self::STATUS_SUDAH_DIAMBIL  => 'Sudah Diambil',
            default                     => $this->status,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanPermintaan extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_MENUNGGU = 'menunggu_persetujuan';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_DIKELUARKAN = 'barang_dikeluarkan';
    public const STATUS_SELESAI = 'selesai';

    /** Status yang dianggap "pending" / butuh aksi admin. */
    public const STATUS_PENDING = [self::STATUS_DIAJUKAN, self::STATUS_MENUNGGU];

    protected $table = 'persediaan_permintaan';

    protected $fillable = [
        'nomor_permintaan',
        'pemohon_id',
        'unit_kerja',
        'tanggal_permintaan',
        'keterangan',
        'status',
        'tanda_tangan_pemohon_path',
        'approved_by',
        'approved_at',
        'tanda_tangan_admin_path',
        'catatan_admin',
    ];

    protected $casts = [
        'tanggal_permintaan' => 'date',
        'approved_at'        => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PersediaanPermintaanItem::class, 'permintaan_id');
    }

    public function pemohon()
    {
        return $this->belongsTo(PersediaanPemohon::class, 'pemohon_id');
    }

    /** @var array<string,string> label status untuk UI */
    public const STATUS_LABEL = [
        self::STATUS_DRAFT       => 'Draft',
        self::STATUS_DIAJUKAN    => 'Diajukan',
        self::STATUS_MENUNGGU    => 'Menunggu Persetujuan',
        self::STATUS_DISETUJUI   => 'Disetujui',
        self::STATUS_DITOLAK     => 'Ditolak',
        self::STATUS_DIKELUARKAN => 'Barang Dikeluarkan',
        self::STATUS_SELESAI     => 'Selesai',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABEL[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::STATUS_PENDING, true);
    }
}

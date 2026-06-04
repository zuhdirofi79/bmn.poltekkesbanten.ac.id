<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun pemohon Persediaan (guard 'persediaan'). Login via NIP.
 */
class PersediaanPemohon extends Authenticatable
{
    use Notifiable;

    protected $table = 'persediaan_pemohon';

    protected $fillable = [
        'nama',
        'nip',
        'email',
        'nomor_wa',
        'jabatan',
        'unit_kerja',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function permintaan()
    {
        return $this->hasMany(PersediaanPermintaan::class, 'pemohon_id');
    }
}

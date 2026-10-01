<?php

declare(strict_types=1);

/**
 * Konfigurasi modul Persediaan BMN.
 * Mapping approver unit kerja — tidak mengandalkan users.jurusan.
 */
return [
    /** User ID yang boleh melihat semua unit dan approve semua permintaan. */
    'superadmin_user_ids' => [
        3235, // Hendri
        3243, // Nirmala Vitaloka
    ],

    /**
     * Approver wajib per unit kerja (satker publik).
     *
     * @var array<string, array{user_id: int, email: string, name: string}>
     */
    'approvers' => [
        'Keperawatan' => [
            'user_id' => 3238,
            'email'   => 'Nurhayati.azmi70@gmail.com',
            'name'    => 'Nurhayati Azmi',
        ],
        'Kebidanan' => [
            'user_id' => 3236,
            'email'   => 'sayuti@poltekkesbanten.ac.id',
            'name'    => 'Sayuti',
        ],
        'TLM' => [
            'user_id' => 3237,
            'email'   => 'm.ridwan@poltekkesbanten.ac.id',
            'name'    => 'M Ridwan',
        ],
        'Direktorat' => [
            'user_id' => 3243,
            'email'   => 'nirmala@poltekkesbanten.ac.id',
            'name'    => 'Nirmala Vitaloka',
        ],
    ],

    /**
     * Unit yang memakai sistem rak + QR Pintu/QR Rak.
     * Unit lain tetap memakai form permintaan katalog existing.
     *
     * @var list<string>
     */
    'rack_enabled_units' => [
        'Direktorat',
    ],

    /*
    |----------------------------------------------------------------------
    | Root folder uploads di document root (public_html) + URL publiknya
    |----------------------------------------------------------------------
    | Kosong = infer dari BMN_FOTO_BASE_PATH / BMN_FOTO_BASE_URL,
    | lalu fallback public_path('uploads').
    |
    | Contoh production:
    | PERSEDIAAN_UPLOADS_PATH=/home/.../public_html/uploads
    | PERSEDIAAN_UPLOADS_URL=https://bmn.poltekkesbanten.ac.id/uploads
    */
    'uploads_path' => env('PERSEDIAAN_UPLOADS_PATH', ''),
    'uploads_url' => env('PERSEDIAAN_UPLOADS_URL', ''),
];

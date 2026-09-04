<?php

declare(strict_types=1);

return [
    'superadmin_user_ids' => [],

    /*
    |----------------------------------------------------------------------
    | Root folder `uploads` di document root (public_html) + URL publiknya
    |----------------------------------------------------------------------
    | Kosong = infer dari BMN_FOTO_BASE_PATH / BMN_FOTO_BASE_URL
    | (dirname → .../public_html/uploads), lalu fallback public_path('uploads').
    |
    | Contoh production:
    | BHP_KEBERSIHAN_UPLOADS_PATH=/home/u708283844/domains/bmn.poltekkesbanten.ac.id/public_html/uploads
    | BHP_KEBERSIHAN_UPLOADS_URL=https://bmn.poltekkesbanten.ac.id/uploads
    */
    'uploads_path' => env('BHP_KEBERSIHAN_UPLOADS_PATH', ''),
    'uploads_url' => env('BHP_KEBERSIHAN_UPLOADS_URL', ''),
];

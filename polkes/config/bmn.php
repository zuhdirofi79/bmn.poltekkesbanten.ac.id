<?php

declare(strict_types=1);

return [
    /*
    |----------------------------------------------------------------------
    | Path absolut (disk) & URL publik untuk foto Aset BMN
    |----------------------------------------------------------------------
    | Kosong = fallback: public_path('uploads/foto_bmn') dan url('/uploads/foto_bmn')
    | Production (shared hosting, docroot = public_html), contoh:
    | BMN_FOTO_BASE_PATH=/home/.../public_html/uploads/foto_bmn
    | BMN_FOTO_BASE_URL=https://bmn....ac.id/uploads/foto_bmn
    */
    'foto_path' => env('BMN_FOTO_BASE_PATH', ''),
    'foto_url' => env('BMN_FOTO_BASE_URL', ''),
];

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

    /*
    |----------------------------------------------------------------------
    | Path absolut (disk) & URL publik untuk Nota Dinas (Peminjaman BMN)
    |----------------------------------------------------------------------
    | WAJIB di-set pada shared hosting (Hostinger/cPanel) di mana docroot =
    | public_html/ TAPI Laravel app sebenarnya berada di public_html/<subdir>/.
    | Pada layout itu, public_path() menunjuk ke <subdir>/public — BUKAN ke
    | document root, sehingga file yang di-upload jadi tidak bisa diakses
    | lewat URL https://domain/uploads/nota_dinas/...
    |
    | Kosong = fallback ke public_path('uploads/nota_dinas') &
    | url('/uploads/nota_dinas') — hanya benar bila docroot = Laravel public/.
    |
    | Contoh production (sinkron dengan BMN_FOTO_BASE_PATH):
    | BMN_NOTA_DINAS_BASE_PATH=/home/u708283844/domains/bmn.poltekkesbanten.ac.id/public_html/uploads/nota_dinas
    | BMN_NOTA_DINAS_BASE_URL=https://bmn.poltekkesbanten.ac.id/uploads/nota_dinas
    */
    'nota_dinas_path' => env('BMN_NOTA_DINAS_BASE_PATH', ''),
    'nota_dinas_url' => env('BMN_NOTA_DINAS_BASE_URL', ''),

    /*
    |----------------------------------------------------------------------
    | Path absolut (disk) & URL publik untuk Bukti Bayar Sewa BMN
    |----------------------------------------------------------------------
    | Pola sama dengan nota_dinas — wajib di-set pada shared hosting.
    |
    | Contoh production:
    | BMN_BUKTI_BAYAR_SEWA_BASE_PATH=/home/.../public_html/uploads/bukti_bayar_sewa
    | BMN_BUKTI_BAYAR_SEWA_BASE_URL=https://bmn....ac.id/uploads/bukti_bayar_sewa
    */
    'bukti_bayar_sewa_path' => env('BMN_BUKTI_BAYAR_SEWA_BASE_PATH', ''),
    'bukti_bayar_sewa_url' => env('BMN_BUKTI_BAYAR_SEWA_BASE_URL', ''),
];

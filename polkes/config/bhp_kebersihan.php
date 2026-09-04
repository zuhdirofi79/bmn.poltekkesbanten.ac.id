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

    /*
    |----------------------------------------------------------------------
    | Logo resmi pada generated barcode (compositing server-side)
    |----------------------------------------------------------------------
    | WAJIB file logo.png "Kemenkes Poltekkes Banten".
    | Kosong = cari public_html/img/logo.png, lalu public/img/logo.png.
    | Jangan isi logo1.png / fav1.png / fav.png (logo lama).
    */
    'barcode_logo_path' => env('BHP_BARCODE_LOGO_PATH', ''),

    /*
    |----------------------------------------------------------------------
    | OCR import foto master barang
    |----------------------------------------------------------------------
    | Shared hosting biasanya tidak punya Tesseract. Gunakan provider API:
    | gemini | openai | ocrspace | tesseract | auto
    | API key WAJIB lewat .env — jangan hard-code.
    */
    'ocr' => [
        'provider' => env('BHP_OCR_PROVIDER', 'auto'),
        'api_key' => env('BHP_OCR_API_KEY', ''),
        'model' => env('BHP_OCR_MODEL', ''),
        'tesseract_bin' => env('BHP_OCR_TESSERACT_BIN', ''),
        'max_files' => 10,
        'max_file_kb' => 8192,
        'timeout' => 90,
    ],
];

# Dokumentasi SIM-BMN

## Generate PDF panduan

```bash
cd polkes
php artisan documentation:generate
```

Output:

`storage/app/public/documentation/Panduan_Penggunaan_SIM_BMN_Poltekkes_Banten.pdf`

## Screenshot

1. Jalankan server lokal: `php artisan serve`
2. Capture halaman publik:

```bash
set BASE_URL=http://127.0.0.1:8000
node scripts/capture-documentation-screenshots.js
```

3. Generate ulang PDF:

```bash
php artisan documentation:generate
```

Jika Playwright tidak tersedia, generator akan membuat placeholder PNG di folder ini.

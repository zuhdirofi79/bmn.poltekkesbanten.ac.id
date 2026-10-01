# Mapping Persediaan — QR Pintu, QR Rak, Code 128

Dokumen audit Phase 0 sebelum implementasi. Tidak mengubah BHP Kebersihan.

## Keputusan arsitektur

| Kode | Teknologi | Dipakai untuk | Bukan untuk |
|------|-----------|---------------|-------------|
| QR Code | URL HTTPS + opaque token | QR Pintu, QR Rak | Label per barang |
| Code 128 | Linear barcode | Identifikasi barang (admin) | Flow pengambilan pemohon |

Flow pemohon: QR Pintu → identitas session → Scan QR Rak → pilih banyak barang + qty → 1 permintaan multi-item → approval existing → stok keluar sekali saat approve.

Feature flag: `config('persediaan.rack_enabled_units') = ['Direktorat']`.
Keperawatan, Kebidanan, TLM **tidak** memakai sistem rak. Flow publik existing tetap.

## File existing terkait

### Routes
- `polkes/routes/web.php`
  - Publik: `public/persediaan/*` (unit picker, login/register pemohon, permintaan)
  - Admin: `persediaan/*` (dashboard, permintaan, pembelian, stok-akhir, opname, laporan, master-barang, pengaturan)

### Controllers
- `app/Http/Controllers/Persediaan/UnitPickerController.php`
- `app/Http/Controllers/Persediaan/PemohonAuthController.php` — login selalu ke `permintaan.create` (perlu intended URL)
- `app/Http/Controllers/Persediaan/PermintaanController.php` — form katalog unit, multi-item, status `menunggu_persetujuan`, **stok tidak berkurang**
- `app/Http/Controllers/PersediaanAdmin/PermintaanController.php` — `setujui()` sudah memanggil `StockService::recordPermintaanKeluar` (stok keluar di approval). **Belum** lock + validasi stok all-or-nothing
- `app/Http/Controllers/PersediaanAdmin/MasterBarangController.php` — filter, import Excel, CRUD; belum ada barcode
- `app/Http/Controllers/PersediaanAdmin/PembelianController.php` — barang masuk
- `app/Http/Controllers/PersediaanAdmin/StockOpnameController.php`
- `app/Http/Controllers/PersediaanAdmin/StokAkhirController.php` — memakai `StockService::ringkasanBanyak`

### Models / tabel
- `persediaan_barang` — PK `bigIncrements`. Unique `(kode_barang, unit_kerja)` **bukan** unique global.
- `persediaan_permintaan` + `persediaan_permintaan_items`
- `persediaan_stock_movements` — tipe: `stok_awal`, `pembelian`, `permintaan_keluar`, `opname_adjustment`
- `persediaan_pemohon` (guard `persediaan`)
- `persediaan_admins` (session `persediaan_admin_id`)
- **Tidak ada** tabel rak / QR akses / barcode path

### Services (reuse, jangan duplikasi)
- `App\Services\Persediaan\StockService` — **single source of truth**
  - `stokAkhir = stok_awal + masuk non-awal - semua keluar`
  - `stok_awal` master **tidak** diubah saat transaksi
  - `recordPermintaanKeluar` skip jika movement `(tipe, ref_table, ref_id, barang_id)` sudah ada
- `App\Services\Persediaan\NomorPermintaanService` — `PRM-{KODE}-{TAHUN}-{URUT}`
- `App\Services\Persediaan\SignatureService`

### Guard / auth
- Guard pemohon: `persediaan` → `PersediaanPemohon`
- Admin: session `PersediaanAdminSession` + middleware `persediaan.admin`
- Approver Direktorat: `config/persediaan.php` → user_id 3243 (Nirmala Vitaloka)
- Superadmin: 3235, 3243
- Middleware pemohon existing redirect ke unit picker (bukan intended URL)

### Sumber current stock
`StockService::stokAkhir()` / `ringkasanBanyak()` — sama dengan `/persediaan/stok-akhir` periode tanpa filter tanggal (realtime).

**Jangan** memakai `stok_awal` sebagai stok saat ini.

### Mekanisme approval existing
1. Pemohon submit → `status = menunggu_persetujuan`, `approver_user_id` dari config unit
2. Admin `/persediaan/permintaan/{id}/setujui` → `disetujui` + movement `permintaan_keluar`
3. `keluarkan` → `selesai`; movement di-skip jika sudah ada (anti double deduction parsial)

Celan yang ditutup di implementasi ini: lockForUpdate + validasi stok sebelum approve; gagal all-or-nothing jika salah satu item kurang.

### Mekanisme pengurangan stok
Hanya lewat `persediaan_stock_movements`. Submit permintaan **tidak** membuat movement.

### Dependency QR / barcode existing
- Composer **tidak** punya library barcode
- BHP Kebersihan (referensi pola saja):
  - QR PNG via `api.qrserver.com` (`BarcodeLabelRenderer`)
  - Upload path `App\Support\BhpKebersihan\PublicUploadPath`
  - Print CSS 50mm × 20mm
  - Token opaque; regenerate **tidak** mengganti token
- **Jangan** coupling tabel/model BHP
- **Jangan** ubah logic BHP

### Keputusan kode_barang vs Code 128
`persediaan_barang` unique `(kode_barang, unit_kerja)`. ATK001 bisa ada di Keperawatan dan Direktorat.

Payload internal Code 128: `{KODE_UNIT}|{kode_barang}` contoh `DIR|ATK001`.
Teks human-readable di label: `kode_barang` saja (`ATK001`).
Resolver scanner **wajib** parse prefix unit; jangan `where kode_barang = ?` saja.

## File yang akan diubah

- `config/persediaan.php`
- `polkes/.env.example`
- `routes/web.php`
- `app/Http/Controllers/Persediaan/PemohonAuthController.php` — `redirect()->intended()`
- `app/Http/Controllers/PersediaanAdmin/PermintaanController.php` — lock + validasi stok
- `app/Http/Controllers/PersediaanAdmin/MasterBarangController.php` — barcode batch
- `app/Http/Controllers/PersediaanAdmin/PembelianController.php` — lookup barcode (aman)
- `app/Http/Controllers/PersediaanAdmin/StockOpnameController.php` — lookup barcode (aman)
- `app/Models/Persediaan/PersediaanBarang.php`
- `app/Models/Persediaan/PersediaanPermintaan.php`
- `app/Services/Persediaan/StockService.php` — helper validasi stok (reuse formula)
- `resources/views/persediaan_admin/partials/sidebar.blade.php`
- `resources/views/persediaan_admin/master-barang/index.blade.php`
- `resources/views/persediaan_admin/pembelian/create.blade.php`
- `resources/views/persediaan_admin/opname/create.blade.php`
- `resources/views/persediaan_admin/permintaan/show.blade.php`
- `public/css/persediaan.css`

## File baru yang diperlukan

### Database
- `database/migrations/2026_09_17_100000_create_persediaan_qr_rak_barcode_tables.php`
- `database/sql/2026_09_17_persediaan_qr_rak_barcode.sql`

Tabel baru: `persediaan_rak`, `persediaan_rak_barang`, `persediaan_rak_movements`, `persediaan_qr_akses`.
Kolom additive: `persediaan_barang.barcode_path`, `barcode_payload`; `persediaan_permintaan.source`, `rak_id`, `idempotency_key`.

### Support / services / models / controllers / views / tests
Lihat implementasi di tree `app/Support/Persediaan`, `app/Services/Persediaan`, `app/Models/Persediaan`, `app/Http/Controllers/Persediaan*`, `resources/views/persediaan*`, `tests/Feature/Persediaan`, `tests/Unit/Persediaan`.

## Route konsep (named, tidak menabrak `{unit}/login`)

Admin (prefix `persediaan`, middleware `persediaan.admin`):
- `persediaan.admin.master-rak.*`
- `persediaan.admin.master-barang.generate-barcodes` (static sebelum `{id}`)
- `persediaan.admin.master-barang.print-barcodes`
- `persediaan.admin.master-barang.lookup-barcode`

Publik (prefix `public/persediaan`, **sebelum** `{unit}/login`):
- `public.persediaan.akses.show` GET `akses/{token}`
- `public.persediaan.rak.show` GET `rak/{token}`
- `public.persediaan.rak.confirm` POST `rak/{token}/konfirmasi`
- `public.persediaan.rak.store` POST `rak/{token}/permintaan`

## Storage

Reuse pola BHP tanpa coupling:
- ENV `PERSEDIAAN_UPLOADS_PATH` / `PERSEDIAAN_UPLOADS_URL`
- Fallback: dirname BMN foto, lalu `public_path('uploads')`
- Relatif: `uploads/persediaan/qr/akses/`, `uploads/persediaan/qr/rak/`, `uploads/persediaan/barcode/barang/`

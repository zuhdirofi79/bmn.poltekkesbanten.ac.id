# 🚀 Panduan Setup API - Step by Step

Setelah API dibuat, ikuti langkah-langkah berikut untuk setup dan testing.

---

## ✅ STEP 1: Konfigurasi Database

### 1.1. Update Database Credentials

Buka file `api/config/database.php` dan update kredensial database Anda:

```php
private const DB_HOST = 'localhost';  // atau IP server database Anda
private const DB_NAME = 'adminlab_polkes';
private const DB_USER = 'your_db_username';  // ⚠️ GANTI INI
private const DB_PASS = 'your_db_password';  // ⚠️ GANTI INI
```

**Cara mendapatkan kredensial:**
- Login ke **cPanel** → **MySQL Databases**
- Atau lihat di **phpMyAdmin** → **Privileges** tab
- Atau tanyakan ke hosting provider Anda

---

## ✅ STEP 2: Buat Tabel API Tokens

### 2.1. Buka phpMyAdmin atau cPanel MySQL

### 2.2. Pilih database `adminlab_polkes`

### 2.3. Jalankan SQL Migration

Buka file `api/database/migrations/create_api_tokens_table.sql`, copy semua isinya, dan jalankan di SQL tab phpMyAdmin.

**ATAU** copy SQL berikut:

```sql
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `api_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.4. Verifikasi Tabel Dibuat

Setelah menjalankan SQL, pastikan tabel `api_tokens` ada di database dengan menjalankan:

```sql
SHOW TABLES LIKE 'api_tokens';
```

---

## ✅ STEP 3: Upload API Files ke Server

### 3.1. Upload Folder `/api` ke Web Server

**Lokasi upload:**
```
/public_html/
├── index.php (existing web system)
├── login.php (existing web system)
└── api/ (UPLOAD FOLDER INI)  ← NEW
    ├── .htaccess
    ├── config/
    ├── middleware/
    ├── auth/
    ├── admin/
    ├── plp/
    └── user/
```

### 3.2. Set Permissions (jika perlu)

Pastikan file PHP dapat dibaca dan dieksekusi:
- File PHP: `644` atau `755`
- Folder: `755`

**Via FTP/cPanel File Manager:**
- Right-click folder `api` → Permissions → Set ke `755`

---

## ✅ STEP 4: Testing API Endpoints

### 4.1. Test Login Endpoint (PENTING - Test Ini Dulu!)

**Via cURL (Command Line):**
```bash
curl -X POST https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"99999","password":"your_password"}'
```

**Via Postman:**
1. Method: `POST`
2. URL: `https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON):
```json
{
  "username": "99999",
  "password": "your_password"
}
```

**Response yang diharapkan:**
```json
{
  "success": true,
  "data": {
    "token": "64_character_hex_token",
    "user": {
      "id": 4,
      "name": "Admin Kampus",
      "username": "99999",
      "role": "admin"
    }
  },
  "message": "Login berhasil"
}
```

**⚠️ SIMPAN TOKEN ini untuk testing endpoint lainnya!**

---

### 4.2. Test Protected Endpoint (Profile)

**Via cURL:**
```bash
curl -X GET https://laboratorium.poltekkesbanten.ac.id/api/user/profile.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**Via Postman:**
1. Method: `GET`
2. URL: `https://laboratorium.poltekkesbanten.ac.id/api/user/profile.php`
3. Headers: 
   - `Authorization: Bearer YOUR_TOKEN_HERE`

---

### 4.3. Test Endpoint Lainnya

Uji semua endpoint sesuai kebutuhan:
- Admin endpoints (perlu role `admin`)
- PLP endpoints (perlu role `plp`)
- User endpoints (bisa semua role)

**Daftar lengkap endpoint:** Lihat `api/README.md`

---

## ✅ STEP 5: Update Flutter App

### 5.1. Update API Base URL

Buka `lib/core/config/api_config.dart` dan pastikan baseUrl sudah benar:

```dart
static const String baseUrl = 'https://laboratorium.poltekkesbanten.ac.id/api';
```

### 5.2. Test dari Flutter Web

Jalankan Flutter app di web untuk testing tanpa emulator:

```bash
flutter run -d chrome
```

**Testing flow:**
1. Login screen → Test login API
2. Dashboard → Test profile API
3. Test fitur sesuai role

### 5.3. Update API Service (jika perlu)

Pastikan `lib/services/api_service.dart` menggunakan:
- Dio dengan interceptor untuk token
- Error handling yang proper
- Base URL dari `api_config.dart`

---

## ✅ STEP 6: Troubleshooting

### Error: Database connection failed

**Solusi:**
- Check kredensial database di `api/config/database.php`
- Pastikan database `adminlab_polkes` ada
- Check apakah user database memiliki akses ke database tersebut

### Error: Table 'api_tokens' doesn't exist

**Solusi:**
- Jalankan SQL migration di Step 2
- Verifikasi tabel sudah dibuat

### Error: 401 Unauthorized

**Solusi:**
- Check token masih valid (30 hari)
- Pastikan header `Authorization: Bearer {token}` sudah benar
- Token harus dari login yang berhasil

### Error: 403 Forbidden

**Solusi:**
- Check role user sesuai dengan endpoint yang diakses
- Admin endpoint → perlu role `admin`
- PLP endpoint → perlu role `plp`

### Error: CORS issue (dari Flutter Web)

**Solusi:**
- Check file `api/.htaccess` sudah ada
- Pastikan CORS headers sudah diset di `.htaccess`
- Atau set di server config (jika menggunakan Nginx)

### Error: Method not allowed (405)

**Solusi:**
- Check HTTP method yang digunakan (GET, POST, PUT, DELETE)
- Pastikan sesuai dengan endpoint yang dituju
- Check `.htaccess` tidak memblokir method tersebut

---

## ✅ STEP 7: Production Checklist

Sebelum deploy ke production:

- [ ] Database credentials sudah diupdate
- [ ] Tabel `api_tokens` sudah dibuat
- [ ] Login endpoint berhasil di-test
- [ ] Protected endpoint berhasil di-test
- [ ] CORS sudah dikonfigurasi dengan benar
- [ ] Error logging aktif (check PHP error log)
- [ ] Base URL di Flutter app sudah benar
- [ ] API endpoints sesuai dengan kebutuhan Flutter app

---

## 📞 Support

Jika ada masalah:

1. **Check PHP Error Log:**
   - cPanel → Error Log
   - Atau `error_log()` output

2. **Check Database:**
   - Verifikasi tabel `api_tokens` ada
   - Check foreign key constraint

3. **Check Permissions:**
   - File PHP readable
   - Folder executable

4. **Test dengan Postman/cURL:**
   - Isolate masalah (database, PHP, atau Flutter)

---

## 🎯 Next Steps

Setelah API berjalan:

1. **Integrate dengan Flutter App:**
   - Update `api_service.dart` untuk semua endpoint
   - Test setiap screen dengan API

2. **Add Error Handling:**
   - Network errors
   - API errors
   - Token expiration

3. **Optimize:**
   - Add caching jika perlu
   - Optimize queries
   - Add rate limiting (optional)

4. **Testing:**
   - Unit test API responses (mocked)
   - Integration test dengan Flutter Web
   - Final test dengan emulator/device (jika perlu)

---

**Selamat! API Anda siap digunakan. 🚀**

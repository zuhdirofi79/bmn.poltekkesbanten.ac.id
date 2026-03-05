# Fix: "Database error during authentication" (HTTP 500)

## A) ROOT CAUSE SPESIFIK

**Root Cause**: Semua `PDOException` di `api/middleware/auth.php` ditangkap dan dikembalikan sebagai HTTP 500 dengan pesan generic "Database error during authentication", tanpa membedakan jenis error (koneksi DB, query error, tabel tidak ada, kolom tidak ada). Logging yang ada tidak cukup detail untuk debugging karena tidak mencatat SQLSTATE, driver code, dan konteks error.

**Lokasi Error**: `api/middleware/auth.php` line 269-295 (catch block `PDOException`)

**Kemungkinan Penyebab Error 500**:
1. **Koneksi DB gagal**: Host/port/credentials salah, firewall, atau MySQL service down
2. **Query error**: Tabel `api_tokens` atau `users` tidak ada, kolom tidak ada, atau SQL syntax error
3. **Transaction error**: `FOR UPDATE` lock timeout atau deadlock
4. **Foreign key constraint**: `api_tokens.user_id` reference ke `users.id` gagal jika user dihapus

**Catatan**: Error ini BUKAN masalah token invalid/expired (yang seharusnya return 401). Ini adalah masalah server-side (database/query).

---

## B) PATCH KODE YANG DITERAPKAN

### 1. Perbaikan `api/middleware/auth.php`

**Perubahan**:
- Menambahkan logging detail dengan SQLSTATE, driver code, dan konteks error (tanpa membocorkan token full)
- Menambahkan error type detection (connection, table, column, query)
- Menambahkan rollback error handling yang lebih aman
- Tetap return HTTP 500 untuk semua database error (karena ini masalah server, bukan client)

**File**: `api/middleware/auth.php` (line 269-295)

```php
} catch (PDOException $e) {
    // Extract detailed error information
    $errorMessage = $e->getMessage();
    $errorCode = $e->getCode();
    $sqlState = $e->errorInfo[0] ?? 'UNKNOWN';
    $driverCode = $e->errorInfo[1] ?? null;
    $driverMessage = $e->errorInfo[2] ?? null;
    
    // Log full error details server-side (safe - no token exposure)
    $logContext = [
        'location' => 'token_validation',
        'sqlstate' => $sqlState,
        'code' => $errorCode,
        'driver_code' => $driverCode,
        'message' => $errorMessage,
        'driver_message' => $driverMessage,
        'token_hash_prefix' => substr($tokenHash, 0, 16) . '...',
        'endpoint' => self::getCurrentEndpoint()
    ];
    
    error_log("AuthMiddleware PDOException: " . json_encode($logContext, JSON_UNESCAPED_UNICODE));
    // ... rest of error handling
}
```

### 2. Perbaikan `api/config/database.php`

**Perubahan**:
- Menambahkan PDO options: `ATTR_TIMEOUT` dan `ATTR_PERSISTENT`
- Menambahkan logging detail untuk connection error (SQLSTATE, driver code)
- Menambahkan charset setup setelah koneksi

**File**: `api/config/database.php` (line 25-47)

### 3. Endpoint Debug Baru: `api/auth/ping.php`

**Purpose**: Test koneksi DB dan query token tanpa memerlukan autentikasi penuh.

**Endpoint**: `GET /api/auth/ping.php`

**Headers (optional)**:
```
Authorization: Bearer {token}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "db_connection": true/false,
    "token_query": true/false,
    "db_error": {...},
    "token_error": {...},
    "db_details": {...},
    "token_details": {...}
  }
}
```

**Security**: Dapat dinonaktifkan dengan set `ENABLE_DEBUG_PING=false` di config/environment.

---

## C) LANGKAH RETEST POSTMAN

### Test 1: GET /api/user/master-data.php TANPA token

**Request**:
```
GET https://laboratorium.poltekkesbanten.ac.id/api/user/master-data.php
Headers: (kosong)
```

**Expected Response**:
```json
{
  "success": false,
  "message": "Authorization header missing"
}
```
**Status Code**: `401 Unauthorized` ✅

---

### Test 2: GET /api/user/master-data.php DENGAN token valid

**Request**:
```
GET https://laboratorium.poltekkesbanten.ac.id/api/user/master-data.php
Headers:
  Authorization: Bearer b1ae7a408a6f...
```

**Expected Response (jika DB OK)**:
```json
{
  "success": true,
  "message": "Master data retrieved successfully",
  "data": {
    "ruangan": [...],
    "tingkat": [...],
    "tujuan": [...]
  }
}
```
**Status Code**: `200 OK` ✅

**Expected Response (jika DB error)**:
```json
{
  "success": false,
  "message": "Database error during authentication",
  "error_code": "AUTH_DB_ERROR"  // hanya jika APP_DEBUG=true
}
```
**Status Code**: `500 Internal Server Error` ❌

**Action**: Cek server log (`logs/php_errors.log`) untuk detail error:
```
AuthMiddleware PDOException: {"location":"token_validation","sqlstate":"...","code":...,"driver_code":...,"message":"...","token_hash_prefix":"...","endpoint":"/api/user/master-data.php"}
```

---

### Test 3: GET /api/auth/ping.php (Debug Endpoint)

**Request (tanpa token)**:
```
GET https://laboratorium.poltekkesbanten.ac.id/api/auth/ping.php
Headers: (kosong)
```

**Expected Response**:
```json
{
  "success": true,
  "data": {
    "db_connection": true/false,
    "token_query": false,
    "db_error": null atau {...},
    "token_error": "No token provided (optional for this endpoint)",
    "db_details": {...},
    "token_details": null
  }
}
```

**Request (dengan token)**:
```
GET https://laboratorium.poltekkesbanten.ac.id/api/auth/ping.php
Headers:
  Authorization: Bearer b1ae7a408a6f...
```

**Expected Response**:
```json
{
  "success": true,
  "data": {
    "db_connection": true,
    "token_query": true/false,
    "db_error": null,
    "token_error": null atau "Token not found or expired",
    "db_details": {
      "status": "connected",
      "charset": "utf8mb4"
    },
    "token_details": {
      "status": "valid",
      "user_id": 123,
      "user_name": "...",
      "user_role": "...",
      "expires_at": "...",
      "revoked": false
    }
  }
}
```

---

## D) TROUBLESHOOTING

### Jika Test 2 masih return 500:

1. **Cek server log** (`api/logs/php_errors.log`):
   ```bash
   tail -f api/logs/php_errors.log
   ```
   Cari entry: `AuthMiddleware PDOException: {...}`

2. **Cek SQLSTATE**:
   - `HY000` = General error (biasanya koneksi DB)
   - `42S02` = Table doesn't exist
   - `42S22` = Column doesn't exist
   - `23000` = Integrity constraint violation

3. **Cek tabel `api_tokens`**:
   ```sql
   SHOW TABLES LIKE 'api_tokens';
   DESCRIBE api_tokens;
   SELECT COUNT(*) FROM api_tokens;
   ```

4. **Cek foreign key constraint**:
   ```sql
   SELECT * FROM api_tokens at
   LEFT JOIN users u ON at.user_id = u.id
   WHERE u.id IS NULL;
   ```

5. **Test koneksi DB langsung**:
   ```bash
   mysql -h localhost -u u708283844_sisenes -p u708283844_sisenes
   ```

6. **Cek credentials di `api/config/database.php`**:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

---

## E) FILE YANG DIUBAH

1. **`api/middleware/auth.php`**
   - Perbaikan error handling `PDOException` (line 269-295)
   - Perbaikan error handling `Exception` (line 296-323)
   - Menambahkan logging detail dengan SQLSTATE, driver code, konteks

2. **`api/config/database.php`**
   - Perbaikan connection error logging (line 25-47)
   - Menambahkan PDO options: `ATTR_TIMEOUT`, `ATTR_PERSISTENT`
   - Menambahkan charset setup

3. **`api/auth/ping.php`** (NEW)
   - Endpoint debug untuk test koneksi DB dan query token
   - Dapat dinonaktifkan dengan `ENABLE_DEBUG_PING=false`

---

## F) CATATAN PENTING

1. **Error 500 vs 401**:
   - **500**: Database/server error (masalah server-side)
   - **401**: Token invalid/expired (masalah client-side)
   - Patch ini tetap return 500 untuk semua DB error, karena ini masalah server, bukan client.

2. **Logging Security**:
   - Token full TIDAK pernah di-log (hanya prefix 16 karakter)
   - Password/credentials TIDAK pernah di-log
   - Detail error hanya di server log, tidak di response ke client

3. **Debug Endpoint**:
   - `api/auth/ping.php` hanya untuk development/debugging
   - Disarankan dinonaktifkan di production dengan set `ENABLE_DEBUG_PING=false`

4. **Next Steps**:
   - Setelah patch diterapkan, test dengan Postman sesuai langkah di atas
   - Jika masih 500, cek server log untuk detail error
   - Gunakan `/api/auth/ping.php` untuk isolasi masalah (DB connection vs token query)

---

**Tanggal**: 2025-01-15  
**Status**: ✅ Patch diterapkan, siap untuk testing

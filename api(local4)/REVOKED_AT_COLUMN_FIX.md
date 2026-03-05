# Fix: Removed `revoked_at` Column References

## ROOT CAUSE

**Error**: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'at.revoked_at' in 'SELECT'`

**Root Cause**: Query di `api/middleware/auth.php` dan file lainnya mencoba SELECT kolom `at.revoked_at` yang **tidak ada** di tabel `api_tokens`.

**Schema Actual** (FIXED):
```sql
CREATE TABLE `api_tokens` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `last_ip` varchar(45) NULL,
  `last_user_agent` varchar(255) NULL,
  `last_used_at` timestamp NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`)
);
```

**Kolom yang TIDAK ADA**:
- `revoked_at` ❌
- `revoked_reason` ❌

---

## PATCH YANG DITERAPKAN

### 1. `api/middleware/auth.php`

#### A) Hapus `revoked_at` dari SELECT query (line 136)
**Before**:
```php
SELECT 
    at.id AS token_id,
    at.user_id AS token_user_id,
    at.expires_at AS token_expires_at,
    at.last_ip,
    at.last_user_agent,
    at.last_used_at,
    at.revoked_at,  // ❌ Column tidak ada
    u.id AS user_id,
    ...
```

**After**:
```php
SELECT 
    at.id AS token_id,
    at.user_id AS token_user_id,
    at.expires_at AS token_expires_at,
    at.last_ip,
    at.last_user_agent,
    at.last_used_at,
    // ✅ revoked_at dihapus
    u.id AS user_id,
    ...
```

#### B) Hapus pengecekan `revoked_at` (line 173-179)
**Before**:
```php
if ($result['revoked_at'] !== null) {
    $db->rollBack();
    error_log("AuthMiddleware: Token revoked - user_id: " . $result['user_id'] . ", reason: " . ($result['revoked_reason'] ?? 'revoked'));
    AuditLogger::tokenRevoked($result['user_id'], $result['revoked_reason'] ?? 'revoked', $tokenHash);
    $errorCode = (defined('APP_DEBUG') && APP_DEBUG) ? 'AUTH_TOKEN_REVOKED' : null;
    ResponseHelper::error('Session expired. Please login again.', 401, null, $errorCode);
}
```

**After**:
```php
// ✅ Pengecekan revoked_at dihapus (kolom tidak ada)
// Token revocation sekarang dilakukan dengan DELETE token (lihat fungsi revokeToken)
```

#### C) Modifikasi fungsi `revokeToken` (line 69-86)
**Before**:
```php
private static function revokeToken($db, $tokenHash, $reason) {
    try {
        $stmt = $db->prepare("
            UPDATE api_tokens
            SET revoked_at = NOW(),  // ❌ Kolom tidak ada
                revoked_reason = :reason  // ❌ Kolom tidak ada
            WHERE token_hash = :token_hash
        ");
        ...
    }
}
```

**After**:
```php
private static function revokeToken($db, $tokenHash, $reason) {
    try {
        // ✅ Delete token instead of marking as revoked (schema doesn't have revoked_at)
        $stmt = $db->prepare("
            DELETE FROM api_tokens
            WHERE token_hash = :token_hash
        ");
        $stmt->execute([
            'token_hash' => $tokenHash
        ]);
        
        error_log("Token revoked (deleted): token_hash=" . substr($tokenHash, 0, 16) . "... reason=" . $reason);
    } catch (Exception $e) {
        error_log("Token revocation error: " . $e->getMessage());
    }
}
```

#### D) Perbaiki update audit fields dengan error handling (line 220-240)
**Before**:
```php
if (!$revoked) {
    $currentTime = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("
        UPDATE api_tokens
        SET last_ip = :last_ip,
            last_user_agent = :last_user_agent,
            last_used_at = :last_used_at,
            updated_at = NOW()
        WHERE id = :token_id
    ");
    
    $stmt->execute([...]);
    
    $db->commit();
}
```

**After**:
```php
// Update audit fields (last_ip, last_user_agent, last_used_at)
// If update fails, log error but don't fail the request
try {
    $currentTime = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("
        UPDATE api_tokens
        SET last_ip = :last_ip,
            last_user_agent = :last_user_agent,
            last_used_at = :last_used_at,
            updated_at = NOW()
        WHERE id = :token_id
    ");
    
    $stmt->execute([...]);
} catch (Exception $updateEx) {
    // ✅ Log update error but don't fail authentication
    error_log("AuthMiddleware: Failed to update token audit fields - " . $updateEx->getMessage());
}

$db->commit();
```

---

### 2. `api/auth/ping.php`

#### Hapus `revoked_at` dari SELECT query (line 122)
**Before**:
```php
SELECT 
    at.id AS token_id,
    at.user_id AS token_user_id,
    at.expires_at AS token_expires_at,
    at.revoked_at,  // ❌ Column tidak ada
    u.id AS user_id,
    ...
```

**After**:
```php
SELECT 
    at.id AS token_id,
    at.user_id AS token_user_id,
    at.expires_at AS token_expires_at,
    // ✅ revoked_at dihapus
    u.id AS user_id,
    ...
```

#### Hapus `revoked` dari response (line 144)
**Before**:
```php
$result['token_details'] = [
    'status' => 'valid',
    'user_id' => (int)$tokenResult['user_id'],
    'user_name' => $tokenResult['user_name'],
    'user_role' => $tokenResult['user_role'],
    'expires_at' => $tokenResult['token_expires_at'],
    'revoked' => $tokenResult['revoked_at'] !== null  // ❌
];
```

**After**:
```php
$result['token_details'] = [
    'status' => 'valid',
    'user_id' => (int)$tokenResult['user_id'],
    'user_name' => $tokenResult['user_name'],
    'user_role' => $tokenResult['user_role'],
    'expires_at' => $tokenResult['token_expires_at']
    // ✅ revoked dihapus
];
```

#### Tambahkan error detail untuk APP_DEBUG (line 149-175)
**After**:
```php
} catch (PDOException $e) {
    $errorInfo = $e->errorInfo ?? [];
    $errorDetails = [
        'sqlstate' => $errorInfo[0] ?? 'UNKNOWN',
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'driver_code' => $errorInfo[1] ?? null,
        'driver_message' => $errorInfo[2] ?? null
    ];
    
    // ✅ Include detailed error in response if APP_DEBUG is enabled
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $result['token_error'] = $errorDetails;
    } else {
        $result['token_error'] = 'Token query failed';
    }
    
    error_log("Debug ping - Token query error: " . json_encode($errorDetails, JSON_UNESCAPED_UNICODE));
}
```

---

### 3. `api/admin/users.php`

#### Hapus filter `revoked_at` (line 143)
**Before**:
```php
WHERE at.token_hash = :token_hash
AND at.expires_at > NOW()
AND (at.revoked_at IS NULL OR at.revoked_at = '')  // ❌
LIMIT 1
```

**After**:
```php
WHERE at.token_hash = :token_hash
AND at.expires_at > NOW()
// ✅ Filter revoked_at dihapus
LIMIT 1
```

---

### 4. `api/auth/capabilities.php`

#### Hapus filter `revoked_at` (line 154)
**Before**:
```php
WHERE at.token_hash = :token_hash
AND at.expires_at > NOW()
AND (at.revoked_at IS NULL OR at.revoked_at = '')  // ❌
LIMIT 1
```

**After**:
```php
WHERE at.token_hash = :token_hash
AND at.expires_at > NOW()
// ✅ Filter revoked_at dihapus
LIMIT 1
```

---

## VALIDASI TOKEN YANG BENAR

### Flow Validasi Token (sesuai schema):

1. **Parse Authorization Header**:
   ```php
   Authorization: Bearer <raw_token>
   ```

2. **Hash Token**:
   ```php
   $tokenHash = hash('sha256', $token);  // SHA-256 hash dari raw token (64 hex chars)
   ```

3. **Query Token**:
   ```sql
   SELECT 
       at.id AS token_id,
       at.user_id AS token_user_id,
       at.expires_at AS token_expires_at,
       u.id AS user_id,
       u.name AS user_name,
       ...
   FROM api_tokens at
   INNER JOIN users u ON at.user_id = u.id
   WHERE at.token_hash = :token_hash
   AND at.expires_at > NOW()
   LIMIT 1
   FOR UPDATE
   ```

4. **Validasi**:
   - Jika token tidak ditemukan => **401** "Invalid or expired token"
   - Jika `expires_at <= NOW()` => **401** "Invalid or expired token" (sudah di-filter di WHERE)
   - Jika valid => set auth user context dan lanjut request

5. **Update Audit Fields** (optional, tidak bikin request gagal jika error):
   ```sql
   UPDATE api_tokens
   SET last_ip = :last_ip,
       last_user_agent = :last_user_agent,
       last_used_at = :last_used_at,
       updated_at = NOW()
   WHERE id = :token_id
   ```

---

## ERROR MAPPING

### 401 Unauthorized (Auth Error):
- Authorization header missing
- Invalid authorization format
- Token is empty
- Invalid token format (bukan 64 hex chars)
- Token not found
- Token expired (`expires_at <= NOW()`)

### 500 Internal Server Error (DB Fatal):
- Database connection error
- Query error (table/column not found, SQL syntax error)
- Transaction error

**Catatan**: Semua error DB di-log dengan detail (SQLSTATE, driver_code, message) di server log, tapi response ke client tetap generic untuk security.

---

## LANGKAH TESTING MANUAL (POSTMAN)

### Test 1: GET /api/auth/ping.php dengan token valid
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
    "token_query": true,
    "db_error": null,
    "token_error": null,
    "db_details": {
      "status": "connected",
      "charset": "utf8mb4"
    },
    "token_details": {
      "status": "valid",
      "user_id": 123,
      "user_name": "...",
      "user_role": "...",
      "expires_at": "2025-01-22 10:00:00"
    }
  }
}
```
**Status Code**: `200 OK` ✅

---

### Test 2: GET /api/user/master-data.php dengan token valid
```
GET https://laboratorium.poltekkesbanten.ac.id/api/user/master-data.php
Headers:
  Authorization: Bearer b1ae7a408a6f...
```

**Expected Response**:
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

---

### Test 3: GET /api/user/master-data.php tanpa token
```
GET https://laboratorium.poltekkesbanten.ac.id/api/user/master-data.php
Headers: (kosong)
```

**Expected Response**:
```json
{
  "success": false,
  "message": "Authorization header missing",
  "error_code": "AUTH_MISSING_HEADER"  // jika APP_DEBUG=true
}
```
**Status Code**: `401 Unauthorized` ✅

---

### Test 4: GET /api/user/master-data.php dengan token random
```
GET https://laboratorium.poltekkesbanten.ac.id/api/user/master-data.php
Headers:
  Authorization: Bearer 0000000000000000000000000000000000000000000000000000000000000000
```

**Expected Response**:
```json
{
  "success": false,
  "message": "Invalid or expired token",
  "error_code": "AUTH_TOKEN_INVALID"  // jika APP_DEBUG=true
}
```
**Status Code**: `401 Unauthorized` ✅

---

### Test 5: GET /api/user/master-data.php dengan token expired
**Setup**: Update `expires_at` di database jadi masa lalu:
```sql
UPDATE api_tokens 
SET expires_at = '2020-01-01 00:00:00' 
WHERE token_hash = :token_hash;
```

**Request**:
```
GET https://laboratorium.poltekkesbanten.ac.id/api/user/master-data.php
Headers:
  Authorization: Bearer <token_yang_expired>
```

**Expected Response**:
```json
{
  "success": false,
  "message": "Invalid or expired token",
  "error_code": "AUTH_TOKEN_INVALID"  // jika APP_DEBUG=true
}
```
**Status Code**: `401 Unauthorized` ✅

---

## FILE YANG DIUBAH

1. ✅ `api/middleware/auth.php`
   - Hapus `at.revoked_at` dari SELECT query
   - Hapus pengecekan `revoked_at`
   - Modifikasi `revokeToken` untuk DELETE token (bukan UPDATE revoked_at)
   - Perbaiki update audit fields dengan error handling

2. ✅ `api/auth/ping.php`
   - Hapus `at.revoked_at` dari SELECT query
   - Hapus `revoked` dari response
   - Tambahkan error detail untuk APP_DEBUG

3. ✅ `api/admin/users.php`
   - Hapus filter `revoked_at` dari WHERE clause

4. ✅ `api/auth/capabilities.php`
   - Hapus filter `revoked_at` dari WHERE clause

---

## CATATAN PENTING

1. **Token Revocation**: Sekarang dilakukan dengan **DELETE** token (bukan UPDATE `revoked_at`). Ini berarti token yang di-revoke akan langsung dihapus dari database.

2. **Error Handling**: Update audit fields (`last_ip`, `last_user_agent`, `last_used_at`) sekarang di-wrap dengan try-catch. Jika update gagal, error di-log tapi request tetap berhasil (tidak return 500).

3. **Schema Compatibility**: Semua query sekarang kompatibel dengan schema actual (tanpa `revoked_at` dan `revoked_reason`).

4. **Security**: Token full tidak pernah di-log atau di-expose. Hanya prefix 16 karakter yang di-log untuk debugging.

---

**Tanggal**: 2025-01-15  
**Status**: ✅ Patch diterapkan, siap untuk testing

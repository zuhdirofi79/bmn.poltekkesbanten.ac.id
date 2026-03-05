# mobile_log.php — Instruksi & Build Play

## Penempatan file (Hostinger / public_html)

| File | Lokasi |
|------|--------|
| `mobile_log.php` | `public_html/api/debug/mobile_log.php` |
| `mobile_log_config.php` | `public_html/api/debug/mobile_log_config.php` (wajib; berisi MOBILE_LOG_KEY) |
| `.htaccess` | `public_html/api/debug/.htaccess` (optional, CORS + POST) |

Log disimpan ke: **`DOCUMENT_ROOT/storage/mobile_logs/YYYY-MM-DD.log`**

Endpoint dikunci: **header X-MOBILE-LOG-KEY wajib** dan harus cocok dengan `MOBILE_LOG_KEY` di server. Tanpa key atau key salah → 401.

---

## Build Play (Flutter AAB)

Key harus **64 karakter hex** dan **sama persis** di server dan di build Flutter. Jangan commit nilai production ke repo.

**Generate key (64 hex):**
```bash
php -r "echo bin2hex(random_bytes(32));"
# atau
openssl rand -hex 32
```

**1) Set di server**  
Edit `public_html/api/debug/mobile_log_config.php`: ganti nilai `MOBILE_LOG_KEY` dengan 64 char hex yang sama.

**2) Build appbundle dengan key:**
```bash
flutter build appbundle --release --dart-define=FLAVOR=play --dart-define=MOBILE_LOG_KEY=<64_CHAR_RANDOM_HEX>
```
Contoh (ganti dengan key Anda):
```bash
flutter build appbundle --release --dart-define=FLAVOR=play --dart-define=MOBILE_LOG_KEY=a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
```

**3) Verifikasi**  
Di app: long-press logo 5x → Diagnostics. Pastikan **SERVER_LOG_KEY: PRESENT**. Jika MISSING, build tidak menyertakan key (request log tidak akan dikirim).

---

## Test dengan curl (Windows PowerShell)

Ganti `https://laboratorium.poltekkesbanten.ac.id` dengan URL API Anda. Ganti `<64_HEX>` dengan nilai MOBILE_LOG_KEY yang sama dengan di server.

### 1) Healthcheck GET (tanpa key)

```powershell
curl -s -X GET "https://laboratorium.poltekkesbanten.ac.id/api/debug/mobile_log.php"
```

Expected: `{"success":true,"status":"ok"}`

---

### 2) POST dengan X-MOBILE-LOG-KEY (expected 200)

```powershell
curl -s -X POST "https://laboratorium.poltekkesbanten.ac.id/api/debug/mobile_log.php" -H "Content-Type: application/json" -H "X-MOBILE-LOG-KEY: <64_HEX>" -d "{\"event\":\"appStart\",\"build_id\":\"BUILD_20260203_1200\",\"flavor\":\"play\",\"version_name\":\"1.0.1\",\"version_code\":\"2\",\"package_name\":\"ac.id.poltekkesbanten.laboratorium_mobile\",\"dio_base_url\":\"https://laboratorium.poltekkesbanten.ac.id/api/\",\"network_state\":\"wifi\",\"timestamp\":\"2026-02-03T10:00:00.000Z\",\"client_timestamp\":\"2026-02-03T10:00:01.000Z\"}"
```

Expected: `{"success":true,"written_to":"storage/mobile_logs/2026-02-03.log","bytes_written":...,"request_id":"..."}`

---

### 3) POST tanpa key (expected 401)

```powershell
curl -s -X POST "https://laboratorium.poltekkesbanten.ac.id/api/debug/mobile_log.php" -H "Content-Type: application/json" -d "{\"event\":\"appStart\",\"build_id\":\"BUILD_20260203_1200\"}"
```

Expected: `{"success":false,"error":"Unauthorized","request_id":"..."}` dengan HTTP 401.

---

## Ringkasan response

| Kondisi | HTTP | Body |
|--------|------|------|
| GET healthcheck | 200 | `{"success":true,"status":"ok"}` |
| OPTIONS (CORS) | 200 | (kosong) |
| POST + key benar | 200 | `{"success":true,"written_to":"...","bytes_written":N,"request_id":"..."}` |
| POST tanpa key / key salah | 401 | `{"success":false,"error":"Unauthorized","request_id":"..."}` |
| Method not allowed | 405 | `{"success":false,"error":"Method Not Allowed","request_id":"..."}` |
| Server error | 500 | `{"success":false,"error":"Server error","request_id":"..."}` |

---

## Keamanan

- Jangan simpan Authorization / token di log (key auth/token dihapus dan nilai token-like di-mask).
- Key 64 hex jangan di-commit; set di server (config) dan lewat `--dart-define` saat build.
- Jika logging gagal (network/401), app tidak crash (fire-and-forget).

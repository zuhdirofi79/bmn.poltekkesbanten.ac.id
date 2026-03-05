# mobile_log.php — Instruksi & Troubleshooting

## Penempatan file (Hostinger / public_html)

| File | Lokasi |
|------|--------|
| `mobile_log.php` | `public_html/api/debug/mobile_log.php` |
| `mobile_log_config.php` | `public_html/api/debug/mobile_log_config.php` (optional) |
| `.htaccess` | `public_html/api/debug/.htaccess` (optional, untuk CORS + POST) |

Log disimpan ke: **`public_html/storage/mobile_logs/YYYY-MM-DD.log`**  
Path memakai `DOCUMENT_ROOT`; folder `storage/mobile_logs` dibuat otomatis (mkdir recursive).

---

## Test dengan curl (Windows PowerShell)

Ganti `https://your-domain.com` dengan URL API Anda.

### 1) Healthcheck GET

```powershell
curl -s -X GET "https://your-domain.com/api/debug/mobile_log.php"
```

Response yang diharapkan: `{"success":true,"status":"ok"}`

---

### 2) POST JSON valid tanpa key (mode dev, MOBILE_LOG_KEY tidak di-set)

```powershell
$body = @{
  event = "loginFail"
  build_id = "BUILD_20260203_1200"
  flavor = "play"
  version_name = "1.0.1"
  version_code = "2"
  package_name = "ac.id.poltekkesbanten.laboratorium_mobile"
  dio_base_url = "https://laboratorium.poltekkesbanten.ac.id/api/"
  last_request_url = "https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php"
  last_login_request_url = "https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php"
  last_error_type = "DioExceptionType.connectionTimeout"
  last_error_message = "Connection timed out"
  last_error_reached_network = $false
  network_state = "wifi"
  timestamp = "2026-02-03T10:00:00.000Z"
  client_timestamp = "2026-02-03T10:00:01.000Z"
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://your-domain.com/api/debug/mobile_log.php" -Method POST -Body $body -ContentType "application/json"
```

Atau satu baris (body JSON object):

```powershell
curl -s -X POST "https://your-domain.com/api/debug/mobile_log.php" -H "Content-Type: application/json" -d "{\"event\":\"loginFail\",\"build_id\":\"BUILD_20260203_1200\",\"flavor\":\"play\",\"version_name\":\"1.0.1\",\"version_code\":\"2\",\"package_name\":\"ac.id.poltekkesbanten.laboratorium_mobile\",\"dio_base_url\":\"https://laboratorium.poltekkesbanten.ac.id/api/\",\"last_request_url\":\"https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php\",\"last_login_request_url\":\"https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php\",\"last_error_type\":\"DioExceptionType.connectionTimeout\",\"last_error_message\":\"Connection timed out\",\"last_error_reached_network\":false,\"network_state\":\"wifi\",\"timestamp\":\"2026-02-03T10:00:00.000Z\",\"client_timestamp\":\"2026-02-03T10:00:01.000Z\"}"
```

Response yang diharapkan (dev mode):  
`{"success":true,"written_to":"storage/mobile_logs/2026-02-03.log","bytes_written":...,"request_id":"...","warning":"MOBILE_LOG_KEY not set; running in dev mode (no key required)"}`

---

### 3) POST JSON dengan X-MOBILE-LOG-KEY (production, MOBILE_LOG_KEY di-set di config)

```powershell
$body = @{
  event = "appStart"
  build_id = "BUILD_20260203_1200"
  flavor = "release"
  version_name = "1.0.1"
  version_code = "2"
  package_name = "ac.id.poltekkesbanten.laboratorium_mobile"
  dio_base_url = "https://laboratorium.poltekkesbanten.ac.id/api/"
  network_state = "wifi"
  timestamp = "2026-02-03T10:00:00.000Z"
  client_timestamp = "2026-02-03T10:00:01.000Z"
} | ConvertTo-Json

$headers = @{
  "Content-Type" = "application/json"
  "X-MOBILE-LOG-KEY" = "your_long_random_secret_same_as_MOBILE_LOG_KEY"
}

Invoke-RestMethod -Uri "https://your-domain.com/api/debug/mobile_log.php" -Method POST -Body $body -Headers $headers
```

Satu baris curl:

```powershell
curl -s -X POST "https://your-domain.com/api/debug/mobile_log.php" -H "Content-Type: application/json" -H "X-MOBILE-LOG-KEY: your_long_random_secret_same_as_MOBILE_LOG_KEY" -d "{\"event\":\"appStart\",\"build_id\":\"BUILD_20260203_1200\",\"flavor\":\"release\",\"version_name\":\"1.0.1\",\"version_code\":\"2\",\"package_name\":\"ac.id.poltekkesbanten.laboratorium_mobile\",\"dio_base_url\":\"https://laboratorium.poltekkesbanten.ac.id/api/\",\"network_state\":\"wifi\",\"timestamp\":\"2026-02-03T10:00:00.000Z\",\"client_timestamp\":\"2026-02-03T10:00:01.000Z\"}"
```

Response yang diharapkan:  
`{"success":true,"written_to":"storage/mobile_logs/2026-02-03.log","bytes_written":...,"request_id":"..."}`

---

## Troubleshooting jika masih 403

### Bedakan WAF/Hostinger vs response dari PHP

| Ciri | Response dari PHP (mobile_log.php) | Response dari WAF / mod_security |
|------|-----------------------------------|----------------------------------|
| Body JSON | Selalu JSON (success/error, request_id, dll) | Sering HTML (halaman “Forbidden” / “Blocked”) |
| Header `Content-Type` | `application/json` | Sering `text/html` |
| Field `request_id` | Ada (string 16 hex) | Tidak ada |
| HTTP status | 200 / 400 / 401 / 405 / 500 | Sering 403 |

**Cara cek cepat:**

1. **GET healthcheck**  
   `curl -s -i "https://your-domain.com/api/debug/mobile_log.php"`  
   - Jika dapat `{"success":true,"status":"ok"}` + `Content-Type: application/json` → script PHP jalan, 403 kemungkinan hanya pada POST.
   - Jika dapat halaman HTML 403 → 403 dari WAF/server sebelum sampai ke PHP.

2. **Lihat response body**  
   - Body berisi `request_id` dan struktur JSON kita → response dari PHP (meski status 403, artinya PHP sempat jalan dan mengirim 403, atau ada layer yang ubah status).  
   - Body berisi “Forbidden”, “Blocked”, atau HTML → kemungkinan WAF/mod_security memblok request.

### Langkah jika 403 dari WAF / server

1. **Cek `.htaccess` di `api/debug/`**  
   Pastikan ada file `public_html/api/debug/.htaccess` yang isinya seperti di repo:
   - `Access-Control-Allow-Headers` mencakup `Content-Type, X-MOBILE-LOG-KEY`
   - `Access-Control-Allow-Methods` mencakup `GET, POST, OPTIONS`
   - Rule OPTIONS → 200 (preflight).

2. **Cek `.htaccess` parent (`api/.htaccess`)**  
   Pastikan tidak memblok POST atau custom header untuk path `debug/`.  
   Jika ada `Access-Control-Allow-Headers`, tambahkan `X-MOBILE-LOG-KEY`:
   ```apache
   Header set Access-Control-Allow-Headers "Content-Type, Authorization, X-MOBILE-LOG-KEY"
   ```

3. **Hostinger: mod_security / WAF**  
   - Di panel Hostinger, cek apakah ada “Web Application Firewall” atau “mod_security” dan apakah bisa di-nonaktifkan untuk path tertentu.
   - Jika ada “Rule/Bypass” per path, coba bypass untuk `/api/debug/` (atau subdomain/domain yang dipakai).

4. **Coba tanpa custom header dulu**  
   - Pastikan MOBILE_LOG_KEY di config kosong (mode dev).
   - Kirim POST **tanpa** header `X-MOBILE-LOG-KEY`.  
   Jika tanpa header berhasil tapi dengan header 403 → WAF mungkin memblok request yang bawa header custom; perlu whitelist `X-MOBILE-LOG-KEY` atau bypass untuk endpoint ini.

5. **Cek method POST**  
   Beberapa WAF memblok POST ke path yang “aneh”. Pastikan URL persis:  
   `https://your-domain.com/api/debug/mobile_log.php`  
   dan method POST (bukan GET).

---

## Ringkasan response endpoint

| Kondisi | HTTP | Body (contoh) |
|--------|------|----------------|
| GET healthcheck | 200 | `{"success":true,"status":"ok"}` |
| POST sukses (dev) | 200 | `{"success":true,"written_to":"storage/mobile_logs/YYYY-MM-DD.log","bytes_written":N,"request_id":"...","warning":"MOBILE_LOG_KEY not set; ..."}` |
| POST sukses (dengan key) | 200 | `{"success":true,"written_to":"...","bytes_written":N,"request_id":"..."}` |
| Method bukan GET/POST | 405 | `{"success":false,"error":"Method Not Allowed","request_id":"..."}` |
| Header Authorization ada | 400 | `{"success":false,"error":"Authorization header not allowed","request_id":"..."}` |
| Key wajib tapi salah/kosong | 401 | `{"success":false,"error":"Unauthorized","request_id":"..."}` |
| Bukan application/json | 400 | `{"success":false,"error":"Content-Type must be application/json","request_id":"..."}` |
| Payload > 20KB | 400 | `{"success":false,"error":"Payload too large (max 20KB)","request_id":"..."}` |
| Error server (mkdir/write) | 500 | `{"success":false,"error":"...","request_id":"...","written_to":"..."}` |
| Exception | 500 | `{"success":false,"error":"Server error","request_id":"..."}` |

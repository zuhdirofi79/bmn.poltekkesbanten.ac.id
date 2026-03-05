# PHP REST API for Mobile App

## Overview

This is an **isolated PHP REST API layer** created specifically for the Flutter mobile app. It operates completely independently from the existing web-based PHP system and does **NOT** interfere with session-based authentication or existing web functionality.

## Database Information

- **Database Name**: `adminlab_polkes`
- **Users Table**: `users`
- **Roles**: `admin`, `plp`, `mahasiswa`, `dosen`, `pimpinan`
- **Password Hashing**: bcrypt (Laravel's `$2y$10$...` format)

## Installation Steps

### 1. Create API Tokens Table

Run the SQL migration file in your cPanel MySQL or phpMyAdmin:

```sql
-- File: api/database/migrations/create_api_tokens_table.sql
```

This creates a new `api_tokens` table that stores authentication tokens for the mobile app.

### 2. Configure Database Connection

Edit `api/config/database.php` and update these constants:

```php
private const DB_HOST = 'localhost';
private const DB_NAME = 'adminlab_polkes';
private const DB_USER = 'your_db_username';  // UPDATE THIS
private const DB_PASS = 'your_db_password';  // UPDATE THIS
```

### 3. Upload API Folder

Upload the entire `/api` folder to your web server root (same level as your existing web PHP files).

**Example structure:**
```
/public_html/
├── index.php (existing web system)
├── login.php (existing web system)
├── ... (other existing files)
└── api/ (NEW - this API folder)
    ├── .htaccess
    ├── config/
    ├── middleware/
    ├── auth/
    ├── user/
    └── ...
```

### 4. Set Permissions

Ensure PHP files are readable and executable by the web server.

## API Endpoints

### Base URL
```
https://laboratorium.poltekkesbanten.ac.id/api
```

### Authentication Endpoints

#### POST /api/auth/login.php
Login and get API token.

**Request:**
```json
{
  "username": "99999",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "64_character_hex_token",
    "user": {
      "id": 4,
      "name": "Admin Kampus",
      "email": "fajar.nur@raharja.info",
      "username": "99999",
      "role": "admin",
      ...
    }
  },
  "message": "Login berhasil"
}
```

#### POST /api/auth/logout.php
Invalidate API token.

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Logout berhasil"
}
```

### Protected Endpoints

All protected endpoints require the `Authorization` header:
```
Authorization: Bearer {token}
```

#### GET /api/user/profile.php
Get current user profile (example protected endpoint).

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 4,
    "name": "Admin Kampus",
    "email": "fajar.nur@raharja.info",
    "username": "99999",
    "role": "admin",
    ...
  }
}
```

## Security Features

1. **Token-Based Authentication**: Uses secure random tokens (64 hex characters)
2. **Token Hashing**: Tokens are hashed (SHA-256) before storage
3. **Token Expiration**: Tokens expire after 30 days
4. **Prepared Statements**: All database queries use PDO prepared statements
5. **Password Verification**: Uses `password_verify()` for bcrypt passwords
6. **No Session Usage**: Completely stateless API

## Why This Doesn't Break the Existing System

1. **Separate Folder**: All API files are in `/api` folder, isolated from web files
2. **No Session Interference**: API uses tokens, web system uses sessions - completely separate
3. **Read-Only on Users Table**: API only READS from `users` table, never modifies it
4. **New Table Only**: Only creates `api_tokens` table, doesn't modify existing tables
5. **Independent Database Connection**: Uses its own PDO connection, doesn't interfere with web system's connection
6. **No File Conflicts**: No PHP files share names with existing web system

## Testing

### Test Login Endpoint

```bash
curl -X POST https://laboratorium.poltekkesbanten.ac.id/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"99999","password":"your_password"}'
```

### Test Protected Endpoint

```bash
curl -X GET https://laboratorium.poltekkesbanten.ac.id/api/user/profile.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Error Responses

All errors follow this format:

```json
{
  "success": false,
  "message": "Error message here"
}
```

Common HTTP status codes:
- `400`: Bad Request (invalid input)
- `401`: Unauthorized (invalid/missing token)
- `403`: Forbidden (insufficient permissions)
- `405`: Method Not Allowed
- `500`: Internal Server Error

## Next Steps

After setting up the basic API:

1. Create role-specific endpoints (admin, plp, user)
2. Implement CRUD operations for your modules
3. Add input validation and sanitization
4. Implement rate limiting (optional)
5. Add logging for debugging

## Support

For issues or questions, check:
- Database connection in `api/config/database.php`
- Token table exists: `SHOW TABLES LIKE 'api_tokens'`
- PHP error logs on your server
- CORS headers if accessing from Flutter Web

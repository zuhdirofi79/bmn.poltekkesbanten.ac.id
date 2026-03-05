# API CONTRACT v1.0 — FINAL LOCKED SPECIFICATION

**STATUS: FINAL / LOCKED / IMMUTABLE**

**WARNING:** This contract is immutable. Breaking changes require a new version (v2.0+). Frontend implementations MUST NOT assume behavior outside this specification.

**Effective Date:** 2025-01-15  
**Contract Version:** 1.0  
**Base URL:** `https://laboratorium.poltekkesbanten.ac.id/api`

---

## 🚨 CONTRACT ENFORCEMENT RULES

1. **If it is not in this contract, frontend MUST NOT rely on it.**
2. **Backend MUST NOT change response shape without version bump.**
3. **All endpoints MUST be versioned via `X-API-Version` header.**
4. **Token is opaque to frontend - no decoding required.**
5. **Field names are normalized to `snake_case` English only.**

---

## REQUIRED HEADERS

### Global Headers (All Requests)
```
Content-Type: application/json
X-API-Version: 1
User-Agent: <client-identifier>
```

### Authentication Header (Protected Endpoints)
```
Authorization: Bearer <jwt_token>
```

**Token Format:**
- Type: JWT (JSON Web Token)
- Token is opaque to frontend clients
- Token MUST be sent in `Authorization: Bearer <token>` header
- Token expiry is authoritative from `expires_at` field in login response
- Frontend MUST NOT attempt to decode or inspect token payload

---

## GLOBAL RESPONSE SCHEMAS

### Success Response
```json
{
  "success": true,
  "message": "string",
  "data": <response_object>
}
```

### Error Response (Global Standard)
```json
{
  "success": false,
  "error_code": "UPPER_SNAKE_CASE",
  "message": "string",
  "retry_after": <integer|null>,
  "block_until": "<ISO-8601-timestamp|null>"
}
```

**Error Response Fields:**
- `success`: MUST be `false`
- `error_code`: Machine-readable error identifier (see Error Code Registry)
- `message`: Human-readable error description
- `retry_after`: Seconds until retry allowed (429 only, null otherwise)
- `block_until`: ISO-8601 timestamp when block expires (403 reputation blocks only, null otherwise)

---

## SECURITY ERROR RESPONSES

### 401 UNAUTHORIZED
```json
{
  "success": false,
  "error_code": "AUTH_INVALID_TOKEN" | "AUTH_TOKEN_EXPIRED",
  "message": "Invalid or expired token",
  "retry_after": null,
  "block_until": null
}
```

**Error Codes:**
- `AUTH_INVALID_TOKEN`: Token format invalid or token not found
- `AUTH_TOKEN_EXPIRED`: Token has expired (check `expires_at` from login)

### 403 FORBIDDEN
```json
{
  "success": false,
  "error_code": "FORBIDDEN_ROLE" | "REPUTATION_BLOCKED" | "IP_BLOCKED",
  "message": "Access denied",
  "retry_after": null,
  "block_until": "<ISO-8601-timestamp|null>"
}
```

**Error Codes:**
- `FORBIDDEN_ROLE`: User role does not have permission for this endpoint
- `REPUTATION_BLOCKED`: User/IP blocked due to reputation system
- `IP_BLOCKED`: IP address blocked by security rules

**When `block_until` is present:**
- Indicates temporary block expiration time
- Frontend MUST NOT retry before `block_until` timestamp
- If `block_until` is `null`, block is permanent (requires admin intervention)

### 429 RATE_LIMITED
```json
{
  "success": false,
  "error_code": "RATE_LIMITED",
  "message": "Rate limit exceeded",
  "retry_after": <integer>,
  "block_until": null
}
```

**Fields:**
- `retry_after`: REQUIRED - Seconds until next request allowed
- Frontend MUST wait `retry_after` seconds before retrying

---

## ERROR CODE REGISTRY

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `AUTH_INVALID_CREDENTIALS` | 401 | Username or password incorrect |
| `AUTH_INVALID_TOKEN` | 401 | Token format invalid or missing |
| `AUTH_TOKEN_EXPIRED` | 401 | Token has expired |
| `FORBIDDEN_ROLE` | 403 | User role lacks required permission |
| `REPUTATION_BLOCKED` | 403 | Blocked by reputation system |
| `IP_BLOCKED` | 403 | IP address blocked |
| `RATE_LIMITED` | 429 | Too many requests, rate limit exceeded |
| `VALIDATION_ERROR` | 400 | Request validation failed |
| `RESOURCE_NOT_FOUND` | 404 | Requested resource does not exist |
| `INTERNAL_ERROR` | 500 | Server internal error |

**Note:** Frontend MUST handle all error codes. Unknown error codes MUST be treated as `INTERNAL_ERROR`.

---

## AUTHENTICATION ENDPOINTS

### POST /auth/login

**Purpose:** Authenticate user and receive JWT token.

**Headers:**
```
Content-Type: application/json
X-API-Version: 1
User-Agent: <client-identifier>
```

**Request Body:**
```json
{
  "username": "string",
  "password": "string"
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "string (JWT)",
    "expires_at": "YYYY-MM-DDTHH:mm:ssZ",
    "user": {
      "user_id": "integer",
      "username": "string",
      "full_name": "string",
      "email": "string",
      "phone_number": "string|null",
      "profile_picture": "string|null",
      "gender": "string|null",
      "department": "string|null",
      "role": "admin|plp|user"
    }
  }
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Missing required fields
- `401`: `AUTH_INVALID_CREDENTIALS` - Invalid username or password
- `429`: `RATE_LIMITED` - Too many login attempts

**Allowed Roles:** Public (no authentication required)

**Security Notes:**
- Rate limiting applies with exponential backoff
- Failed attempts are logged and may trigger reputation system
- Token expires after 7 days (authoritative from `expires_at`)

---

### GET /auth/me

**Purpose:** Get current authenticated user identity. This is the ONLY identity endpoint.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Response 200:**
```json
{
  "success": true,
  "message": "Token valid",
  "data": {
    "user_id": "integer",
    "username": "string",
    "full_name": "string",
    "email": "string",
    "phone_number": "string|null",
    "profile_picture": "string|null",
    "gender": "string|null",
    "department": "string|null",
    "role": "admin|plp|user"
  }
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`

**Allowed Roles:** All authenticated users

**Contract Rule:** This is the ONLY identity endpoint. Any internal alias endpoints (e.g., `/user/profile`) are NOT part of this contract and MUST NOT be used by frontend.

---

### POST /auth/logout

**Purpose:** Invalidate current JWT token.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Response 200:**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`

**Allowed Roles:** All authenticated users

---

### POST /auth/change-password

**Purpose:** Change authenticated user's password.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Request Body:**
```json
{
  "old_password": "string",
  "new_password": "string"
}
```

**Validation Rules:**
- `old_password`: REQUIRED, must match current password
- `new_password`: REQUIRED, minimum 6 characters

**Response 200:**
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid input, new password too short
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED` or `AUTH_INVALID_CREDENTIALS` (old password incorrect)
- `404`: `RESOURCE_NOT_FOUND` - User not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** All authenticated users

---

## ADMIN ENDPOINTS

**Base Authorization:** All admin endpoints require `role: "admin"`

### GET /admin/users

**Purpose:** List all users with optional search.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Query Parameters:**
- `search` (optional): Search term for username, name, or email

**Response 200:**
```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [
    {
      "user_id": "integer",
      "username": "string",
      "full_name": "string",
      "email": "string",
      "phone_number": "string"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`

**Allowed Roles:** `admin`

---

### GET /admin/manage-users

**Purpose:** List all users with role information for management.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Query Parameters:**
- `search` (optional): Search term for username or name

**Response 200:**
```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [
    {
      "user_id": "integer",
      "username": "string",
      "full_name": "string",
      "role": "admin|plp|user"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`

**Allowed Roles:** `admin`

---

### POST /admin/users

**Purpose:** Create new user.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Request Body:**
```json
{
  "username": "string",
  "full_name": "string",
  "email": "string",
  "phone_number": "string",
  "password": "string",
  "role": "admin|plp|user"
}
```

**Validation Rules:**
- All fields: REQUIRED
- `email`: Must be unique, valid email format
- `password`: Minimum 6 characters
- `role`: Must be one of: `admin`, `plp`, `user`

**Response 200:**
```json
{
  "success": true,
  "message": "User created successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid input, email already exists
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `admin`

---

### PUT /admin/users/{user_id}

**Purpose:** Update existing user.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Path Parameters:**
- `user_id` (required): Target user ID

**Request Body:**
```json
{
  "username": "string",
  "full_name": "string",
  "email": "string",
  "phone_number": "string",
  "role": "admin|plp|user",
  "password": "string|null"
}
```

**Validation Rules:**
- `email`: Must be unique if provided (cannot duplicate existing)
- `password`: Optional, minimum 6 characters if provided
- `role`: Must be one of: `admin`, `plp`, `user` if provided

**Response 200:**
```json
{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "user_id": "integer",
    "username": "string",
    "full_name": "string",
    "email": "string",
    "phone_number": "string|null",
    "profile_picture": "string|null",
    "gender": "string|null",
    "department": "string|null",
    "role": "admin|plp|user",
    "email_verified_at": "YYYY-MM-DDTHH:mm:ssZ|null",
    "created_at": "YYYY-MM-DDTHH:mm:ssZ",
    "updated_at": "YYYY-MM-DDTHH:mm:ssZ"
  }
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid input, email already exists
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - User not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `admin`

---

### DELETE /admin/users/{user_id}

**Purpose:** Delete user.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Path Parameters:**
- `user_id` (required): Target user ID

**Response 200:**
```json
{
  "success": true,
  "message": "User deleted successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid user_id
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - User not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `admin`

---

### GET /admin/rooms

**Purpose:** List all lab rooms (master data).

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Query Parameters:**
- `search` (optional): Search term for room name, department, or campus

**Response 200:**
```json
{
  "success": true,
  "message": "Rooms retrieved successfully",
  "data": [
    {
      "room_id": "integer",
      "department": "string",
      "campus": "string",
      "room_name": "string"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`

**Allowed Roles:** `admin`

---

### POST /admin/rooms

**Purpose:** Create new lab room.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Request Body:**
```json
{
  "room_name": "string",
  "department": "string",
  "campus": "string"
}
```

**Validation Rules:**
- All fields: REQUIRED

**Response 200:**
```json
{
  "success": true,
  "message": "Room created successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid input
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `admin`

---

### PUT /admin/rooms/{room_id}

**Purpose:** Update existing lab room.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Path Parameters:**
- `room_id` (required): Target room ID

**Request Body:**
```json
{
  "room_name": "string",
  "department": "string",
  "campus": "string"
}
```

**Validation Rules:**
- All fields: REQUIRED

**Response 200:**
```json
{
  "success": true,
  "message": "Room updated successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid input
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - Room not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `admin`

---

### DELETE /admin/rooms/{room_id}

**Purpose:** Delete lab room.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Path Parameters:**
- `room_id` (required): Target room ID

**Response 200:**
```json
{
  "success": true,
  "message": "Room deleted successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid room_id
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - Room not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `admin`

---

## PLP ENDPOINTS

**Base Authorization:** All PLP endpoints require `role: "plp"`  
**Auto-Filtering:** Results automatically filtered by PLP's `department` field

### GET /plp/items

**Purpose:** List inventory items (equipment, supplies, materials) filtered by PLP's department.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Query Parameters:**
- `type` (optional): Filter by type - `inventaris` | `alat` | `bahan`. If omitted, returns all types.
- `search` (optional): Search term for item name

**Response 200:**
```json
{
  "success": true,
  "message": "Items retrieved successfully",
  "data": [
    {
      "item_id": "integer",
      "item_type": "inventaris|alat|bahan",
      "department": "string",
      "item_name": "string",
      "stock_quantity": "integer",
      "unit": "string",
      "condition": "string|null",
      "item_code": "string|null (inventaris only)",
      "entry_date": "YYYY-MM-DD|null (inventaris only)",
      "brand": "string|null (inventaris only)",
      "item_model": "string|null (inventaris only)",
      "serial_number": "string|null (inventaris only)",
      "room_location": "string|null (inventaris only)",
      "chemical_name": "string|null (bahan only)",
      "expiry_date": "YYYY-MM-DD|null (bahan only)",
      "updated_at": "YYYY-MM-DDTHH:mm:ssZ"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`

**Allowed Roles:** `plp`

**Security Notes:** Results automatically scoped to PLP's department. PLP cannot access items from other departments.

---

### GET /plp/praktikum/schedule

**Purpose:** List praktikum (practical session) schedules.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Query Parameters:**
- `search` (optional): Search term for course name, class, or room

**Response 200:**
```json
{
  "success": true,
  "message": "Schedule retrieved successfully",
  "data": [
    {
      "schedule_id": "integer",
      "course_name": "string",
      "class_name": "string",
      "lab_room": "string",
      "date": "YYYY-MM-DD",
      "start_time": "HH:mm",
      "end_time": "HH:mm",
      "instructor": "string",
      "status": "string"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`

**Allowed Roles:** `plp`

---

### GET /plp/equipment/requests

**Purpose:** List equipment loan requests filtered by PLP's department.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Query Parameters:**
- `status` (optional): Filter by status - `Menunggu` | `Menunggu Konfirmasi` | `Diterima` | `Ditolak` | `Menunggu Dikembalikan` | `Selesai`
- `department` (optional): Additional department filter

**Response 200:**
```json
{
  "success": true,
  "message": "Equipment requests retrieved successfully",
  "data": [
    {
      "request_id": "integer",
      "department": "string",
      "user_id": "integer",
      "user_name": "string",
      "user_username": "string",
      "equipment_type": "string",
      "room_name": "string",
      "responsible_person": "string|null",
      "level": "string",
      "start_time": "string",
      "end_time": "string",
      "purpose": "string",
      "loan_date": "YYYY-MM-DD",
      "return_date": "YYYY-MM-DD|null",
      "status": "string",
      "created_at": "YYYY-MM-DDTHH:mm:ssZ"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`

**Allowed Roles:** `plp`

**Security Notes:** Results automatically filtered by PLP's department if PLP has department assigned.

---

### GET /plp/requests/{request_id}

**Purpose:** Get detailed equipment request with items.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Path Parameters:**
- `request_id` (required): Equipment request ID

**Response 200:**
```json
{
  "success": true,
  "message": "Request detail retrieved successfully",
  "data": {
    "request_id": "integer",
    "department": "string",
    "user_id": "integer",
    "user_name": "string",
    "user_username": "string",
    "equipment_type": "string",
    "room_name": "string",
    "responsible_person": "string|null",
    "level": "string",
    "start_time": "string",
    "end_time": "string",
    "purpose": "string",
    "loan_date": "YYYY-MM-DD",
    "return_date": "YYYY-MM-DD|null",
    "status": "string",
    "created_at": "YYYY-MM-DDTHH:mm:ssZ",
    "items": [
      {
        "item_id": "integer",
        "item_detail_id": "integer",
        "item_name": "string",
        "item_type": "inventaris|alat|bahan|null",
        "loan_quantity": "integer",
        "status": "string",
        "condition": "string|null",
        "loan_officer": "string|null",
        "return_officer": "string|null"
      }
    ]
  }
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Missing request_id
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - Request not found

**Allowed Roles:** `plp`

---

### POST /plp/requests/{request_id}/approve

**Purpose:** Approve equipment loan request.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Path Parameters:**
- `request_id` (required): Equipment request ID

**Response 200:**
```json
{
  "success": true,
  "message": "Request approved successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid request state
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - Request not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `plp`

---

### POST /plp/requests/{request_id}/reject

**Purpose:** Reject equipment loan request.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Path Parameters:**
- `request_id` (required): Equipment request ID

**Request Body:**
```json
{
  "reason": "string"
}
```

**Validation Rules:**
- `reason`: REQUIRED

**Response 200:**
```json
{
  "success": true,
  "message": "Request rejected successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Missing reason, invalid request state
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - Request not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `plp`

---

### GET /plp/schedule/requests

**Purpose:** List praktikum schedule requests (pending approval).

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Query Parameters:**
- `status` (optional): Filter by status - `Menunggu` | `Diterima` | `Ditolak`

**Response 200:**
```json
{
  "success": true,
  "message": "Schedule requests retrieved successfully",
  "data": [
    {
      "request_id": "integer",
      "status": "string",
      "course_name": "string",
      "class_name": "string",
      "lab_room": "string",
      "date": "YYYY-MM-DD",
      "start_time": "HH:mm",
      "end_time": "HH:mm",
      "instructor": "string",
      "created_at": "YYYY-MM-DDTHH:mm:ssZ"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`

**Allowed Roles:** `plp`

---

### POST /plp/schedule/requests/{request_id}/approve

**Purpose:** Approve praktikum schedule request. Automatically creates praktikum schedule entry.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Path Parameters:**
- `request_id` (required): Schedule request ID

**Response 200:**
```json
{
  "success": true,
  "message": "Schedule request approved successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid request state
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - Request not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `plp`

**Business Logic:** Upon approval, backend automatically creates `jadwal_praktikum` entry. Frontend does not need to create schedule separately.

---

### POST /plp/schedule/requests/{request_id}/reject

**Purpose:** Reject praktikum schedule request.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Path Parameters:**
- `request_id` (required): Schedule request ID

**Request Body:**
```json
{
  "reason": "string"
}
```

**Validation Rules:**
- `reason`: REQUIRED

**Response 200:**
```json
{
  "success": true,
  "message": "Schedule request rejected successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Missing reason, invalid request state
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - Request not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `plp`

---

### GET /plp/loans

**Purpose:** List equipment loans and returns filtered by PLP's department.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Query Parameters:**
- `status` (optional): Filter by status - `Selesai` | `Menunggu Dikembalikan` | etc.

**Response 200:**
```json
{
  "success": true,
  "message": "Loans retrieved successfully",
  "data": [
    {
      "loan_id": "integer",
      "user_id": "integer",
      "user_name": "string",
      "equipment_type": "string",
      "room_name": "string",
      "status": "string",
      "loan_date": "YYYY-MM-DD",
      "return_date": "YYYY-MM-DD|null",
      "created_at": "YYYY-MM-DDTHH:mm:ssZ"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`

**Allowed Roles:** `plp`

**Security Notes:** Results automatically filtered by PLP's department.

---

### POST /plp/loans/{loan_id}/return

**Purpose:** Mark loan return as completed.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Path Parameters:**
- `loan_id` (required): Loan ID

**Response 200:**
```json
{
  "success": true,
  "message": "Return completed successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid loan state
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `403`: `FORBIDDEN_ROLE`
- `404`: `RESOURCE_NOT_FOUND` - Loan not found
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** `plp`

---

## USER ENDPOINTS

**Base Authorization:** All user endpoints require authentication  
**Auto-Filtering:** Results automatically filtered to current authenticated user

### GET /user/equipment/requests

**Purpose:** List current user's equipment loan requests.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Response 200:**
```json
{
  "success": true,
  "message": "Equipment requests retrieved successfully",
  "data": [
    {
      "request_id": "integer",
      "equipment_type": "string",
      "room_name": "string",
      "responsible_person": "string|null",
      "level": "string",
      "start_time": "string",
      "end_time": "string",
      "purpose": "string",
      "loan_date": "YYYY-MM-DD",
      "return_date": "YYYY-MM-DD|null",
      "status": "string",
      "created_at": "YYYY-MM-DDTHH:mm:ssZ"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`

**Allowed Roles:** All authenticated users (returns only current user's requests)

---

### POST /user/equipment/requests

**Purpose:** Create new equipment loan request.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Request Body:**
```json
{
  "equipment_type": "string",
  "room_name": "string",
  "responsible_person": "string|null",
  "level": "string",
  "start_time": "HH:mm:ss",
  "end_time": "HH:mm:ss",
  "purpose": "string",
  "loan_date": "YYYY-MM-DD",
  "items": [
    {
      "item_id": "integer",
      "item_type": "inventaris|alat|bahan",
      "loan_quantity": "integer"
    }
  ]
}
```

**Validation Rules:**
- `equipment_type`: REQUIRED
- `room_name`: REQUIRED
- `level`: REQUIRED
- `start_time`: REQUIRED, format `HH:mm:ss`
- `end_time`: REQUIRED, format `HH:mm:ss`
- `purpose`: REQUIRED
- `loan_date`: REQUIRED, format `YYYY-MM-DD`
- `items`: REQUIRED, array with at least one item
- `items[].item_id`: REQUIRED
- `items[].item_type`: REQUIRED, one of: `inventaris`, `alat`, `bahan`
- `items[].loan_quantity`: REQUIRED, integer > 0

**Response 200:**
```json
{
  "success": true,
  "message": "Request created successfully",
  "data": {
    "request_id": "integer",
    "department": "string",
    "equipment_type": "string",
    "room_name": "string",
    "responsible_person": "string|null",
    "level": "string",
    "start_time": "string",
    "end_time": "string",
    "purpose": "string",
    "loan_date": "YYYY-MM-DD",
    "return_date": "YYYY-MM-DD|null",
    "status": "Menunggu Konfirmasi",
    "created_at": "YYYY-MM-DDTHH:mm:ssZ"
  }
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid input, missing required fields, empty items array
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** All authenticated users

**Business Logic:** Request is created with status `Menunggu Konfirmasi` and awaits PLP approval.

---

### GET /user/praktikum/schedule

**Purpose:** List current user's praktikum schedules.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Response 200:**
```json
{
  "success": true,
  "message": "Schedule retrieved successfully",
  "data": [
    {
      "schedule_id": "integer",
      "course_name": "string",
      "class_name": "string",
      "lab_room": "string",
      "date": "YYYY-MM-DD",
      "start_time": "HH:mm",
      "end_time": "HH:mm"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`

**Allowed Roles:** All authenticated users (returns only current user's schedule)

---

### GET /user/lab-visits

**Purpose:** List current user's lab visit records.

**Headers:**
```
Authorization: Bearer <jwt_token>
X-API-Version: 1
```

**Response 200:**
```json
{
  "success": true,
  "message": "Lab visits retrieved successfully",
  "data": [
    {
      "visit_id": "integer",
      "lab_room": "string",
      "visit_date": "YYYY-MM-DD",
      "time_range": "HH:mm-HH:mm"
    }
  ]
}
```

**Error Responses:**
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`

**Allowed Roles:** All authenticated users (returns only current user's visits)

---

### POST /user/lab-visits

**Purpose:** Create new lab visit record.

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
X-API-Version: 1
```

**Request Body:**
```json
{
  "lab_room": "string",
  "visit_date": "YYYY-MM-DD",
  "time_range": "string"
}
```

**Validation Rules:**
- All fields: REQUIRED
- `visit_date`: Format `YYYY-MM-DD`

**Response 200:**
```json
{
  "success": true,
  "message": "Lab visit created successfully"
}
```

**Error Responses:**
- `400`: `VALIDATION_ERROR` - Invalid input
- `401`: `AUTH_INVALID_TOKEN` or `AUTH_TOKEN_EXPIRED`
- `500`: `INTERNAL_ERROR`

**Allowed Roles:** All authenticated users

---

## STATUS VALUE ENUMERATIONS

### Equipment Request Status (`status`)
- `Menunggu`
- `Menunggu Konfirmasi`
- `Diterima`
- `Ditolak`
- `Menunggu Dikembalikan`
- `Selesai`

### Equipment Request Item Status (`items[].status`)
- `Menunggu Konfirmasi`
- `Dipinjamkan`
- `Dikembalikan`
- `Habis Pakai`
- `Pemakaian`
- `Ditolak`
- `Hilang`

### Schedule Request Status (`status`)
- `Menunggu`
- `Diterima`
- `Ditolak`

### User Role (`role`)
- `admin`
- `plp`
- `user`

---

## DATE AND TIME FORMATS

All dates and timestamps use ISO 8601 format:

- **Date only:** `YYYY-MM-DD` (e.g., `2025-01-15`)
- **Time only:** `HH:mm` or `HH:mm:ss` (e.g., `14:30` or `14:30:00`)
- **Date-Time:** `YYYY-MM-DDTHH:mm:ssZ` (e.g., `2025-01-15T14:30:00Z`)
- **Time Range:** `HH:mm-HH:mm` (e.g., `08:00-10:00`)

**Timezone:** All timestamps are in server timezone. Frontend MUST handle timezone conversion if needed.

---

## RATE LIMITING AND SECURITY

### Rate Limiting
- All endpoints are rate-limited
- Authentication endpoints have stricter limits
- Rate limit information provided in `429` responses via `retry_after` field
- Frontend MUST respect `retry_after` before retrying

### Reputation System
- Backend tracks IP and user reputation
- Low reputation may result in `403 REPUTATION_BLOCKED`
- `block_until` field indicates temporary block expiration
- Permanent blocks have `block_until: null`

### Token Security
- Token expires after 7 days (authoritative from `expires_at`)
- Token is invalidated on logout
- Token is invalidated on password change
- Token replay protection (same token cannot be used from different IPs/devices)

---

## VERSIONING

### Current Version
- **API Version:** 1.0
- **Version Header:** `X-API-Version: 1`

### Versioning Rules
1. **Breaking changes require new version:**
   - Response field removal
   - Response field type change
   - Endpoint removal
   - Endpoint path change
   - Required field addition

2. **Non-breaking changes (allowed in same version):**
   - New optional response fields
   - New endpoints
   - Bug fixes (behavior correction)

3. **Frontend MUST:**
   - Always send `X-API-Version: 1` header
   - Handle version negotiation if backend supports multiple versions
   - Not assume v2+ features exist

4. **Backend MUST:**
   - Support v1 indefinitely or with deprecation notice period
   - Never break v1 contract without deprecation period

---

## CONTRACT COMPLIANCE

### Frontend Responsibilities
1. **MUST NOT** use endpoints not listed in this contract
2. **MUST NOT** assume undocumented response fields exist
3. **MUST NOT** decode or inspect JWT token payload
4. **MUST** handle all error codes listed in Error Code Registry
5. **MUST** send `X-API-Version: 1` header on all requests
6. **MUST** respect `retry_after` and `block_until` fields
7. **MUST** use `/auth/me` as the ONLY identity endpoint

### Backend Responsibilities
1. **MUST NOT** change response shape without version bump
2. **MUST NOT** remove endpoints without deprecation period
3. **MUST** return all error responses in standard format
4. **MUST** include `error_code` in all error responses
5. **MUST** enforce rate limiting and return `429` with `retry_after`
6. **MUST** enforce reputation blocking and return `403` with `block_until`
7. **MUST** maintain backward compatibility within v1

---

## ENDPOINT EXCLUSIONS

**The following are NOT part of this contract and MUST NOT be used by frontend:**

1. `/user/profile` - Internal alias, use `/auth/me` instead
2. Any endpoint not explicitly listed in this contract
3. Any endpoint with `.php` extension (frontend uses clean REST paths)
4. Web-only endpoints (session-based authentication, file uploads not documented)
5. Internal administrative endpoints not listed in Admin section

**Rule:** If it is not in this contract, frontend MUST NOT rely on it.

---

## IMPLEMENTATION NOTES

1. **Field Normalization:** All field names use `snake_case` English. Database field names or mixed-language keys are NOT exposed.

2. **Null Handling:** Fields marked as `"string|null"` may be `null` or omitted. Frontend MUST handle null values.

3. **Pagination:** Not implemented in v1. Large lists return all results. Future versions may add pagination.

4. **Filtering:** Query parameters are optional. Missing parameters return unfiltered results (subject to role-based scoping).

5. **Transactions:** Multi-item operations (e.g., creating request with items) use database transactions. Partial failures result in rollback.

---

**END OF CONTRACT v1.0**

This contract is frozen and immutable. Breaking changes require API Contract v2.0+.

---

# Backend Implementation Guide: GET /api/auth/capabilities

## Endpoint Overview

### Purpose
The `/api/auth/capabilities` endpoint provides comprehensive user capabilities information required for dynamic sidebar rendering in the Flutter mobile application. This endpoint addresses the limitation where the existing login API only returns a single role string, while the web application uses a granular permission-based access control system.

### Problem Statement
The mobile application sidebar cannot match the web application sidebar because:
- Mobile receives only `"role": "admin"` from login
- Web uses multi-role + granular permissions + department-based access
- Mobile hardcodes menus based on single role, causing incomplete sidebar display

### Solution
This endpoint returns all user capabilities (roles, permissions, jurusan) in a structured format that allows the Flutter app to dynamically build sidebar menus identical to the web application.

## Request Specification

### HTTP Method
GET

### URL
`/api/auth/capabilities`

### Required Headers
```
Authorization: Bearer <token>
Content-Type: application/json
X-API-Version: 1
```

### Example Request (Postman)
```
GET https://laboratorium.poltekkesbanten.ac.id/api/auth/capabilities
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
X-API-Version: 1
```

## Authentication Flow

### Header Extraction
```php
// Extract Authorization header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

if (!$authHeader) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authorization header missing']);
    exit;
}
```

### Bearer Token Validation
```php
// Validate Bearer format
if (!preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid authorization format']);
    exit;
}

$token = trim($matches[1]);

// Validate token format (64 character hex)
if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token format']);
    exit;
}
```

### Database Token Verification
```php
// Hash token for database lookup
$tokenHash = hash('sha256', $token);

// Query valid token
$stmt = $pdo->prepare("
    SELECT u.id, u.role, u.jurusan
    FROM api_tokens at
    INNER JOIN users u ON at.user_id = u.id
    WHERE at.token_hash = ?
    AND at.expires_at > NOW()
    AND at.revoked_at IS NULL
");

$stmt->execute([$tokenHash]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}
```

## Database Assumptions

### Table Structure
The implementation assumes the following database tables (create if missing):

#### users table
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    role VARCHAR(50),
    jurusan VARCHAR(100),
    -- other fields...
);
```

#### user_roles table (for multi-role support)
```sql
CREATE TABLE user_roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED,
    role VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### user_permissions table (for granular permissions)
```sql
CREATE TABLE user_permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED,
    permission VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### user_jurusan table (for department access)
```sql
CREATE TABLE user_jurusan (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED,
    jurusan VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Relationships
- One user can have multiple roles (user_roles)
- One user can have multiple permissions (user_permissions)
- One user can access multiple departments (user_jurusan)
- Permissions are explicit strings, not inferred from roles

## PHP Implementation

### File Location
Create: `api/auth/capabilities.php`

### Complete Implementation
```php
<?php
header('Content-Type: application/json; charset=utf-8');

// CORS handling (same as other endpoints)
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Version, User-Agent');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Extract and validate Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authorization header missing']);
        exit;
    }

    if (!preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid authorization format']);
        exit;
    }

    $token = trim($matches[1]);

    if (strlen($token) !== 64 || !ctype_xdigit($token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token format']);
        exit;
    }

    // Database connection
    $pdo = Database::getInstance()->getConnection();

    // Verify token and get user
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT u.id, u.role, u.jurusan
        FROM api_tokens at
        INNER JOIN users u ON at.user_id = u.id
        WHERE at.token_hash = ?
        AND at.expires_at > NOW()
        AND at.revoked_at IS NULL
    ");

    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }

    // Query user roles
    $roles = [];
    $stmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $roles[] = $row['role'];
    }

    // Fallback to single role if no multi-role records
    if (empty($roles) && $user['role']) {
        $roles[] = $user['role'];
    }

    // Query user permissions
    $permissions = [];
    $stmt = $pdo->prepare("SELECT permission FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions[] = $row['permission'];
    }

    // Query user jurusan access
    $jurusan = [];
    $stmt = $pdo->prepare("SELECT jurusan FROM user_jurusan WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $jurusan[] = $row['jurusan'];
    }

    // Fallback to user jurusan if no multi-jurusan records
    if (empty($jurusan) && $user['jurusan']) {
        $jurusan[] = $user['jurusan'];
    }

    // Build response
    $capabilities = [
        'roles' => $roles,
        'permissions' => $permissions,
        'jurusan' => $jurusan
    ];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Capabilities retrieved successfully',
        'data' => $capabilities
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Capabilities endpoint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
```

## Example Success Response

### Status Code: 200 OK
```json
{
  "success": true,
  "message": "Capabilities retrieved successfully",
  "data": {
    "roles": ["admin", "plp", "user"],
    "permissions": [
      "ADMIN_USERS_READ",
      "ADMIN_USERS_MANAGE",
      "ADMIN_MASTER_DATA",
      "PLP_BARANG_READ",
      "PLP_BARANG_KEPERAWATAN",
      "PLP_BARANG_KEBIDANAN",
      "PLP_BARANG_TLM",
      "PLP_JADWAL_KEPERAWATAN",
      "PLP_JADWAL_KEBIDANAN",
      "PLP_JADWAL_TLM",
      "PLP_REQUEST_PERALATAN_KEPERAWATAN",
      "PLP_REQUEST_PERALATAN_KEBIDANAN",
      "PLP_REQUEST_PERALATAN_TLM",
      "PLP_REQUEST_JADWAL_KEPERAWATAN",
      "PLP_REQUEST_JADWAL_KEBIDANAN",
      "PLP_REQUEST_JADWAL_TLM",
      "PLP_PINJAMAN_KEPERAWATAN",
      "PLP_PINJAMAN_KEBIDANAN",
      "PLP_PINJAMAN_TLM",
      "PLP_LAPORAN_KEPERAWATAN",
      "PLP_LAPORAN_KEBIDANAN",
      "PLP_LAPORAN_TLM",
      "USER_REQUEST_PERALATAN",
      "USER_JADWAL_PRAKTIKUM",
      "USER_KUNJUNGAN_LAB"
    ],
    "jurusan": ["keperawatan", "kebidanan", "tlm"]
  }
}
```

## Example Error Responses

### 401 Unauthorized (Invalid Token)
```json
{
  "success": false,
  "message": "Invalid or expired token"
}
```

### 401 Unauthorized (Missing Authorization Header)
```json
{
  "success": false,
  "message": "Authorization header missing"
}
```

### 401 Unauthorized (Invalid Token Format)
```json
{
  "success": false,
  "message": "Invalid token format"
}
```

### 403 Forbidden (Token Valid but No Permissions)
```json
{
  "success": false,
  "message": "Access denied"
}
```

### 405 Method Not Allowed
```json
{
  "success": false,
  "message": "Method not allowed. Only GET method is accepted."
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Internal server error"
}
```

## Postman Testing Guide

### Step 1: Login to Get Token
1. Create POST request to `/api/auth/login.php`
2. Set body to:
```json
{
  "username": "your_username",
  "password": "your_password"
}
```
3. Execute request
4. Copy the `token` value from the response data

### Step 2: Test Capabilities Endpoint
1. Create GET request to `/api/auth/capabilities`
2. Add header: `Authorization: Bearer <paste_token_here>`
3. Add header: `X-API-Version: 1`
4. Execute request

### Expected Results
- Status: 200 OK
- Response matches the success format above
- Contains appropriate roles, permissions, and jurusan for the logged-in user

### Common Testing Issues
- **401 Unauthorized**: Token expired or invalid
- **Missing Bearer prefix**: Header must be exactly `Authorization: Bearer <token>`
- **Wrong token format**: Token must be 64 hexadecimal characters
- **CORS errors**: Ensure correct Origin header if testing from different domain

## Notes for Flutter Integration

### Capability Storage
Store the capabilities response in your AuthProvider or equivalent state management:
```dart
class AuthProvider extends ChangeNotifier {
  List<String> _roles = [];
  List<String> _permissions = [];
  List<String> _jurusan = [];

  void setCapabilities(Map<String, dynamic> capabilities) {
    _roles = List<String>.from(capabilities['roles']);
    _permissions = List<String>.from(capabilities['permissions']);
    _jurusan = List<String>.from(capabilities['jurusan']);
    notifyListeners();
  }

  bool hasPermission(String permission) {
    return _permissions.contains(permission);
  }

  bool hasRole(String role) {
    return _roles.contains(role);
  }

  bool hasJurusan(String jurusan) {
    return _jurusan.contains(jurusan);
  }
}
```

### Sidebar Menu Logic
Replace role-based menu logic with permission-based logic:

**Before (Incorrect):**
```dart
if (user.role == 'admin') {
  // Show admin menus
}
```

**After (Correct):**
```dart
if (authProvider.hasPermission('ADMIN_USERS_READ')) {
  // Show user management menu
}

if (authProvider.hasPermission('PLP_BARANG_KEPERAWATAN')) {
  // Show nursing inventory menu
}
```

### Menu Visibility Mapping
Map backend permissions directly to menu visibility:
- `ADMIN_USERS_READ` → Show "Users" menu
- `ADMIN_USERS_MANAGE` → Show "Manage Users" submenu
- `PLP_BARANG_KEPERAWATAN` → Show "Daftar Barang > Keperawatan"
- `USER_JADWAL_PRAKTIKUM` → Show "Jadwal Praktikum" menu

### Architecture Benefits
1. **Dynamic Menus**: Menus appear/disappear based on actual user permissions
2. **Consistency**: Mobile sidebar matches web sidebar exactly
3. **Security**: No hardcoded assumptions about user capabilities
4. **Scalability**: Adding new permissions doesn't require Flutter code changes
5. **Audit Trail**: Clear mapping between backend permissions and UI elements

This implementation transforms your Flutter application from role-based access control to proper capability-based access control, ensuring complete parity with the web application.
# ✅ API Testing Checklist

Checklist untuk memastikan semua endpoint berfungsi dengan baik.

---

## 🔐 Authentication Endpoints

### ✅ Login
- [ ] `POST /api/auth/login.php`
  - [ ] Login dengan username & password valid → Success + token
  - [ ] Login dengan username salah → Error 401
  - [ ] Login dengan password salah → Error 401
  - [ ] Request tanpa username/password → Error 400

### ✅ Logout
- [ ] `POST /api/auth/logout.php`
  - [ ] Logout dengan token valid → Success
  - [ ] Logout dengan token invalid → Error 401
  - [ ] Logout tanpa token → Error 401

### ✅ Profile
- [ ] `GET /api/user/profile.php`
  - [ ] Get profile dengan token valid → Success + user data
  - [ ] Get profile tanpa token → Error 401
  - [ ] Get profile dengan token expired → Error 401

### ✅ Change Password
- [ ] `POST /api/auth/change-password.php`
  - [ ] Change password dengan old password benar → Success
  - [ ] Change password dengan old password salah → Error 400
  - [ ] Change password dengan new password < 6 karakter → Error 400

---

## 👨‍💼 Admin Endpoints

### ✅ Users List
- [ ] `GET /api/admin/users.php`
  - [ ] Get users dengan role admin → Success + list users
  - [ ] Get users dengan role bukan admin → Error 403
  - [ ] Get users dengan search parameter → Filtered results

### ✅ Manage Users
- [ ] `GET /api/admin/manage-users.php`
  - [ ] Get manage users dengan role admin → Success
  - [ ] Get dengan role bukan admin → Error 403

### ✅ Add User
- [ ] `POST /api/admin/users/add.php`
  - [ ] Add user dengan data valid → Success + user data
  - [ ] Add user dengan username duplikat → Error 400
  - [ ] Add user dengan email duplikat → Error 400
  - [ ] Add user dengan password < 6 karakter → Error 400
  - [ ] Add user dengan role invalid → Error 400

### ✅ Edit User
- [ ] `PUT /api/admin/users/edit.php?id={id}`
  - [ ] Edit user dengan data valid → Success
  - [ ] Edit user yang tidak ada → Error 404
  - [ ] Edit dengan email duplikat → Error 400

### ✅ Delete User
- [ ] `DELETE /api/admin/users/delete.php?id={id}`
  - [ ] Delete user yang ada → Success
  - [ ] Delete user yang tidak ada → Error 404
  - [ ] Delete user sendiri → Error 400 (prevented)

### ✅ Master Data (Rooms)
- [ ] `GET /api/admin/master-data.php`
  - [ ] Get rooms dengan role admin → Success + list rooms

### ✅ Add Room
- [ ] `POST /api/admin/rooms/add.php`
  - [ ] Add room dengan data valid → Success
  - [ ] Add room tanpa required fields → Error 400

### ✅ Edit Room
- [ ] `PUT /api/admin/rooms/edit.php?id={id}`
  - [ ] Edit room dengan data valid → Success
  - [ ] Edit room yang tidak ada → Error 404

### ✅ Delete Room
- [ ] `DELETE /api/admin/rooms/delete.php?id={id}`
  - [ ] Delete room yang ada → Success
  - [ ] Delete room yang tidak ada → Error 404

---

## 🔬 PLP Endpoints

### ✅ Items List
- [ ] `GET /api/plp/items.php`
  - [ ] Get items dengan role plp → Success + filtered by jurusan
  - [ ] Get items dengan filter type=inventaris → Only inventaris
  - [ ] Get items dengan filter type=alat → Only alat
  - [ ] Get items dengan filter type=bahan → Only bahan

### ✅ Praktikum Schedule
- [ ] `GET /api/plp/praktikum/schedule.php`
  - [ ] Get schedule dengan role plp → Success + filtered by jurusan

### ✅ Equipment Requests
- [ ] `GET /api/plp/equipment/requests.php`
  - [ ] Get requests dengan role plp → Success
  - [ ] Get requests dengan filter status → Filtered results

### ✅ Request Detail
- [ ] `GET /api/plp/requests/detail.php?id={id}`
  - [ ] Get detail dengan request yang ada → Success + items
  - [ ] Get detail dengan request yang tidak ada → Error 404

### ✅ Approve Request
- [ ] `POST /api/plp/requests/approve.php?id={id}`
  - [ ] Approve request dengan status "Menunggu Konfirmasi" → Success
  - [ ] Approve request yang sudah disetujui → Error 400
  - [ ] Approve request yang tidak ada → Error 404

### ✅ Reject Request
- [ ] `POST /api/plp/requests/reject.php?id={id}`
  - [ ] Reject request dengan status "Menunggu Konfirmasi" → Success
  - [ ] Reject request yang sudah disetujui → Error 400

### ✅ Schedule Requests
- [ ] `GET /api/plp/schedule/requests.php`
  - [ ] Get schedule requests dengan role plp → Success

### ✅ Approve Schedule Request
- [ ] `POST /api/plp/schedule/requests/approve.php?id={id}`
  - [ ] Approve schedule request → Success + jadwal_praktikum created

### ✅ Reject Schedule Request
- [ ] `POST /api/plp/schedule/requests/reject.php?id={id}`
  - [ ] Reject schedule request → Success

### ✅ Loans
- [ ] `GET /api/plp/loans.php`
  - [ ] Get loans dengan role plp → Success + items included

### ✅ Mark Return
- [ ] `POST /api/plp/loans/return.php?id={id}`
  - [ ] Mark return dengan items valid → Success
  - [ ] Update loan status to "Selesai"

---

## 👤 User Endpoints

### ✅ Equipment Requests
- [ ] `GET /api/user/equipment/requests.php`
  - [ ] Get user's own requests → Success (only user's data)

### ✅ Create Equipment Request
- [ ] `POST /api/user/equipment/request/create.php`
  - [ ] Create request dengan data valid → Success
  - [ ] Create request tanpa required fields → Error 400
  - [ ] Create request tanpa items → Error 400
  - [ ] Verify peminjaman dan peminjaman_detail created

### ✅ Praktikum Schedule
- [ ] `GET /api/user/praktikum/schedule.php`
  - [ ] Get schedule → Success (filtered by jurusan if available)

### ✅ Lab Visits
- [ ] `GET /api/user/lab-visits.php`
  - [ ] Get user's lab visits → Success (only user's data)

### ✅ Create Lab Visit
- [ ] `POST /api/user/lab-visits/create.php`
  - [ ] Create lab visit dengan data valid → Success
  - [ ] Create tanpa required fields → Error 400

---

## 🔒 Security Tests

### ✅ Token Validation
- [ ] Request tanpa token → Error 401
- [ ] Request dengan token invalid → Error 401
- [ ] Request dengan token expired → Error 401

### ✅ Role-Based Access
- [ ] Admin endpoint dengan role admin → Success
- [ ] Admin endpoint dengan role plp → Error 403
- [ ] Admin endpoint dengan role user → Error 403
- [ ] PLP endpoint dengan role plp → Success
- [ ] PLP endpoint dengan role user → Error 403

### ✅ SQL Injection Protection
- [ ] Test dengan input yang berisi SQL commands
- [ ] Verify tidak ada SQL injection yang berhasil

### ✅ Input Validation
- [ ] Test dengan input kosong
- [ ] Test dengan input invalid format
- [ ] Test dengan input terlalu panjang

---

## 📊 Data Integrity Tests

### ✅ Foreign Key Constraints
- [ ] Delete user yang memiliki api_tokens → Tokens terhapus (CASCADE)
- [ ] Create peminjaman dengan user_id valid → Success
- [ ] Create peminjaman dengan user_id tidak ada → Error (if FK enforced)

### ✅ Transaction Safety
- [ ] Create request dengan beberapa items → All or nothing
- [ ] Approve schedule request → jadwal_praktikum created in same transaction

---

## 🌐 CORS & Headers

### ✅ CORS Headers
- [ ] Request dari Flutter Web → CORS headers present
- [ ] OPTIONS preflight request → Success 200

### ✅ Content-Type
- [ ] Request dengan Content-Type: application/json → Success
- [ ] Response Content-Type: application/json

---

## 📝 Response Format

### ✅ Success Response
- [ ] All endpoints return `{"success": true, "data": {...}, "message": "..."}`

### ✅ Error Response
- [ ] All errors return `{"success": false, "message": "..."}`
- [ ] HTTP status codes correct (400, 401, 403, 404, 500)

---

## ✅ Final Checklist

- [ ] Semua endpoint di-test
- [ ] Error handling bekerja dengan baik
- [ ] Token expiration bekerja (30 hari)
- [ ] Role-based access bekerja
- [ ] Database transactions bekerja
- [ ] CORS dikonfigurasi dengan benar
- [ ] Flutter app dapat connect ke API
- [ ] Production ready

---

**Testing Tools:**
- Postman (recommended)
- cURL (command line)
- Flutter Web (`flutter run -d chrome`)
- Browser DevTools (Network tab)

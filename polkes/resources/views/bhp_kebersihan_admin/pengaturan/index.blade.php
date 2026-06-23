@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Pengaturan')

@section('content')
<div class="pa-card">
  <h2>Profil Admin BHP Kebersihan</h2>
  <table class="table table-sm mt-3" style="max-width:480px;">
    <tr><th>Nama</th><td>{{ optional($admin?->user)->name ?? \App\Support\BhpKebersihan\BhpAdminSession::displayName(request()) }}</td></tr>
    <tr><th>Username</th><td>{{ optional($admin?->user)->username ?? '-' }}</td></tr>
    <tr><th>Role</th><td>{{ $admin?->roleLabel() ?? ucfirst(str_replace('_', ' ', $role ?? '')) }}</td></tr>
  </table>
  <p class="text-muted mt-3 mb-0" style="font-size:.9rem;">
    Modul BHP Kebersihan terpisah dari Persediaan Aset BMN. Hubungi superadmin untuk penambahan akun admin.
  </p>
</div>
@endsection

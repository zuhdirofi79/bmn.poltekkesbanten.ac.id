@extends('layouts.persediaan_admin')

@section('page_title', 'Pengaturan')

@php $roleLabel = ['superadmin_persediaan' => 'Superadmin Persediaan', 'admin_unit' => 'Admin Unit']; @endphp

@section('content')
<div class="pa-card mt-0">
  <h2>Admin Persediaan</h2>
  <p class="text-muted">Daftar akun yang memiliki akses dashboard Persediaan. Struktur role disiapkan untuk pengembangan admin per unit (Keperawatan, Kebidanan, TLM, Direktorat, PPPK, Pimpinan).</p>
  <div class="table-responsive mt-2">
    <table class="pa-table">
      <thead><tr><th>User ID</th><th>Nama</th><th>Username</th><th>Role</th><th>Unit</th><th>Status</th></tr></thead>
      <tbody>
        @forelse($admins as $a)
          @php $u = $userMap[$a->user_id] ?? null; @endphp
          <tr>
            <td>{{ $a->user_id }}</td>
            <td>{{ optional($u)->name ?? '-' }}</td>
            <td>{{ optional($u)->username ?? '-' }}</td>
            <td><span class="prs-badge disetujui">{{ $roleLabel[$a->role] ?? $a->role }}</span></td>
            <td>{{ $a->unit_kerja ?: 'Semua Unit' }}</td>
            <td>@if($a->is_active)<span class="prs-badge selesai">Aktif</span>@else<span class="prs-badge ditolak">Nonaktif</span>@endif</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-3">Belum ada admin.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pa-card">
  <h2>Informasi Modul</h2>
  <ul class="text-muted mb-0">
    <li>Modul Persediaan terpisah dari Aset BMN (aset tetap), Peminjaman, Sewa, dan Pemeliharaan.</li>
    <li>Login pemohon (NIP) dan login admin Persediaan terisolasi dari login admin BMN utama dan admin Sewa.</li>
    <li>Nomor permintaan otomatis: PRM-[KODE UNIT]-[TAHUN]-[URUTAN].</li>
  </ul>
</div>
@endsection

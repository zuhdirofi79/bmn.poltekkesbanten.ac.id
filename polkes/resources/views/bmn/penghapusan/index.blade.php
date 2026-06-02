@extends('layouts.bmn_admin')

@section('page_title', 'Penghapusan BMN')
@section('breadcrumbs')
  <a href="{{ route('bmn.dashboard.index') }}">Dashboard</a> / Penghapusan BMN / Dashboard Penghapusan
@endsection

@section('content')
<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon blue"><i class="ti ti-file-plus"></i></div>
    <div class="bmn-stat-body"><div class="label">Usulan Baru</div><div class="value">—</div><div class="sub">Modul dalam pengembangan</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon green"><i class="ti ti-progress"></i></div>
    <div class="bmn-stat-body"><div class="label">Dalam Proses</div><div class="value">—</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon yellow"><i class="ti ti-check"></i></div>
    <div class="bmn-stat-body"><div class="label">Disetujui</div><div class="value">—</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon red"><i class="ti ti-x"></i></div>
    <div class="bmn-stat-body"><div class="label">Ditolak</div><div class="value">—</div></div>
  </div>
</div>

<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Modul Penghapusan BMN</h2>
      <div class="subtitle">Tampilan mengikuti referensi desain — fitur akan diaktifkan bertahap</div>
    </div>
  </div>
  <div class="bmn-card-body">
    <p class="text-muted mb-0">
      Modul penghapusan aset BMN (usulan, verifikasi, riwayat, laporan) akan tersedia pada tahap berikutnya.
      Saat ini modul aset, peminjaman, pengembalian, sewa, dan pemeliharaan sudah aktif.
    </p>
  </div>
</div>
@endsection

@extends('layouts.bmn_admin')

@section('page_title', 'Pemeliharaan BMN')
@section('breadcrumbs')
  <a href="{{ route('bmn.dashboard.index') }}">Dashboard</a> / Pemeliharaan BMN / Dashboard Pemeliharaan
@endsection

@section('content')
<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon blue"><i class="ti ti-box"></i></div>
    <div class="bmn-stat-body"><div class="label">Total Barang</div><div class="value">—</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon green"><i class="ti ti-calendar"></i></div>
    <div class="bmn-stat-body"><div class="label">Jadwal Hari Ini</div><div class="value">—</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon yellow"><i class="ti ti-alert-circle"></i></div>
    <div class="bmn-stat-body"><div class="label">Perlu Tindak Lanjut</div><div class="value">—</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon red"><i class="ti ti-tools"></i></div>
    <div class="bmn-stat-body"><div class="label">Dalam Perbaikan</div><div class="value">—</div></div>
  </div>
</div>

<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Layanan Pemeliharaan Aset BMN</h2>
      <div class="subtitle">Pemeliharaan dan perbaikan aset internal Poltekkes Kemenkes Banten</div>
    </div>
  </div>
  <div class="bmn-card-body">
    <p>Pemeliharaan aset BMN dikelola oleh tim teknis internal berdasarkan permintaan unit kerja dan jadwal rutin.</p>
    <ol class="small text-muted">
      <li>Unit kerja mengajukan permintaan melalui nota dinas / surat resmi.</li>
      <li>Tim BMN melakukan identifikasi kerusakan dan ketersediaan suku cadang.</li>
      <li>Pemeliharaan dijadwalkan dan dilaporkan setelah selesai.</li>
    </ol>
    <p class="mb-0 small">Informasi publik tersedia di <a href="{{ route('public.pemeliharaan.index') }}" target="_blank">portal pemeliharaan publik</a>.</p>
  </div>
</div>
@endsection

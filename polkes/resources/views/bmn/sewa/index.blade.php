@extends('layouts.bmn_admin')

@section('page_title', 'Sewa BMN')
@section('breadcrumbs')
  <a href="{{ route('bmn.dashboard.index') }}">Dashboard</a> / Sewa BMN
@endsection

@section('content')
@php
  $pendingPay = $list->getCollection()->where('payment_status', 'pending')->count();
  $confirmedPay = $list->getCollection()->where('payment_status', 'confirmed')->count();
@endphp

<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon yellow"><i class="ti ti-clock"></i></div>
    <div class="bmn-stat-body"><div class="label">Menunggu Konfirmasi</div><div class="value">{{ $pendingPay }}</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon green"><i class="ti ti-check"></i></div>
    <div class="bmn-stat-body"><div class="label">Pembayaran OK</div><div class="value">{{ $confirmedPay }}</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon blue"><i class="ti ti-receipt"></i></div>
    <div class="bmn-stat-body"><div class="label">Total Pengajuan</div><div class="value">{{ $list->total() }}</div></div>
  </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Data Pengajuan Sewa</h2>
      <div class="subtitle">Konfirmasi pembayaran dan koordinasi ke tim</div>
    </div>
  </div>
  <div class="bmn-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Penyewa</th>
            <th>Jenis</th>
            <th>Layanan</th>
            <th>Status</th>
            <th>Pembayaran</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($list as $row)
            <tr>
              <td><code>{{ $row->kode_sewa }}</code></td>
              <td>{{ $row->nama_lengkap }}</td>
              <td>{{ $row->jenisPenyewaLabel() }}</td>
              <td>{{ optional($row->tarif)->nama_layanan ?? '-' }}</td>
              <td><span class="badge badge-bmn-secondary rounded-pill">{{ $row->status }}</span></td>
              <td>
                <span class="badge rounded-pill {{ $row->payment_status === 'confirmed' ? 'badge-bmn-success' : ($row->payment_status === 'rejected' ? 'badge-bmn-danger' : 'badge-bmn-warning') }}">
                  {{ $row->payment_status }}
                </span>
              </td>
              <td><a href="{{ route('bmn.sewa.show', $row->id) }}" class="btn btn-bmn-primary btn-sm">Detail</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan sewa.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-3">{{ $list->links() }}</div>
  </div>
</div>
@endsection

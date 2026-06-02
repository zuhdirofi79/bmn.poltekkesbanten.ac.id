@extends('layouts.sewa_admin')

@section('page_title', 'Dashboard Admin Sewa')
@section('breadcrumbs')
  Dashboard Admin Sewa BMN
@endsection

@section('content')
<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon blue"><i class="ti ti-receipt"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Pengajuan Masuk</div>
      <div class="value">{{ number_format($pengajuanMasuk, 0, ',', '.') }}</div>
      <div class="sub">Total seluruh pengajuan</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon yellow"><i class="ti ti-clock"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Menunggu Pembayaran</div>
      <div class="value">{{ number_format($menungguPembayaran, 0, ',', '.') }}</div>
      <div class="sub">Belum diverifikasi Bu Indah</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon green"><i class="ti ti-check"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Terbayar</div>
      <div class="value">{{ number_format($terbayar, 0, ',', '.') }}</div>
      <div class="sub">Pembayaran ke BLU terkonfirmasi</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon navy"><i class="ti ti-users"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Perlu Koordinasi</div>
      <div class="value">{{ number_format($perluKoordinasi, 0, ',', '.') }}</div>
      <div class="sub">Sudah terbayar, belum dikoordinasi</div>
    </div>
  </div>
</div>

<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon green"><i class="ti ti-circle-check"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Selesai Bulan Ini</div>
      <div class="value">{{ number_format($selesaiBulanIni, 0, ',', '.') }}</div>
      <div class="sub">Koordinasi selesai bulan berjalan</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon red"><i class="ti ti-x"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Pembayaran Ditolak (Bulan Ini)</div>
      <div class="value">{{ number_format($ditolakBulanIni, 0, ',', '.') }}</div>
      <div class="sub">Verifikasi tidak valid</div>
    </div>
  </div>
</div>

<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Pengajuan Sewa Terbaru</h2>
      <div class="subtitle">8 pengajuan paling akhir masuk</div>
    </div>
    <a href="{{ route('sewa-admin.sewa.index') }}" class="btn btn-bmn-primary btn-sm">Lihat Semua</a>
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
            <th>Tanggal</th>
            <th>Pembayaran</th>
            <th>Koordinasi</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($recent as $row)
            <tr>
              <td><code>{{ $row->kode_sewa }}</code></td>
              <td>{{ $row->nama_lengkap }}</td>
              <td>{{ $row->jenisPenyewaLabel() }}</td>
              <td>{{ optional($row->tarif)->nama_layanan ?? '-' }}</td>
              <td>{{ optional($row->tanggal_mulai_sewa)->format('d-m-Y') }}</td>
              <td>
                @include('sewa_admin.sewa._payment_badge', ['row' => $row])
              </td>
              <td>
                @include('sewa_admin.sewa._coord_badge', ['row' => $row])
              </td>
              <td><a href="{{ route('sewa-admin.sewa.show', $row->id) }}" class="btn btn-bmn-primary btn-sm">Detail</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada pengajuan sewa.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

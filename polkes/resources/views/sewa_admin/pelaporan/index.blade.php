@extends('layouts.sewa_admin')

@section('page_title', 'Pelaporan Bulanan Sewa')
@section('breadcrumbs')
  <a href="{{ route('sewa-admin.dashboard') }}">Dashboard</a> / Pelaporan Bulanan
@endsection

@push('styles')
<style>
  @media print {
    .bmn-sidebar, .bmn-header, .no-print { display: none !important; }
    .bmn-main { margin-left: 0 !important; }
    .bmn-content { padding: 0 !important; }
  }
</style>
@endpush

@section('content')
<div class="bmn-card mb-3 no-print">
  <div class="bmn-card-header">
    <div>
      <h2>Filter Periode</h2>
      <div class="subtitle">Pilih bulan & tahun pelaporan</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('sewa-admin.pelaporan.export-excel', request()->query()) }}" class="btn btn-success btn-sm">
        <i class="ti ti-file-spreadsheet"></i> Export Excel
      </a>
      <a href="{{ route('sewa-admin.pelaporan.export-csv', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-file-export"></i> Export CSV
      </a>
      <button type="button" onclick="window.print();" class="btn btn-bmn-primary btn-sm">
        <i class="ti ti-printer"></i> Cetak
      </button>
    </div>
  </div>
  <div class="bmn-card-body">
    <form method="get" action="{{ route('sewa-admin.pelaporan.index') }}" class="row g-2">
      <div class="col-md-3">
        <label class="form-label small mb-1">Bulan</label>
        <select name="bulan" class="form-select form-select-sm">
          @foreach($monthOptions as $k => $v)
            <option value="{{ $k }}" @selected($month === $k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Tahun</label>
        <select name="tahun" class="form-select form-select-sm">
          @foreach($yearOptions as $y)
            <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-bmn-primary btn-sm w-100">Tampilkan</button>
      </div>
    </form>
  </div>
</div>

<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon blue"><i class="ti ti-receipt"></i></div>
    <div class="bmn-stat-body"><div class="label">Total Pengajuan</div><div class="value">{{ $totalPengajuan }}</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon green"><i class="ti ti-check"></i></div>
    <div class="bmn-stat-body"><div class="label">Terbayar</div><div class="value">{{ $totalTerbayar }}</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon yellow"><i class="ti ti-clock"></i></div>
    <div class="bmn-stat-body"><div class="label">Pending</div><div class="value">{{ $totalPending }}</div></div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon red"><i class="ti ti-x"></i></div>
    <div class="bmn-stat-body"><div class="label">Ditolak</div><div class="value">{{ $totalDitolak }}</div></div>
  </div>
</div>

<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon navy"><i class="ti ti-currency-dollar"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Total Biaya Umum (Terbayar)</div>
      <div class="value">Rp {{ number_format($totalNominalUmum, 0, ',', '.') }}</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon green"><i class="ti ti-currency-dollar"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Total Biaya Pegawai (Terbayar)</div>
      <div class="value">Rp {{ number_format($totalNominalPegawai, 0, ',', '.') }}</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon blue"><i class="ti ti-cash"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Total Biaya Seluruh (Terbayar)</div>
      <div class="value">Rp {{ number_format($totalBiayaTerbayar, 0, ',', '.') }}</div>
    </div>
  </div>
</div>

<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Laporan Pengajuan — {{ $monthOptions[$month] }} {{ $year }}</h2>
      <div class="subtitle">Termasuk surat resmi dan bukti pembayaran</div>
    </div>
  </div>
  <div class="bmn-card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead>
          <tr>
            <th>No</th>
            <th>Kode Sewa</th>
            <th>Tgl Pengajuan</th>
            <th>Nama Penyewa</th>
            <th>No WA</th>
            <th>Email</th>
            <th>Jenis</th>
            <th>Layanan</th>
            <th class="text-end">Tarif</th>
            <th>Mulai</th>
            <th>Kembali</th>
            <th class="text-end">Hari</th>
            <th class="text-end">Total Biaya</th>
            <th>Status Pembayaran</th>
            <th>Diverifikasi Oleh</th>
            <th>Tgl Verifikasi</th>
            <th>Bukti</th>
            <th>Surat</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody>
          @forelse($list as $i => $row)
            @php
              $tarif      = $row->tarif;
              $nilai      = $row->tarifDipakai();
              $rowHari    = $row->jumlahHariDihitung();
              $rowTotal   = $row->totalBiayaDihitung();
            @endphp
            <tr>
              <td>{{ $i + 1 }}</td>
              <td><code>{{ $row->kode_sewa }}</code></td>
              <td>{{ optional($row->created_at)->format('d-m-Y H:i') }}</td>
              <td>{{ $row->nama_lengkap }}</td>
              <td>{{ $row->nomor_wa }}</td>
              <td>{{ $row->email }}</td>
              <td>{{ $row->jenisPenyewaLabel() }}</td>
              <td>{{ optional($tarif)->nama_layanan ?? '-' }}</td>
              <td class="text-end">{{ $nilai !== null ? 'Rp '.number_format($nilai, 0, ',', '.') : '-' }}</td>
              <td>{{ optional($row->tanggal_mulai_sewa)->format('d-m-Y') }}</td>
              <td>{{ optional($row->tanggal_rencana_kembali)->format('d-m-Y') }}</td>
              <td class="text-end">{{ $rowHari > 0 ? $rowHari : '-' }}</td>
              <td class="text-end fw-semibold text-primary">{{ $rowTotal !== null ? 'Rp '.number_format($rowTotal, 0, ',', '.') : '-' }}</td>
              <td>@include('sewa_admin.sewa._payment_badge', ['row' => $row])</td>
              <td>{{ optional($row->adminPaymentBy)->name ?? '-' }}</td>
              <td>{{ optional($row->admin_payment_at)->format('d-m-Y H:i') ?? '-' }}</td>
              <td>
                @if($row->buktiBayarUrl())
                  <a href="{{ $row->buktiBayarUrl() }}" target="_blank" class="btn btn-outline-primary btn-sm">Bukti</a>
                @else - @endif
              </td>
              <td>
                @if($row->suratResmiUrl())
                  <a href="{{ $row->suratResmiUrl() }}" target="_blank" class="btn btn-outline-primary btn-sm">Surat</a>
                @else - @endif
              </td>
              <td>{{ $row->admin_payment_note ?? $row->catatan ?? '' }}</td>
            </tr>
          @empty
            <tr><td colspan="19" class="text-center text-muted py-4">Tidak ada data pengajuan pada periode ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

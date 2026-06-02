@extends('layouts.sewa_admin')

@section('page_title', 'Daftar Pengajuan Sewa')
@section('breadcrumbs')
  <a href="{{ route('sewa-admin.dashboard') }}">Dashboard</a> / Sewa
@endsection

@section('content')
<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Pengajuan Sewa BMN</h2>
      <div class="subtitle">Filter, lihat detail, dan kelola pembayaran/koordinasi</div>
    </div>
    @if(!empty($quickLabel))
      <div class="d-flex align-items-center gap-2">
        <span class="badge badge-bmn-info rounded-pill px-3 py-2">Filter: {{ $quickLabel }}</span>
        <a href="{{ route('sewa-admin.sewa.index') }}" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-x"></i> Reset
        </a>
      </div>
    @endif
  </div>
  <div class="bmn-card-body">
    {{-- Kirim ulang quick agar quick filter tidak hilang saat user submit form filter detail --}}
    <form method="get" action="{{ route('sewa-admin.sewa.index') }}" class="row g-2 mb-3">
      @if(!empty($quick))
        <input type="hidden" name="quick" value="{{ $quick }}">
      @endif
      <div class="col-md-3">
        <label class="form-label small mb-1">Cari (kode/nama/WA/email)</label>
        <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Cari...">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Semua</option>
          @foreach($statusOptions as $k => $v)
            <option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Pembayaran</label>
        <select name="payment_status" class="form-select form-select-sm">
          <option value="">Semua</option>
          @foreach($paymentOptions as $k => $v)
            <option value="{{ $k }}" @selected($filters['payment_status'] === $k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Jenis Penyewa</label>
        <select name="jenis_penyewa" class="form-select form-select-sm">
          <option value="">Semua</option>
          @foreach($jenisOptions as $k => $v)
            <option value="{{ $k }}" @selected($filters['jenis_penyewa'] === $k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Bulan (YYYY-MM)</label>
        <input type="month" name="bulan" value="{{ $filters['bulan'] }}" class="form-control form-control-sm">
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button type="submit" class="btn btn-bmn-primary btn-sm w-100">Filter</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama Penyewa</th>
            <th>No WA</th>
            <th>Email</th>
            <th>Jenis</th>
            <th>Layanan</th>
            <th>Mulai</th>
            <th>Kembali</th>
            <th class="text-end">Hari</th>
            <th class="text-end">Total Biaya</th>
            <th>Pembayaran</th>
            <th>Koordinasi</th>
            <th>Verifikator</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($list as $row)
            <tr>
              <td><code>{{ $row->kode_sewa }}</code></td>
              <td>{{ $row->nama_lengkap }}</td>
              <td>
                @php $no = preg_replace('/\D+/', '', (string) $row->nomor_wa); if (str_starts_with($no, '0')) { $no = '62'.substr($no,1); } @endphp
                <a href="https://wa.me/{{ $no }}" target="_blank" rel="noopener">{{ $row->nomor_wa }}</a>
              </td>
              <td>
                <a href="mailto:{{ $row->email }}">{{ $row->email }}</a>
                @if(method_exists($row, 'isEmailVerified') && $row->isEmailVerified())
                  <span class="badge bg-success ms-1" title="Email sudah diverifikasi pemohon">
                    <i class="ti ti-circle-check"></i>
                  </span>
                @else
                  <span class="badge bg-warning text-dark ms-1" title="Email belum diverifikasi pemohon">
                    <i class="ti ti-mail-question"></i>
                  </span>
                @endif
              </td>
              <td>{{ $row->jenisPenyewaLabel() }}</td>
              <td>{{ optional($row->tarif)->nama_layanan ?? '-' }}</td>
              <td>{{ optional($row->tanggal_mulai_sewa)->format('d-m-Y') }}</td>
              <td>{{ optional($row->tanggal_rencana_kembali)->format('d-m-Y') }}</td>
              @php
                $rowHari  = $row->jumlahHariDihitung();
                $rowTotal = $row->totalBiayaDihitung();
              @endphp
              <td class="text-end">{{ $rowHari > 0 ? $rowHari : '-' }}</td>
              <td class="text-end fw-semibold text-primary">
                {{ $rowTotal !== null ? 'Rp '.number_format($rowTotal, 0, ',', '.') : '-' }}
              </td>
              <td>@include('sewa_admin.sewa._payment_badge', ['row' => $row])</td>
              <td>@include('sewa_admin.sewa._coord_badge', ['row' => $row])</td>
              <td class="small">{{ optional($row->adminPaymentBy)->name ?? '-' }}</td>
              <td><a href="{{ route('sewa-admin.sewa.show', $row->id) }}" class="btn btn-bmn-primary btn-sm">Detail</a></td>
            </tr>
          @empty
            <tr><td colspan="14" class="text-center text-muted py-4">Tidak ada data sesuai filter.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-2">{{ $list->links() }}</div>
  </div>
</div>
@endsection

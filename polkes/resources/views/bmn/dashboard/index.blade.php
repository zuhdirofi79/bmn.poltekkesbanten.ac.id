@extends('layouts.bmn_admin')

@section('page_title', 'Dashboard')
@section('breadcrumbs')
  <a href="{{ route('bmn.dashboard.index') }}">Dashboard</a> / Dashboard Utama BMN
@endsection

@section('content')
<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon blue"><i class="ti ti-box"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Total Aset</div>
      <div class="value">{{ number_format($totalAset, 0, ',', '.') }}</div>
      <div class="sub"><a href="{{ route('bmn.aset.index') }}" class="text-primary text-decoration-none">Lihat Detail →</a></div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon green"><i class="ti ti-circle-check"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Aset Tersedia</div>
      <div class="value">{{ number_format($asetAktif, 0, ',', '.') }}</div>
      <div class="sub">{{ $totalAset > 0 ? number_format($asetAktif / $totalAset * 100, 2, ',', '.') : '0' }}% dari total aset</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon yellow"><i class="ti ti-clock"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Dalam Peminjaman</div>
      <div class="value">{{ number_format($asetDipinjam, 0, ',', '.') }}</div>
      <div class="sub">{{ $totalAset > 0 ? number_format($asetDipinjam / $totalAset * 100, 2, ',', '.') : '0' }}% dari total aset</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon red"><i class="ti ti-tools"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Butuh Pemeliharaan</div>
      <div class="value">{{ number_format($asetRusak, 0, ',', '.') }}</div>
      <div class="sub">Kondisi rusak ringan/berat</div>
    </div>
  </div>
</div>

<div class="bmn-stat-row">
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon navy"><i class="ti ti-clipboard-list"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Pengajuan Pending</div>
      <div class="value">{{ $pendingPeminjaman }}</div>
      <div class="sub">Menunggu persetujuan</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon blue"><i class="ti ti-clipboard-check"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Peminjaman Aktif</div>
      <div class="value">{{ $activePeminjaman }}</div>
      <div class="sub">Status dipinjam</div>
    </div>
  </div>
  <div class="bmn-stat-card">
    <div class="bmn-stat-icon yellow"><i class="ti ti-receipt"></i></div>
    <div class="bmn-stat-body">
      <div class="label">Sewa Pending</div>
      <div class="value">{{ $pendingSewa }}</div>
      <div class="sub">Menunggu konfirmasi bayar</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="bmn-card">
      <div class="bmn-card-header">
        <div>
          <h2>Aset Terbaru</h2>
          <div class="subtitle">5 aset terakhir ditambahkan</div>
        </div>
        <a href="{{ route('bmn.aset.index') }}" class="btn btn-bmn-primary btn-sm">Lihat Semua Aset</a>
      </div>
      <div class="bmn-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>No</th>
                <th>Kode Barang</th>
                <th>NUP</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentAset as $i => $aset)
                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td><code>{{ $aset->kode_barang }}</code></td>
                  <td>{{ $aset->nup ?? '-' }}</td>
                  <td>{{ $aset->nama_barang }}</td>
                  <td>{{ optional($aset->kategori)->nama_kategori ?? '-' }}</td>
                  <td>
                    @php
                      $st = $aset->status ?? 'aktif';
                      $badge = match($st) {
                        'aktif' => 'badge-bmn-success',
                        'dipinjam' => 'badge-bmn-warning',
                        default => 'badge-bmn-secondary',
                      };
                    @endphp
                    <span class="badge {{ $badge }} rounded-pill">{{ ucfirst($st) }}</span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data aset.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="bmn-card">
      <div class="bmn-card-header">
        <div>
          <h2>Pengajuan Terbaru</h2>
          <div class="subtitle">Peminjaman internal terakhir</div>
        </div>
      </div>
      <div class="bmn-card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Status</th>
                <th>Tgl</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentPeminjaman as $p)
                <tr>
                  <td><a href="{{ route('bmn.peminjaman-aset.show', $p->id) }}"><code>{{ $p->kode_peminjaman }}</code></a></td>
                  <td><span class="badge badge-bmn-warning rounded-pill">{{ ucfirst($p->status) }}</span></td>
                  <td>{{ optional($p->tanggal_pinjam)->format('Y-m-d') }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted py-4">Belum ada pengajuan.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

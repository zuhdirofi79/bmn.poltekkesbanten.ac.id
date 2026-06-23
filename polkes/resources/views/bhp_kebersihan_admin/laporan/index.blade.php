@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Laporan BHP Kebersihan')

@section('content')
<div class="pa-card">
  <ul class="nav nav-tabs mb-3">
    @foreach(['permintaan' => 'Permintaan BHP', 'keluar' => 'Barang Keluar', 'stok' => 'Stok Akhir', 'opname' => 'Opname'] as $key => $label)
      <li class="nav-item">
        <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('bhp.admin.laporan.index', array_merge(request()->except('tab'), ['tab' => $key])) }}">{{ $label }}</a>
      </li>
    @endforeach
  </ul>

  <form method="GET" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="col-md-2"><label class="form-label">Dari</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Sampai</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></div>
    @if($tab === 'permintaan')
    <div class="col-md-2">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="">Semua</option>
        @foreach(['pending','disetujui','siap_diambil','sudah_diambil','ditolak'] as $s)
          <option value="{{ $s }}" @selected(($filters['status'] ?? '')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
        @endforeach
      </select>
    </div>
    @endif
    @if(in_array($tab, ['permintaan','keluar','stok']))
    <div class="col-md-3">
      <label class="form-label">Barang</label>
      <select name="barang_id" class="form-select">
        <option value="">Semua barang</option>
        @foreach($barangs as $b)
          <option value="{{ $b->id }}" @selected(($filters['barang_id'] ?? '')==$b->id)>{{ $b->kode_barang }} — {{ $b->nama_barang }}</option>
        @endforeach
      </select>
    </div>
    @endif
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-prs-blue">Filter</button>
      <a href="{{ route('bhp.admin.laporan.export', request()->query()) }}" class="btn btn-outline-success">Export Excel</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr>
          @if($tab === 'permintaan')
            <th>Nomor</th><th>Tanggal</th><th>Barang</th><th>Pengambil</th><th>Jumlah</th><th>Status</th>
          @elseif($tab === 'keluar')
            <th>Tanggal</th><th>Kode</th><th>Nama</th><th>Jumlah</th><th>Sumber</th><th>Catatan</th>
          @elseif($tab === 'stok')
            <th>Kode</th><th>Nama</th><th>Satuan</th><th>Awal</th><th>Masuk</th><th>Keluar</th><th>Akhir</th>
          @else
            <th>Tanggal Opname</th><th>Catatan</th><th>Jumlah Barang</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          <tr>
            @foreach($row as $cell)
              <td>{{ $cell }}</td>
            @endforeach
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada data untuk filter ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

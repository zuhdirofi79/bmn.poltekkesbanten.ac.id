@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Stok Akhir BHP')

@section('content')
<div class="pa-card">
  <form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-md-2"><label class="form-label">Dari</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Sampai</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Cari</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Kode / nama barang"></div>
    <div class="col-md-2"><label class="form-label">&nbsp;</label>
      <div class="form-check"><input type="checkbox" name="menipis" value="1" class="form-check-input" @checked($filters['menipis'] ?? false)><label class="form-check-label">Stok menipis</label></div>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-prs-blue">Filter</button>
      <a href="{{ route('bhp.admin.stok-akhir.export', request()->query()) }}" class="btn btn-outline-success">Export Excel</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr><th>Kode</th><th>Nama Barang</th><th>Satuan</th><th>Stok Awal</th><th>Barang Masuk</th><th>Barang Keluar</th><th>Stok Akhir</th></tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td>{{ $r['kode'] }}</td>
            <td>{{ $r['nama'] }}</td>
            <td>{{ $r['satuan'] }}</td>
            <td>{{ $r['awal'] }}</td>
            <td>{{ $r['masuk'] }}</td>
            <td>{{ $r['keluar'] }}</td>
            <td><strong>{{ $r['akhir'] }}</strong></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

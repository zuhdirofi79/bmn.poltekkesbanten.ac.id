@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Detail Stock Opname')

@section('content')
<div class="pa-card">
  <div class="d-flex justify-content-between mb-3">
    <div>
      <h2>Opname {{ optional($opname->tanggal_opname)->format('d-m-Y') }}</h2>
      @if($opname->catatan)<p class="text-muted mb-0">{{ $opname->catatan }}</p>@endif
    </div>
    <a href="{{ route('bhp.admin.opname.export', $opname->id) }}" class="btn btn-outline-success btn-sm">Export Excel</a>
  </div>

  <div class="table-responsive">
    <table class="pa-table">
      <thead><tr><th>Kode</th><th>Nama</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Selisih</th><th>Keterangan</th></tr></thead>
      <tbody>
        @foreach($opname->details as $d)
          <tr>
            <td>{{ $d->barang?->kode_barang }}</td>
            <td>{{ $d->barang?->nama_barang }}</td>
            <td>{{ $d->stok_sistem }}</td>
            <td>{{ $d->stok_fisik }}</td>
            <td class="{{ $d->selisih != 0 ? 'text-danger fw-bold' : '' }}">{{ $d->selisih }}</td>
            <td>{{ $d->keterangan ?: '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection

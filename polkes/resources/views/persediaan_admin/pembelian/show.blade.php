@extends('layouts.persediaan_admin')

@section('page_title', 'Detail Pembelian')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="{{ route('persediaan.admin.pembelian.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="ti ti-arrow-left"></i> Kembali
  </a>
</div>

<div class="pa-card mt-0">
  <h2>Faktur {{ $pembelian->nomor_faktur }}</h2>
  <div class="row mt-2">
    <div class="col-md-3"><span class="text-muted">Tanggal:</span> <strong>{{ optional($pembelian->tanggal)->format('d M Y') }}</strong></div>
    <div class="col-md-3"><span class="text-muted">Penyedia:</span> <strong>{{ $pembelian->penyedia ?: '-' }}</strong></div>
    <div class="col-md-3"><span class="text-muted">Unit:</span> <strong>{{ $pembelian->unit_kerja ?: '-' }}</strong></div>
    <div class="col-md-3"><span class="text-muted">Total:</span> <strong>Rp {{ number_format($pembelian->total, 0, ',', '.') }}</strong></div>
  </div>

  <div class="mt-3 d-flex flex-wrap gap-2">
    @foreach(['faktur_path'=>'Faktur','bast_path'=>'BAST','invoice_path'=>'Invoice','foto_barang_path'=>'Foto Barang'] as $col => $lbl)
      @if($pembelian->$col)
        <a href="{{ asset($pembelian->$col) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-file"></i> {{ $lbl }}
        </a>
      @endif
    @endforeach
  </div>
</div>

<div class="pa-card">
  <h2>Item</h2>
  <div class="table-responsive mt-2">
    <table class="pa-table">
      <thead><tr><th>No</th><th>Kode</th><th>Nama</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Jumlah</th><th class="text-end">Subtotal</th></tr></thead>
      <tbody>
        @foreach($pembelian->items as $i => $it)
          <tr>
            <td>{{ $i+1 }}</td>
            <td><code>{{ $it->kode_barang }}</code></td>
            <td>{{ $it->nama_barang }}</td>
            <td>{{ $it->satuan }}</td>
            <td class="text-end">Rp {{ number_format($it->harga_satuan, 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($it->jumlah, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($it->subtotal, 0, ',', '.') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection

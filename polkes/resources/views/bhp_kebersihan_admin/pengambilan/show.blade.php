@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Tindak Lanjut Pengambilan')

@section('content')
<div class="pa-card">
  <h2 class="mb-3"><code>{{ $permintaan->nomor_permintaan }}</code></h2>

  <table class="table table-sm mb-4">
    <tr><th>Barang</th><td>{{ $permintaan->barang?->nama_barang }}</td></tr>
    <tr><th>Pengambil</th><td>{{ $permintaan->nama_pengambil }} ({{ $permintaan->no_wa_pengambil }})</td></tr>
    <tr><th>Jumlah</th><td>{{ $permintaan->jumlah_diminta }} {{ $permintaan->barang?->satuan }}</td></tr>
    <tr><th>Disetujui oleh</th><td>{{ optional($permintaan->verifier)->name ?? '-' }}</td></tr>
  </table>

  <form method="POST" action="{{ route('bhp.admin.pengambilan.sudah-diambil', $permintaan->id) }}" onsubmit="return confirm('Konfirmasi barang sudah diambil? Stok akan berkurang.');">
    @csrf
    <div class="mb-3">
      <label class="form-label">Catatan (opsional)</label>
      <textarea name="process_note" class="form-control" rows="2"></textarea>
    </div>
    <button type="submit" class="btn btn-prs-green btn-lg"><i class="ti ti-package-export"></i> Barang Sudah Diambil</button>
    <a href="{{ route('bhp.admin.pengambilan.index') }}" class="btn btn-outline-secondary ms-2">Kembali</a>
  </form>
</div>
@endsection

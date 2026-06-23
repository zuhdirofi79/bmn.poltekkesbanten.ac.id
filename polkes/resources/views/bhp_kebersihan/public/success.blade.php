@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Permintaan Berhasil — BHP Kebersihan')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=2">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="prs-public-card text-center" style="max-width:520px;margin:0 auto;">
    <div class="mb-3" style="font-size:48px;color:var(--prs-teal);"><i class="ti ti-circle-check"></i></div>
    <h1 class="h4">Permintaan Berhasil Dikirim</h1>
    <p class="mb-2">Nomor permintaan Anda:</p>
    <p class="fs-4"><code>{{ $permintaan->nomor_permintaan }}</code></p>
    <p class="text-muted">
      Permintaan pengambilan BHP berhasil dikirim dan menunggu verifikasi admin.
    </p>
    <hr>
    <p class="small mb-0">
      Barang: <strong>{{ $permintaan->barang?->nama_barang }}</strong><br>
      Jumlah: {{ $permintaan->jumlah_diminta }} {{ $permintaan->barang?->satuan }}
    </p>
  </div>
</div>
@endsection

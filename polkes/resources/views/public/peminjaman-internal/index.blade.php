@extends('layouts.public_bmn')

@section('title', 'Peminjaman Internal')

@section('content')
<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Peminjaman Internal</h2>
      <div class="subtitle">Pilih layanan pengajuan peminjaman atau pengembalian</div>
    </div>
  </div>
  <div class="bmn-card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <a href="{{ route('public.peminjaman-internal.pinjam') }}" class="btn btn-bmn-primary btn-lg w-100 py-4">
          <i class="ti ti-file-plus fs-4 d-block mb-2"></i>
          Ajukan Peminjaman Internal
        </a>
      </div>
      <div class="col-md-6">
        <a href="{{ route('public.peminjaman-internal.kembali') }}" class="btn btn-bmn-outline-success btn-lg w-100 py-4">
          <i class="ti ti-rotate-2 fs-4 d-block mb-2"></i>
          Ajukan Pengembalian Internal
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

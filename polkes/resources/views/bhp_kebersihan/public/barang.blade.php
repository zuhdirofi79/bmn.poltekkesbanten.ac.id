@extends('layouts.public-bmn-dashboard')

@section('page_title', $barang->nama_barang . ' — BHP Kebersihan')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=2">
  <link rel="stylesheet" href="{{ asset('css/bhp-kebersihan.css') }}?v=2">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="prs-public-card bhp-detail-card">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <h1 class="h4 mb-1">{{ $barang->nama_barang }}</h1>
        <p class="text-muted mb-0">Form pengambilan BHP Kebersihan</p>
      </div>
      <a href="{{ route('public.bhp-kebersihan.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left"></i> Kembali
      </a>
    </div>

    <div class="row g-2 mb-3 bhp-detail-meta">
      <div class="col-6">
        <label class="text-muted small">Kode Barang</label>
        <div class="fw-semibold">{{ $barang->kode_barang }}</div>
      </div>
      <div class="col-6">
        <label class="text-muted small">Satuan</label>
        <div class="fw-semibold">{{ $barang->satuan }}</div>
      </div>
      <div class="col-6">
        <label class="text-muted small">Status Barang</label>
        <div>
          @if($barang->isAktif())
            <span class="badge bg-success">Aktif</span>
          @else
            <span class="badge bg-secondary">Nonaktif</span>
          @endif
        </div>
      </div>
      <div class="col-6">
        <label class="text-muted small">Stok Tersedia</label>
        <div class="bhp-stok-inline">{{ $stokTersedia }} {{ $barang->satuan }}</div>
      </div>
    </div>

    <div class="text-center mb-4 p-3 rounded bhp-stok-box">
      <div class="text-muted small">Stok Tersedia (Realtime)</div>
      <div class="bhp-stok">{{ $stokTersedia }}</div>
      <div class="text-muted small">{{ $barang->satuan }}</div>
    </div>

    <hr>

    <h2 class="h5 mb-3">Form Pengambilan</h2>

    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('public.bhp-kebersihan.barang.store', $barang->barcode_token) }}">
      @csrf
      <div class="mb-3">
        <label class="form-label">Nama Lengkap Pengambil <span class="text-danger">*</span></label>
        <input type="text" name="nama_pengambil" value="{{ old('nama_pengambil') }}" class="form-control" required maxlength="200">
      </div>
      <div class="mb-3">
        <label class="form-label">Nomor WhatsApp <span class="text-danger">*</span></label>
        <input type="tel" name="no_wa_pengambil" value="{{ old('no_wa_pengambil') }}" class="form-control" placeholder="08123456789" required maxlength="20">
        <small class="text-muted">Format Indonesia: 08xxxxxxx / 62xxxxxxx / +62xxxxxxx.</small>
      </div>
      <div class="mb-3">
        <label class="form-label">Tanggal Pengambilan</label>
        <input type="date" value="{{ $today }}" class="form-control" readonly disabled>
      </div>
      <div class="mb-3">
        <label class="form-label">Jumlah Diminta <span class="text-danger">*</span></label>
        <input type="number" name="jumlah_diminta" value="{{ old('jumlah_diminta', 1) }}" min="1" max="{{ max(1, $stokTersedia) }}" class="form-control" required>
        <small class="text-muted">Maksimal {{ $stokTersedia }} {{ $barang->satuan }}</small>
      </div>
      <div class="mb-3">
        <label class="form-label">Catatan</label>
        <textarea name="catatan" rows="2" class="form-control" maxlength="2000" placeholder="Opsional — kebutuhan atau lokasi penggunaan">{{ old('catatan') }}</textarea>
      </div>
      <button type="submit" class="btn btn-prs-blue w-100 btn-lg" @disabled($stokTersedia < 1)>
        <i class="ti ti-send"></i> Ajukan Pengambilan
      </button>
      @if($stokTersedia < 1)
        <p class="text-danger text-center mt-2 mb-0">Stok habis. Tidak dapat mengajukan permintaan.</p>
      @endif
    </form>
  </div>
</div>
@endsection

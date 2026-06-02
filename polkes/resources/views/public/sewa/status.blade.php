@extends('layouts.public_bmn')

@section('title', 'Cek Status Pengajuan Sewa')

@section('content')
<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Cek Status Pengajuan Sewa</h2>
      <div class="subtitle">Masukkan kode sewa beserta email atau nomor WA yang Anda gunakan saat pengajuan</div>
    </div>
  </div>
  <div class="bmn-card-body">
    @if($errors->any())
      <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
      </div>
    @endif
    @if(!empty($notFound))
      <div class="alert alert-warning small">
        Data tidak ditemukan. Pastikan kode sewa dan email/nomor WA Anda benar.
      </div>
    @endif

    <form method="post" action="{{ route('public.sewa.status.submit') }}" class="row g-3">
      @csrf
      <div class="col-md-6">
        <label class="form-label">Kode Sewa <span class="text-danger">*</span></label>
        <input type="text" name="kode_sewa" class="form-control" required maxlength="100"
               value="{{ $old['kode_sewa'] ?? '' }}" placeholder="Contoh: SW-BMN-20260601-AB12C">
      </div>
      <div class="col-md-6">
        <label class="form-label">Email / Nomor WA <span class="text-danger">*</span></label>
        <input type="text" name="identitas" class="form-control" required maxlength="255"
               value="{{ $old['identitas'] ?? '' }}" placeholder="email@contoh.com atau 0812xxxx">
      </div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-bmn-primary">
          <i class="ti ti-search"></i> Cek Status
        </button>
        <a href="{{ route('public.landing') }}" class="btn btn-outline-secondary">Kembali</a>
      </div>
    </form>
  </div>
</div>

@if(!empty($pengajuan))
@php
  $payCls = 'badge-bmn-warning';
  if ($pengajuan->payment_status === 'confirmed') { $payCls = 'badge-bmn-success'; }
  if ($pengajuan->payment_status === 'rejected')  { $payCls = 'badge-bmn-danger'; }

  $coordCls = 'badge-bmn-secondary';
  if ($pengajuan->coordination_status === 'sent_to_team') { $coordCls = 'badge-bmn-info'; }
  if ($pengajuan->coordination_status === 'done')         { $coordCls = 'badge-bmn-success'; }
@endphp

<div class="bmn-card mt-3">
  <div class="bmn-card-header">
    <div>
      <h2>Status Pengajuan</h2>
      <div class="subtitle">Kode: <code>{{ $pengajuan->kode_sewa }}</code></div>
    </div>
  </div>
  <div class="bmn-card-body">
    <div class="row g-2 small">
      <div class="col-md-6"><strong>Nama:</strong> {{ $pengajuan->nama_lengkap }}</div>
      <div class="col-md-6"><strong>Layanan:</strong> {{ optional($pengajuan->tarif)->nama_layanan ?? '-' }}</div>
      <div class="col-md-6"><strong>Status Pembayaran:</strong>
        <span class="badge rounded-pill {{ $payCls }}">{{ $pengajuan->paymentStatusLabel() }}</span>
      </div>
      <div class="col-md-6"><strong>Status:</strong> <code>{{ $pengajuan->status }}</code></div>
      <div class="col-md-6"><strong>Koordinasi:</strong>
        <span class="badge rounded-pill {{ $coordCls }}">{{ $pengajuan->coordinationStatusLabel() }}</span>
      </div>
    </div>
    <hr>
    <div class="alert alert-info small mb-0">
      {{ $pengajuan->publicMessage() }}
    </div>
  </div>
</div>
@endif
@endsection

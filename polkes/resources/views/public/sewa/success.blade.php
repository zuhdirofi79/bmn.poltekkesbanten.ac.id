@extends('layouts.public_bmn')

@section('title', 'Pengajuan Sewa Berhasil')

@push('styles')
<style>
  .waiting-spinner {
    width: 64px; height: 64px; border: 4px solid #e9ecef;
    border-top-color: #5c6bc0; border-radius: 50%;
    animation: spin 1s linear infinite; margin: 0 auto 1rem;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  .pulse-text { animation: pulse 2s ease-in-out infinite; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .55; } }
</style>
@endpush

@php
  use App\User;
  $waText = 'Halo Bu Retno, saya mengajukan sewa BMN dengan kode '.$pengajuan->kode_sewa;
  $waUrl = 'https://wa.me/'.User::SEWA_ADMIN_RETNO_WA_INTL.'?text='.rawurlencode($waText);
@endphp

@section('content')
<div class="bmn-card text-center">
  <div class="bmn-card-body p-5">
    <div class="waiting-spinner"></div>
    <i class="ti ti-clock fs-1 text-primary mb-2 d-block"></i>
    <h4 class="fw-semibold text-success mb-3">Pengajuan sewa berhasil dikirim</h4>
    <p class="text-muted pulse-text mb-4">Status: Menunggu verifikasi pembayaran</p>

    <div class="alert alert-light border text-start mx-auto" style="max-width: 560px;">
      <p class="mb-2"><strong>Kode pengajuan:</strong> <code>{{ $pengajuan->kode_sewa }}</code></p>
      @if(method_exists($pengajuan, 'isEmailVerified') && $pengajuan->isEmailVerified())
        <p class="mb-2 text-success">
          <i class="ti ti-mail-check"></i>
          Email <strong>{{ $pengajuan->email }}</strong> sudah terverifikasi.
        </p>
      @else
        <p class="mb-2 text-warning">
          <i class="ti ti-alert-triangle"></i>
          Email <strong>{{ $pengajuan->email }}</strong> menunggu verifikasi —
          cek inbox Anda untuk kode OTP. Verifikasi WhatsApp sementara dinonaktifkan.
        </p>
      @endif
      <p class="mb-2">Pengajuan sewa berhasil dikirim. Silakan tunggu konfirmasi berikutnya.</p>
      <hr>
      <p class="mb-1"><strong>Admin Koordinasi Sewa:</strong></p>
      <p class="mb-2"><strong>Bu Retno</strong> — {{ User::SEWA_ADMIN_RETNO_WA_DISPLAY }}</p>
      <p class="mb-0 small text-muted">Hubungi Bu Retno untuk konfirmasi koordinasi sampai hari H pemakaian aset.</p>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-center gap-2 mt-4">
      <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="btn btn-success">
        <i class="ti ti-brand-whatsapp"></i> Hubungi Bu Retno via WhatsApp
      </a>
      <a href="{{ route('public.sewa.status', ['kode_sewa' => $pengajuan->kode_sewa]) }}" class="btn btn-outline-primary">
        <i class="ti ti-search"></i> Cek Status Pengajuan
      </a>
      <a href="{{ route('public.landing') }}" class="btn btn-outline-secondary">Beranda</a>
    </div>
  </div>
</div>
@endsection

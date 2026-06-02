@extends('layouts.guest_bmn')

@section('title', 'Verifikasi Email Pengajuan BMN')

@php
  /**
   * Adaptor display per-type. Sewa memakai field `email` & `nama_lengkap`;
   * peminjaman/pengembalian memakai `public_email` & `public_nama`.
   */
  $emailDisplay = (string) ($record->public_email ?? $record->email ?? '-');
  $waDisplay    = (string) ($record->public_no_wa ?? $record->nomor_wa ?? '-');
  $kodeDisplay  = (string) ($record->kode_peminjaman ?? $record->kode_sewa ?? ('#'.$record->id));

  $typeLabel = match ($type) {
      'sewa' => 'sewa',
      'pengembalian' => 'pengembalian',
      default => 'peminjaman',
  };
@endphp

@section('content')
  <h5 class="fw-semibold mb-3 text-center">
    <i class="ti ti-shield-check"></i> Verifikasi Email Pengajuan BMN
  </h5>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="alert alert-info small mb-3">
    <strong>Email wajib diverifikasi</strong> untuk memastikan pengajuan berasal dari pemohon yang valid.
    Kode 6 digit telah dikirim ke <strong>{{ $emailDisplay }}</strong> (cek juga folder Spam/Promotions).
    Verifikasi kontak <strong>tidak</strong> sama dengan persetujuan admin BMN.
  </div>

  <div class="alert alert-warning small mb-3">
    <strong>Verifikasi WhatsApp sementara dinonaktifkan.</strong>
    Nomor WhatsApp <code>{{ $waDisplay }}</code> tetap kami simpan agar petugas
    BMN dapat menghubungi Anda jika diperlukan.
  </div>

  @if(($record->verification_status ?? '') === 'verified')
    <div class="alert alert-success">
      <i class="ti ti-circle-check"></i>
      Email sudah terverifikasi. Pengajuan {{ $typeLabel }} menunggu pemrosesan oleh admin BMN.
    </div>
    @if(!empty($successRedirectUrl ?? null))
      <a href="{{ $successRedirectUrl }}" class="btn btn-primary w-100">
        Lanjut ke Halaman Status
      </a>
    @endif
  @else
    <form method="post"
          action="{{ route('bmn.public-verification.verify', ['type' => $type, 'id' => $record->id, 'token' => $token]) }}">
      @csrf
      <div class="mb-3">
        <label class="form-label">Kode OTP Email (6 digit) <span class="text-danger">*</span></label>
        <input type="text" name="email_otp" maxlength="6" pattern="\d{6}" inputmode="numeric"
               class="form-control text-center letter-spacing-1 @error('email_otp') is-invalid @enderror"
               style="letter-spacing: .35rem; font-size: 1.1rem;"
               value="{{ old('email_otp') }}" required autocomplete="one-time-code"
               placeholder="000000">
        @error('email_otp')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <i class="ti ti-check"></i> Verifikasi Email
      </button>
    </form>
  @endif

  <p class="small text-muted mt-3 mb-0 text-center">
    Pengajuan {{ $typeLabel }} BMN — <code>{{ $kodeDisplay }}</code>
  </p>
@endsection

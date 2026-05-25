@extends('layouts.guest_bmn')

@section('title', 'Verifikasi OTP BMN')

@section('content')
  <h5 class="fw-semibold mb-3 text-center">
    <i class="ti ti-shield-check"></i> Verifikasi Email &amp; WhatsApp
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

  <div class="alert alert-warning small">
    Masukkan kode OTP yang dikirim ke <strong>{{ $record->public_email }}</strong>
    dan WhatsApp <strong>{{ $record->public_no_wa }}</strong>.
    Verifikasi kontak <strong>tidak</strong> sama dengan persetujuan admin BMN.
  </div>

  @if(($record->verification_status ?? '') === 'verified')
    <div class="alert alert-success">
      Kontak sudah terverifikasi. Pengajuan menunggu persetujuan admin BMN.
    </div>
  @else
    <form method="post"
          action="{{ route('bmn.public-verification.verify', ['type' => $type, 'id' => $record->id, 'token' => $token]) }}">
      @csrf
      <div class="mb-3">
        <label class="form-label">OTP Email <span class="text-danger">*</span></label>
        <input type="text" name="email_otp" maxlength="6" pattern="\d{6}"
               class="form-control @error('email_otp') is-invalid @enderror"
               value="{{ old('email_otp') }}" required autocomplete="one-time-code">
        @error('email_otp')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="mb-3">
        <label class="form-label">OTP WhatsApp <span class="text-danger">*</span></label>
        <input type="text" name="wa_otp" maxlength="6" pattern="\d{6}"
               class="form-control @error('wa_otp') is-invalid @enderror"
               value="{{ old('wa_otp') }}" required autocomplete="one-time-code">
        @error('wa_otp')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <i class="ti ti-check"></i> Verifikasi
      </button>
    </form>
  @endif

  <p class="small text-muted mt-3 mb-0 text-center">
    Pengajuan {{ $type === 'peminjaman' ? 'peminjaman' : 'pengembalian' }} BMN —
    @if($type === 'peminjaman')
      <code>{{ $record->kode_peminjaman ?? ('#'.$record->id) }}</code>
    @else
      transaksi #{{ $record->id }}
    @endif
  </p>
@endsection

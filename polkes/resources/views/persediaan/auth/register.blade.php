@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Registrasi Pemohon Persediaan')

@php use App\Support\Persediaan\UnitKerja; @endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=1">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="prs-auth-wrap" style="max-width:560px;">
    <div class="prs-public-card">
      <span class="prs-section-title">Registrasi Pemohon Persediaan</span>

      <form method="POST" action="{{ route('public.persediaan.register.store', $unitSlug) }}" class="mt-3">
        @csrf
        <div class="mb-3">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="nama" value="{{ old('nama') }}"
                 class="form-control @error('nama') is-invalid @enderror" required>
          @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label">NIP / NIDN / NIK</label>
          <input type="text" name="nip" value="{{ old('nip') }}"
                 class="form-control @error('nip') is-invalid @enderror" required>
          @error('nip')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Nomor WA</label>
            <input type="text" name="nomor_wa" value="{{ old('nomor_wa') }}"
                   class="form-control @error('nomor_wa') is-invalid @enderror" placeholder="08xxxxxxxxxx">
            @error('nomor_wa')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Jabatan</label>
            <input type="text" name="jabatan" value="{{ old('jabatan') }}"
                   class="form-control @error('jabatan') is-invalid @enderror">
            @error('jabatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Unit Kerja</label>
            <select name="unit_kerja" class="form-select @error('unit_kerja') is-invalid @enderror" required>
              @foreach($units as $u)
                <option value="{{ $u }}" @selected(old('unit_kerja', $unitName) === $u)>
                  {{ UnitKerja::label($u) }}
                </option>
              @endforeach
            </select>
            @error('unit_kerja')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
          </div>
        </div>

        <button type="submit" class="btn btn-prs-teal w-100">
          <i class="ti ti-user-plus"></i> Daftar
        </button>
      </form>

      <div class="text-center mt-3">
        <span class="text-muted">Sudah punya akun?</span>
        <a href="{{ route('public.persediaan.login', $unitSlug) }}">Login</a>
      </div>
    </div>
  </div>
</div>
@endsection

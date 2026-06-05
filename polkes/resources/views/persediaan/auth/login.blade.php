@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Login Pemohon Persediaan')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=1">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="prs-auth-wrap">
    <div class="prs-public-card">
      <span class="prs-section-title">Login Pemohon Persediaan</span>
      <p class="prs-unit-badge">{{ $unitName }}</p>

      @if(session('error'))<div class="alert alert-danger mt-3">{{ session('error') }}</div>@endif
      @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif

      <form method="POST" action="{{ route('public.persediaan.login.attempt', $unitSlug) }}" class="mt-3">
        @csrf
        <div class="mb-3">
          <label class="form-label">Nama / Username</label>
          <input type="text" name="login" value="{{ old('login') }}"
                 class="form-control @error('login') is-invalid @enderror"
                 placeholder="Masukkan nama atau username" required autofocus>
          @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password"
                 class="form-control @error('password') is-invalid @enderror" required>
          @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-check mb-3">
          <input type="checkbox" name="remember" id="remember" class="form-check-input">
          <label class="form-check-label" for="remember">Ingat saya</label>
        </div>
        <button type="submit" class="btn btn-prs-blue w-100">
          <i class="ti ti-login"></i> Masuk
        </button>
      </form>

      <div class="text-center mt-3">
        <span class="text-muted">Belum punya akun?</span>
        <a href="{{ route('public.persediaan.register', $unitSlug) }}">Daftar di sini</a>
      </div>
      <div class="text-center mt-2">
        <a href="{{ route('public.persediaan.index') }}" class="text-muted">
          <i class="ti ti-arrow-left"></i> Pilih unit kerja lain
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

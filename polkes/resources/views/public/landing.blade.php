@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Portal Layanan BMN')

@section('content')
{{-- 
  Landing publik BMN — KHUSUS LAYANAN PUBLIK.
  Tidak ada: ringkasan layanan, donut chart, notifikasi,
              card layanan BMN kanan, tabel layanan tersedia.
  Hanya: header minimalis + 4 card layanan utama (2x2).
--}}

<section class="portal-hero">
  <div class="portal-hero-inner">
    <img src="{{ asset('img/logo.png') }}"
         alt="Logo Poltekkes Kemenkes Banten"
         class="portal-hero-logo"
         onerror="this.style.display='none';">
    <h1 class="portal-hero-title">Sistem Informasi Barang Milik Negara</h1>
    <p class="portal-hero-sub">Poltekkes Kemenkes Banten</p>
  </div>
</section>

<section class="portal-menu-section">
  <div class="portal-menu-grid">

    {{-- 1. ASET BMN --}}
    <a href="{{ url('/login') }}"
       class="portal-service-card"
       style="--card-img: url('{{ asset('img/icon/aset-bmn.png') }}');">
      <span class="portal-service-overlay"></span>
      <span class="portal-service-content">
        <span class="portal-service-title">Aset BMN</span>
        <span class="portal-service-sub">Poltekkes Kemenkes Banten</span>
        <span class="portal-service-cta">
          Masuk Layanan
          <i class="ti ti-arrow-right"></i>
        </span>
      </span>
    </a>

    {{-- 2. PEMINJAMAN BMN --}}
    <a href="{{ url('/public/peminjaman-internal') }}"
       class="portal-service-card"
       style="--card-img: url('{{ asset('img/icon/peminjaman-bmn.png') }}');">
      <span class="portal-service-overlay"></span>
      <span class="portal-service-content">
        <span class="portal-service-title">Peminjaman BMN</span>
        <span class="portal-service-sub">Poltekkes Kemenkes Banten</span>
        <span class="portal-service-cta">
          Masuk Layanan
          <i class="ti ti-arrow-right"></i>
        </span>
      </span>
    </a>

    {{-- 3. SEWA BMN --}}
    <a href="{{ url('/public/sewa') }}"
       class="portal-service-card"
       style="--card-img: url('{{ asset('img/icon/sewa-bmn.png') }}');">
      <span class="portal-service-overlay"></span>
      <span class="portal-service-content">
        <span class="portal-service-title">Sewa BMN</span>
        <span class="portal-service-sub">Poltekkes Kemenkes Banten</span>
        <span class="portal-service-cta">
          Masuk Layanan
          <i class="ti ti-arrow-right"></i>
        </span>
      </span>
    </a>

    {{-- 4. PEMELIHARAAN BMN --}}
    <a href="{{ url('/public/pemeliharaan') }}"
       class="portal-service-card"
       style="--card-img: url('{{ asset('img/icon/pemeliharaan-bmn.png') }}');">
      <span class="portal-service-overlay"></span>
      <span class="portal-service-content">
        <span class="portal-service-title">Pemeliharaan BMN</span>
        <span class="portal-service-sub">Poltekkes Kemenkes Banten</span>
        <span class="portal-service-cta">
          Masuk Layanan
          <i class="ti ti-arrow-right"></i>
        </span>
      </span>
    </a>

  </div>
</section>
@endsection

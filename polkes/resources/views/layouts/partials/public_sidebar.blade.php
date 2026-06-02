@php
  $isDashboard = request()->routeIs('public.landing') || request()->is('/');
  $isAset = request()->is('login');
  $isPeminjaman = request()->is('public/peminjaman-internal*');
  $isSewa = request()->is('public/sewa*');
  $isPemeliharaan = request()->is('public/pemeliharaan*');
@endphp

<aside class="public-sidebar" id="publicSidebar">
  <div class="public-brand">
    <img src="{{ asset('img/logo.png') }}" alt="Logo Poltekkes Kemenkes Banten">
    <div class="public-brand-title">
      SISTEM INFORMASI<br>
      BARANG MILIK NEGARA<br>
      (BMN)<br>
      <span class="public-brand-sub">Poltekkes Kemenkes Banten</span>
    </div>
  </div>

  <nav class="public-menu">
    <div class="public-menu-title">Menu Utama</div>

    <a href="{{ route('public.landing') }}" class="{{ $isDashboard ? 'active' : '' }}">
      <i class="ti ti-home"></i>
      <span>Dashboard</span>
    </a>

    <a href="{{ url('/login') }}" class="{{ $isAset ? 'active' : '' }}">
      <i class="ti ti-database"></i>
      <span>1. Aset</span>
    </a>

    <a href="{{ url('/public/peminjaman-internal') }}" class="{{ $isPeminjaman ? 'active' : '' }}">
      <i class="ti ti-clipboard-check"></i>
      <span>2. Peminjaman BMN</span>
    </a>

    <a href="{{ url('/public/sewa') }}" class="{{ $isSewa ? 'active' : '' }}">
      <i class="ti ti-receipt"></i>
      <span>3. Sewa BMN</span>
    </a>

    <a href="{{ url('/public/pemeliharaan') }}" class="{{ $isPemeliharaan ? 'active' : '' }}">
      <i class="ti ti-tools"></i>
      <span>4. Pemeliharaan BMN</span>
    </a>
  </nav>

  <div class="public-sidebar-footer">
    <strong>Informasi Instansi</strong><br>
    Politeknik Kesehatan Kemenkes Banten<br>
    
    Jl. Syech Nawawi Al Bantani No.12, Cipocok Jaya, Kota Serang
    0858-2146-6172
    direktorat@poltekkesbanten.ac.id
    @polkes_banten
    <div class="public-sidebar-copy">© {{ date('Y') }} Poltekkes Kemenkes Banten</div>
  </div>
</aside>

@php
  $routeName = request()->route()?->getName() ?? '';
  $currentQuick = (string) request()->query('quick', '');

  $isDashboard = $routeName === 'sewa-admin.dashboard';
  $isSewaIndex = $routeName === 'sewa-admin.sewa.index';
  $isSewaShow = $routeName === 'sewa-admin.sewa.show';
  $isSewaSection = str_starts_with($routeName, 'sewa-admin.sewa.');
  $isPelaporan = str_starts_with($routeName, 'sewa-admin.pelaporan');
  $isKomplain = str_starts_with($routeName, 'sewa-admin.komplain');

  // Sub-item Sewa aktif kalau saat ini berada di halaman sewa-admin.sewa.index DAN
  // parameter ?quick=<key> cocok. Untuk halaman show tidak ada quick filter,
  // jadi tidak menyalakan sub-item apapun (tapi parent "Sewa" tetap aktif).
  $quickItems = [
    'baru'      => ['label' => 'Pengajuan Baru',      'icon' => 'ti-mail-plus'],
    'menunggu'  => ['label' => 'Menunggu Verifikasi', 'icon' => 'ti-clock'],
    'terbayar'  => ['label' => 'Sudah Terbayar',      'icon' => 'ti-check'],
    'berjalan'  => ['label' => 'Sedang Berjalan',     'icon' => 'ti-progress'],
    'selesai'   => ['label' => 'Selesai',             'icon' => 'ti-circle-check'],
  ];
@endphp

<aside class="bmn-sidebar" id="bmnSidebar">
  <div class="bmn-sidebar-brand">
    <img src="{{ asset('img/logo.png') }}" alt="Logo Poltekkes">
    <div class="bmn-sidebar-brand-text">
      Admin Sewa BMN
      <small>Poltekkes Kemenkes Banten</small>
    </div>
  </div>

  <nav class="bmn-sidebar-nav">
    <div class="bmn-nav-item {{ $isDashboard ? 'active' : '' }}">
      <a href="{{ route('sewa-admin.dashboard') }}" class="bmn-nav-link {{ $isDashboard ? 'active' : '' }}">
        <i class="ti ti-home"></i>
        <span>Dashboard</span>
      </a>
    </div>

    <div class="bmn-nav-section">Admin Sewa BMN</div>

    <div class="bmn-nav-item has-sub {{ $isSewaSection ? 'open active' : '' }}">
      <a href="javascript:void(0)" class="bmn-nav-link bmn-sub-toggle">
        <i class="ti ti-receipt"></i>
        <span>Sewa</span>
        <i class="ti ti-chevron-right bmn-chevron"></i>
      </a>
      <ul class="bmn-nav-sub">
        <li>
          <a href="{{ route('sewa-admin.sewa.index') }}"
             class="bmn-nav-link {{ $isSewaIndex && $currentQuick === '' ? 'active' : '' }}">
            Semua Pengajuan
          </a>
        </li>
        @foreach($quickItems as $key => $meta)
          <li>
            <a href="{{ route('sewa-admin.sewa.index', ['quick' => $key]) }}"
               class="bmn-nav-link {{ $isSewaIndex && $currentQuick === $key ? 'active' : '' }}">
              {{ $meta['label'] }}
            </a>
          </li>
        @endforeach
      </ul>
    </div>

    <div class="bmn-nav-item {{ $isPelaporan ? 'active' : '' }}">
      <a href="{{ route('sewa-admin.pelaporan.index') }}" class="bmn-nav-link {{ $isPelaporan ? 'active' : '' }}">
        <i class="ti ti-report-money"></i>
        <span>Pelaporan Bulanan</span>
      </a>
    </div>

    <div class="bmn-nav-item {{ $isKomplain ? 'active' : '' }}">
      <a href="{{ route('sewa-admin.komplain.index') }}" class="bmn-nav-link {{ $isKomplain ? 'active' : '' }}">
        <i class="ti ti-message-report"></i>
        <span>Komplain Asrama</span>
        <span class="badge bg-warning text-dark ms-auto" style="font-size:.65rem;">Progress</span>
      </a>
    </div>

    <div class="bmn-nav-section">Akun</div>
    <div class="bmn-nav-item">
      <a href="#"
         class="bmn-nav-link text-danger"
         onclick="event.preventDefault(); document.getElementById('sewa-admin-logout-form').submit();">
        <i class="ti ti-logout"></i>
        <span>Logout</span>
      </a>
    </div>
  </nav>

  <div class="bmn-sidebar-footer">
    <strong>Informasi Instansi</strong>
    Poltekkes Kemenkes Banten<br>
    Jl. Syech Nawawi Al Bantani No.12, Cipocok Jaya, Kota Serang<br>
    Admin Koordinasi Sewa: Bu Retno<br>
    {{ \App\User::SEWA_ADMIN_RETNO_WA_DISPLAY }}
    <div class="mt-2 opacity-75">© {{ date('Y') }} Poltekkes Kemenkes Banten</div>
  </div>
</aside>

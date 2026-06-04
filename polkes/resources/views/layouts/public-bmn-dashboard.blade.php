<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('page_title', 'Portal BMN') — Poltekkes Kemenkes Banten</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
  <link rel="stylesheet" href="{{ asset('thema/assets/css/styles.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/public-bmn-dashboard.css') }}?v=20260603a">
  @stack('styles')
</head>
{{-- 
  body.public-portal-body:
    - sidebar default CLOSED di semua viewport
    - background dashboard fullscreen + overlay gelap rgba(7,23,51,0.65)
    - jika /img/background/dashboard-bmn.png belum ada → fallback gradient navy (lihat CSS)
--}}
<body class="public-dashboard-body public-portal-body"
      style="--portal-bg-image: url('{{ asset('img/background/dashboard-bmn.png') }}');">

  {{-- HAMBURGER (fixed top-left, satu-satunya pemicu sidebar) --}}
  <button type="button"
          class="portal-hamburger"
          id="publicSidebarToggle"
          aria-label="Buka menu navigasi"
          aria-controls="publicSidebar"
          aria-expanded="false">
    <i class="ti ti-menu-2"></i>
  </button>

  {{-- LOGIN ADMIN (pojok kanan atas, outline-light, kecil) --}}
  <a href="{{ url('/login') }}" class="portal-login-pill" title="Login Admin">
    <i class="ti ti-login"></i>
    <span>Login Admin</span>
  </a>

  {{-- SIDEBAR (tetap dipertahankan, hanya default closed + slide dari kiri) --}}
  @include('layouts.partials.public_sidebar')

  {{-- OVERLAY gelap di belakang sidebar (klik untuk menutup) --}}
  <div class="public-sidebar-overlay" id="publicSidebarOverlay"></div>

  {{-- MAIN: tanpa topbar, tanpa breadcrumb administratif --}}
  <main class="public-portal-main">
    @yield('content')
  </main>

  <script src="{{ asset('thema/assets/libs/jquery/dist/jquery.min.js') }}"></script>
  <script src="{{ asset('thema/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
  <script>
    (function () {
      var toggle  = document.getElementById('publicSidebarToggle');
      var sidebar = document.getElementById('publicSidebar');
      var overlay = document.getElementById('publicSidebarOverlay');
      if (!toggle || !sidebar) return;

      function openSidebar() {
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('show');
        document.body.classList.add('public-sidebar-open');
        toggle.setAttribute('aria-expanded', 'true');
      }
      function closeSidebar() {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        document.body.classList.remove('public-sidebar-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
      function toggleSidebar() {
        if (sidebar.classList.contains('open')) closeSidebar();
        else openSidebar();
      }

      toggle.addEventListener('click', toggleSidebar);
      if (overlay) overlay.addEventListener('click', closeSidebar);

      // ESC untuk menutup sidebar
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSidebar();
      });
    })();
  </script>
  @stack('scripts')
</body>
</html>

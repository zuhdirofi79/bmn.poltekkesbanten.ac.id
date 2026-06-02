<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('page_title', 'Admin Sewa BMN') — {{ config('app.name') }}</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
  <link rel="stylesheet" href="{{ asset('thema/assets/css/styles.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/bmn-pagination.css') }}?v=1">
  <link rel="stylesheet" href="{{ asset('css/bmn-dashboard.css') }}?v=1">
  @stack('styles')
</head>
<body class="bmn-admin-body">
  <div class="bmn-wrapper">
    @include('layouts.partials.sewa_admin_sidebar')
    <div class="bmn-main">
      @include('layouts.partials.sewa_admin_header')
      <main class="bmn-content">
        @if(session('success'))
          <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @yield('content')
      </main>
    </div>
  </div>

  <script src="{{ asset('thema/assets/libs/jquery/dist/jquery.min.js') }}"></script>
  <script src="{{ asset('thema/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
  <script>
    document.querySelectorAll('.bmn-sub-toggle').forEach(function (el) {
      el.addEventListener('click', function () {
        el.closest('.bmn-nav-item').classList.toggle('open');
      });
    });
    var toggle = document.getElementById('bmnSidebarToggle');
    var sidebar = document.getElementById('bmnSidebar');
    if (toggle && sidebar) {
      toggle.addEventListener('click', function () {
        sidebar.classList.toggle('open');
      });
    }
  </script>
  @stack('scripts')
</body>
</html>

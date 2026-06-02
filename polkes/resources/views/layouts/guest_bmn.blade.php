<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'BMN') — {{ config('app.name') }}</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
  <link rel="stylesheet" href="{{ asset('thema/assets/css/styles.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/bmn-pagination.css') }}?v=1">
  <link rel="stylesheet" href="{{ asset('css/bmn-dashboard.css') }}?v=1">
</head>
<body class="bmn-public-body">
  <header class="bmn-public-header">
    <div class="bmn-public-header-inner">
      <img src="{{ asset('img/logo.png') }}" alt="Logo">
      <div>
        <h1>Sistem Informasi Barang Milik Negara (BMN)<br>Poltekkes Kemenkes Banten</h1>
        <p>@yield('header_subtitle', 'Layanan Publik BMN')</p>
      </div>
    </div>
  </header>
  <main class="bmn-public-main">
    @yield('content')
  </main>
  <footer class="bmn-public-footer">
    <a href="{{ route('public.landing') }}" class="text-decoration-none me-2">Beranda</a>
    · <a href="{{ url('login') }}" class="text-decoration-none">Login Admin</a>
  </footer>
  <script src="{{ asset('thema/assets/libs/jquery/dist/jquery.min.js') }}"></script>
  <script src="{{ asset('thema/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
  @stack('scripts')
</body>
</html>

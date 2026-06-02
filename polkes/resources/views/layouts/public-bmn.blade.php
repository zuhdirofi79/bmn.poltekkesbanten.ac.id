<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Portal BMN') — Poltekkes Kemenkes Banten</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
  {{-- Bootstrap + tema existing project --}}
  <link rel="stylesheet" href="{{ asset('thema/assets/css/styles.min.css') }}" />
  {{-- CSS khusus portal publik (landing + layanan) --}}
  <link rel="stylesheet" href="{{ asset('css/public-bmn.css') }}?v=2">
  @stack('styles')
</head>
<body class="public-bmn-body">
  @yield('content')
  @stack('scripts')
</body>
</html>

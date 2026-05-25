<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'BMN') — {{ config('app.name') }}</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
  <link rel="stylesheet" href="{{ asset('thema/assets/css/styles.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/bmn-pagination.css') }}?v=1">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical">
    <div class="position-relative overflow-hidden radial-gradient min-vh-100 py-4">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-10 col-xl-9">
            <div class="text-center mb-3">
              <a href="{{ url('login') }}">
                <img src="{{ asset('img/logo.png') }}" width="64" alt="Logo">
              </a>
              <p class="mb-0 mt-2 text-muted">{{ config('app.name') }}</p>
            </div>
            <div class="card mb-0">
              <div class="card-body p-4">
                @yield('content')
              </div>
            </div>
            <div class="text-center mt-3">
              <a href="{{ url('login') }}" class="text-primary">Sudah punya akun? Login</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="{{ asset('thema/assets/libs/jquery/dist/jquery.min.js') }}"></script>
  <script src="{{ asset('thema/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
  @stack('scripts')
</body>
</html>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('page_title', 'Admin Persediaan') — SIM-BMN Persediaan</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
  <link rel="stylesheet" href="{{ asset('thema/assets/css/styles.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=3">
  @stack('styles')
</head>
<body class="pa-body">
  <div class="pa-wrap">
    @include('persediaan_admin.partials.sidebar')

    <div class="pa-main">
      <div class="pa-topbar">
        <div>
          <div class="text-muted" style="font-size:.85rem;">
            Selamat Datang, <strong>{{ \App\Support\Persediaan\PersediaanAdminSession::displayName(request()) }}</strong>
          </div>
          <h1>@yield('page_title', 'Dashboard Persediaan')</h1>
        </div>
        <div class="pa-user">
          <i class="ti ti-user-circle" style="font-size:26px;"></i>
          <form action="{{ route('persediaan.admin.logout') }}" method="POST" class="d-inline ms-2">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm" title="Logout">
              <i class="ti ti-logout"></i>
            </button>
          </form>
        </div>
      </div>

      <main class="pa-content">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        @endif
        @yield('content')
      </main>
    </div>
  </div>

  <script src="{{ asset('thema/assets/libs/jquery/dist/jquery.min.js') }}"></script>
  <script src="{{ asset('thema/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
  @stack('scripts')
</body>
</html>

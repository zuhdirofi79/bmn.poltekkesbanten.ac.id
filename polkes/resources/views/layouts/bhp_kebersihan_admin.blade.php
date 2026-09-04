<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('page_title', 'Admin BHP Kebersihan') — SIM-BMN</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
  <link rel="stylesheet" href="{{ asset('thema/assets/css/styles.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=3">
  <link rel="stylesheet" href="{{ asset('css/bhp-kebersihan.css') }}?v=8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @stack('styles')
</head>
<body class="pa-body">
  <div class="pa-wrap">
    @include('bhp_kebersihan_admin.partials.sidebar')

    <div class="pa-main">
      <div class="pa-topbar">
        <div>
          <div class="text-muted" style="font-size:.85rem;">
            Selamat Datang, <strong>{{ \App\Support\BhpKebersihan\BhpAdminSession::displayName(request()) }}</strong>
          </div>
          <h1>@yield('page_title', 'Dashboard BHP Kebersihan')</h1>
        </div>
        <div class="pa-user">
          <i class="ti ti-user-circle" style="font-size:26px;"></i>
          <form action="{{ route('bhp.admin.logout') }}" method="POST" class="d-inline ms-2">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm" title="Logout">
              <i class="ti ti-logout"></i>
            </button>
          </form>
        </div>
      </div>

      <main class="pa-content">
        @if(session('success'))
          <div class="alert alert-success">
            <div>{{ session('success') }}</div>
            @if(session('stok_koreksi'))
              <div class="small mt-2 mb-0">
                Stok sebelumnya : {{ session('stok_koreksi')['sebelum'] }}<br>
                Perubahan : {{ session('stok_koreksi')['delta'] }}<br>
                Stok sekarang : {{ session('stok_koreksi')['sesudah'] }}
              </div>
            @endif
          </div>
        @endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if(session('bulk_delete_summary'))
          @php $bulk = session('bulk_delete_summary'); @endphp
          <div class="alert {{ ($bulk['deleted'] ?? 0) > 0 ? 'alert-success' : 'alert-warning' }}">
            <div class="fw-semibold">Hasil Penghapusan</div>
            <div class="small mt-2 mb-0">
              Berhasil dihapus : {{ $bulk['deleted'] ?? 0 }}<br>
              Gagal : {{ $bulk['failed'] ?? 0 }}
            </div>
            @if(!empty($bulk['reasons']))
              <div class="small mt-2 mb-0">
                Alasan:
                <ul class="mb-0 ps-3">
                  @foreach($bulk['reasons'] as $reason)
                    <li>{{ $reason }}</li>
                  @endforeach
                </ul>
              </div>
            @endif
          </div>
        @endif
        @if($errors->any() && !session('open_edit_id') && !session('open_koreksi_id'))
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

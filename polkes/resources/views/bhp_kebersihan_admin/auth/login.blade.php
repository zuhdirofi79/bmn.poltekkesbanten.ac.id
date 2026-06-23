<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin BHP Kebersihan — SIM-BMN</title>
  <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
  <link rel="stylesheet" href="{{ asset('thema/assets/css/styles.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=1">
  <style>
    body { background: linear-gradient(190deg, #16356b, #0f2550); min-height: 100vh;
           display: flex; align-items: center; justify-content: center; margin: 0; }
    .pa-login { width: 100%; max-width: 400px; background: #fff; border-radius: 18px;
                padding: 30px; box-shadow: 0 24px 60px rgba(0,0,0,.3); }
    .pa-login .brand { text-align: center; margin-bottom: 18px; }
    .pa-login .brand .logo { width: 56px; height: 56px; border-radius: 14px; margin: 0 auto 8px;
                background: #16356b; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; }
  </style>
</head>
<body>
  <div class="pa-login">
    <div class="brand">
      <div class="logo"><i class="ti ti-spray"></i></div>
      <h4 class="fw-bold mb-0">SIM-BMN BHP Kebersihan</h4>
      <small class="text-muted">Login Admin BHP Kebersihan</small>
    </div>

    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('bhp.admin.login.attempt') }}">
      @csrf
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" value="{{ old('username') }}"
               class="form-control @error('username') is-invalid @enderror" required autofocus>
        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password"
               class="form-control @error('password') is-invalid @enderror" required>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <button type="submit" class="btn btn-prs-blue w-100"><i class="ti ti-login"></i> Masuk</button>
    </form>

    <div class="text-center mt-3">
      <a href="{{ route('public.bhp-kebersihan.index') }}" class="text-muted"><i class="ti ti-arrow-left"></i> Kembali ke BHP Kebersihan</a>
    </div>
  </div>
</body>
</html>

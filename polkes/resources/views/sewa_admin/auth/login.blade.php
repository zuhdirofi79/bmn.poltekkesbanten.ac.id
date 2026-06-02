<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin Sewa BMN — Poltekkes Banten</title>
    <link rel="shortcut icon" type="image/png" href="{{ asset('img/fav.png') }}" />
    <link rel="stylesheet" href="{{ asset('css/bmn-login.css') }}?v=20260602">
</head>
<body class="bmn-login-body">

<main class="bmn-login-page">
    <section class="bmn-login-card">

        <div class="bmn-login-logo-wrap">
            <img src="{{ asset('img/logo.png') }}"
                 alt="Poltekkes Banten"
                 class="bmn-login-logo">
        </div>

        <p class="bmn-login-subtitle">ADMIN SEWA BMN POLTEKKES BANTEN</p>
        <h1 class="bmn-login-title">LOGIN</h1>

        <div class="bmn-login-divider"></div>

        @if(session('error'))
            <div class="bmn-error" style="margin-bottom:1rem;">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="bmn-error" style="background:#d1fae5;color:#065f46;margin-bottom:1rem;">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('sewa-admin.login.attempt') }}">
            @csrf

            <div class="bmn-form-group">
                <label for="username">Username Admin Sewa</label>
                <input id="username"
                       type="text"
                       name="username"
                       value="{{ old('username') }}"
                       required
                       autofocus>
                @error('username')
                    <div class="bmn-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="bmn-form-group">
                <label for="password">Password</label>
                <input id="password"
                       type="password"
                       name="password"
                       required>
                @error('password')
                    <div class="bmn-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="bmn-login-options">
                <label class="bmn-remember">
                    <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <span>Remember me</span>
                </label>
            </div>

            <button type="submit" class="bmn-login-button">
                Login Admin Sewa
            </button>
        </form>

        <a href="{{ url('/') }}" class="bmn-back-public">
            ← Kembali ke Beranda Publik
        </a>

    </section>
</main>

</body>
</html>

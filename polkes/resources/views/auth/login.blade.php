<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ENV('APP_NAME')}}</title>
  <link rel="shortcut icon" type="image/png" href="{{asset('img')}}/fav.png" />
  <link rel="stylesheet" href="{{asset('thema')}}/assets/css/styles.min.css" />
</head>

<body>
  <!--  Body Wrapper -->
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
    <div
      class="position-relative overflow-hidden radial-gradient min-vh-100 d-flex align-items-center justify-content-center">
      <div class="d-flex align-items-center justify-content-center w-100">
        <div class="row justify-content-center w-100">
          <div class="col-md-8 col-lg-4 col-xxl-4">
            <div class="card mb-3 border-primary">
              <div class="card-body py-3">
                <h5 class="fw-semibold mb-2">Mau Pinjam atau Kembalikan Aset BMN?</h5>
                <p class="small text-muted mb-3">
                  Mau Pinjam/Kembalikan Aset BMN tanpa login? Ajukan langsung — verifikasi Email &amp; WhatsApp wajib.
                </p>
                <div class="d-grid gap-2">
                  <a href="{{ route('bmn.peminjaman-aset.create') }}" class="btn btn-outline-primary btn-sm">
                    Ajukan Peminjaman Aset BMN
                  </a>
                  <a href="{{ route('bmn.pengembalian-aset.create-public') }}" class="btn btn-outline-warning btn-sm">
                    Ajukan Pengembalian Aset BMN
                  </a>
                </div>
              </div>
            </div>
            <div class="card mb-0">
              <div class="card-body">
                <a href="./index.html" class="text-nowrap logo-img text-center d-block py-3 w-100">
                  <img src="{{asset('img')}}/logo.png" width="80" alt="">
                </a>
                <p class="text-center">{{ENV('APP_NAME')}}</p>
                <h4 class="text-center">LOGIN</h4>
                <hr>
                <form method="POST" action="{{ route('login') }}">
                  @csrf
                  <div class="mb-3">
                    <label for="username" class="form-label">NIM/NID/NIP/NUP</label>
                    <input type="username" name="username" class="form-control @error('username') is-invalid @enderror" id="username" value="{{ old('username') }}" required autofocus>
                    @error('username')
                    <div id="username" class="form-text text-danger"><strong>{{ $message }}</strong></div>
                    @enderror
                  </div>
                  <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required >
                    @error('password')
                    <div id="password" class="form-text text-danger"><strong>{{ $message }}</strong></div>
                    @enderror
                  </div>
                  <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check">
                      <input class="form-check-input primary" type="checkbox" value="" id="flexCheckChecked" checked>
                      <label class="form-check-label text-dark" for="flexCheckChecked">
                        Remeber me
                      </label>
                    </div>
                    <a class="text-primary fw-bold" href="{{url('password/reset')}}">Lupa Password ?</a>
                  </div>
                  <button type="submit" class="btn btn-primary w-100 py-8 fs-4 mb-4 rounded-2">Login</button>
                  <!-- <a href="./index.html" class="btn btn-primary w-100 py-8 fs-4 mb-4 rounded-2">Sign In</a> -->
                  <!--<div class="d-flex align-items-center justify-content-center">-->
                  <!--  <p class="fs-4 mb-0 fw-bold">Belum Memiliki akun?</p>-->
                  <!--  <a class="text-primary fw-bold ms-2" href="{{url('register')}}">Registrasi</a>-->
                  <!--</div>-->
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="{{asset('thema')}}/assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="{{asset('thema')}}/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
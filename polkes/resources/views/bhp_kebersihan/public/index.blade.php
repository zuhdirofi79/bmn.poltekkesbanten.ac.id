@extends('layouts.public-bmn-dashboard')

@section('page_title', 'BHP Kebersihan')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=2">
  <link rel="stylesheet" href="{{ asset('css/bhp-kebersihan.css') }}?v=2">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="prs-banner" style="background: linear-gradient(120deg, #2f6fdb, #16356b);">
    <i class="ti ti-spray"></i>
    <div>
      <h1>BHP Kebersihan</h1>
      <p>Pengelolaan barang habis pakai kebersihan — Poltekkes Kemenkes Banten</p>
    </div>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="prs-public-card">
    <div class="bhp-public-pickup">
      <h2 class="bhp-public-pickup-title">Pengambilan BHP Kebersihan</h2>
      <p class="bhp-public-pickup-subtitle">
        Scan barcode barang BHP atau masukkan kode barang untuk mengajukan pengambilan.
      </p>

      <button type="button" class="btn btn-prs-blue btn-lg w-100 bhp-scan-btn" id="btnStartScan">
        <i class="ti ti-scan"></i> Scan Barcode Barang
      </button>

      <div id="bhpScannerWrap" class="bhp-scanner-wrap d-none" aria-hidden="true">
        <div id="bhpQrReader" class="bhp-qr-reader"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnStopScan">
          <i class="ti ti-x"></i> Tutup Kamera
        </button>
        <p id="bhpScannerError" class="text-danger small mt-2 mb-0 d-none"></p>
      </div>

      <form method="POST" action="{{ route('public.bhp-kebersihan.cari') }}" class="bhp-manual-search mt-3">
        @csrf
        <label class="form-label small text-muted mb-1" for="kodeInput">Input manual</label>
        <div class="input-group input-group-lg">
          <input
            type="text"
            name="kode"
            id="kodeInput"
            class="form-control"
            placeholder="Masukkan kode barang / scan token barcode"
            value="{{ old('kode') }}"
            autocomplete="off"
            required
          >
          <button type="submit" class="btn btn-outline-primary">
            <i class="ti ti-search"></i> Cari Barang
          </button>
        </div>
      </form>

      <p class="bhp-scan-help text-muted mt-3 mb-0">
        Gunakan kamera smartphone untuk scan barcode yang ditempel pada barang BHP.
      </p>
    </div>

    <div class="prs-admin-area-divider">Area Admin BHP Kebersihan</div>

    <div class="prs-admin-card">
      <div class="prs-admin-icon" aria-hidden="true">
        <i class="ti ti-shield-lock"></i>
      </div>
      <div class="flex-grow-1">
        <h3>Admin BHP Kebersihan</h3>
        <p>Khusus petugas verifikasi, pengambilan barang, master data, stok akhir, dan stock opname.</p>
        <a href="{{ route('bhp.admin.login.show') }}"
           class="prs-admin-btn"
           aria-label="Masuk ke halaman Login Admin BHP Kebersihan">
          <i class="ti ti-login" aria-hidden="true"></i>
          <span>Login Admin BHP Kebersihan</span>
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
  var barangBaseUrl = '{{ url('/public/bhp-kebersihan/barang') }}';
  var btnStart = document.getElementById('btnStartScan');
  var btnStop = document.getElementById('btnStopScan');
  var scannerWrap = document.getElementById('bhpScannerWrap');
  var scannerError = document.getElementById('bhpScannerError');
  var qrReaderId = 'bhpQrReader';
  var scanner = null;
  var scanning = false;

  function extractToken(text) {
    var trimmed = (text || '').trim();
    if (!trimmed) return null;

    var pathMatch = trimmed.match(/bhp-kebersihan\/barang\/([^/?#]+)/i);
    if (pathMatch) return pathMatch[1];

    return trimmed;
  }

  function handleScanResult(decodedText) {
    var trimmed = (decodedText || '').trim();
    if (!trimmed) return;

    if (/^https?:\/\//i.test(trimmed)) {
      window.location.href = trimmed;
      return;
    }

    var token = extractToken(trimmed);
    if (token) {
      window.location.href = barangBaseUrl + '/' + encodeURIComponent(token);
    }
  }

  function showScannerError(message) {
    if (!scannerError) return;
    scannerError.textContent = message;
    scannerError.classList.remove('d-none');
  }

  function hideScannerError() {
    if (!scannerError) return;
    scannerError.textContent = '';
    scannerError.classList.add('d-none');
  }

  function stopScanner() {
    if (!scanner) return;

    scanner.stop().then(function () {
      scanner.clear();
      scanner = null;
      scanning = false;
      if (scannerWrap) {
        scannerWrap.classList.add('d-none');
        scannerWrap.setAttribute('aria-hidden', 'true');
      }
    }).catch(function () {
      scanner = null;
      scanning = false;
      if (scannerWrap) {
        scannerWrap.classList.add('d-none');
        scannerWrap.setAttribute('aria-hidden', 'true');
      }
    });
  }

  function startScanner() {
    if (typeof Html5Qrcode === 'undefined') {
      showScannerError('Scanner tidak dapat dimuat. Gunakan input manual di bawah.');
      if (scannerWrap) {
        scannerWrap.classList.remove('d-none');
        scannerWrap.setAttribute('aria-hidden', 'false');
      }
      return;
    }

    hideScannerError();

    if (scannerWrap) {
      scannerWrap.classList.remove('d-none');
      scannerWrap.setAttribute('aria-hidden', 'false');
    }

    if (scanning) return;

    scanner = new Html5Qrcode(qrReaderId);
    scanning = true;

    scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 250, height: 250 } },
      function (decodedText) {
        stopScanner();
        handleScanResult(decodedText);
      },
      function () {}
    ).catch(function (err) {
      scanning = false;
      scanner = null;
      showScannerError('Kamera tidak dapat diakses. Gunakan input manual di bawah.');
      console.warn('BHP scanner error:', err);
    });
  }

  if (btnStart) {
    btnStart.addEventListener('click', startScanner);
  }

  if (btnStop) {
    btnStop.addEventListener('click', stopScanner);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden && scanning) {
      stopScanner();
    }
  });
})();
</script>
@endpush

@extends('layouts.persediaan_qr')

@section('page_title', 'Identitas Pemohon')

@section('content')
<div class="prs-qr-id">
  <p class="text-muted mb-1">Halo,</p>
  <h1 class="h4 mb-3">{{ $pemohon->nama }}</h1>
  <div class="prs-public-card">
    <div class="prs-id-row"><span>Nama</span><strong>{{ $pemohon->nama }}</strong></div>
    <div class="prs-id-row"><span>Jabatan</span><strong>{{ $pemohon->jabatan ?: '-' }}</strong></div>
    <div class="prs-id-row"><span>Unit Kerja</span><strong>{{ $pemohon->unit_kerja }}</strong></div>
    <div class="prs-id-row"><span>Tanggal</span><strong>{{ $tanggal->timezone(config('app.timezone'))->format('d/m/Y') }}</strong></div>
    <div class="prs-id-row"><span>Waktu</span><strong>{{ $tanggal->timezone(config('app.timezone'))->format('H:i') }}</strong></div>
  </div>
  <p class="small text-muted mt-3">Gunakan kamera HP untuk scan QR yang menempel pada rak, atau buka scanner di bawah.</p>
  <a href="#scan" class="btn btn-prs-blue w-100 prs-touch" id="scanRakBtn">Scan QR Rak</a>
  <div id="scan" class="prs-public-card mt-3">
    <label class="form-label">Tempel URL QR Rak (cadangan)</label>
    <input type="url" id="rakUrlInput" class="form-control" placeholder="https://.../public/persediaan/rak/...">
    <button type="button" class="btn btn-outline-primary w-100 mt-2 prs-touch" id="openRakUrl">Buka Rak</button>
    <video id="qrVideo" class="w-100 mt-3" playsinline muted hidden></video>
    <p id="scanHint" class="small text-muted mt-2 mb-0">Jika browser mendukung, kamera akan mencoba membaca QR rak.</p>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var input = document.getElementById('rakUrlInput');
  document.getElementById('openRakUrl').addEventListener('click', function () {
    var url = (input.value || '').trim();
    if (!url) return;
    window.location.href = url;
  });

  var video = document.getElementById('qrVideo');
  if (!('BarcodeDetector' in window) || !navigator.mediaDevices) {
    return;
  }
  var detector = new BarcodeDetector({ formats: ['qr_code'] });
  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
    video.srcObject = stream;
    video.hidden = false;
    video.play();
    function tick() {
      detector.detect(video).then(function (codes) {
        if (codes && codes[0] && codes[0].rawValue) {
          stream.getTracks().forEach(function (t) { t.stop(); });
          window.location.href = codes[0].rawValue;
          return;
        }
        requestAnimationFrame(tick);
      }).catch(function () { requestAnimationFrame(tick); });
    }
    requestAnimationFrame(tick);
  }).catch(function () {});
})();
</script>
@endpush

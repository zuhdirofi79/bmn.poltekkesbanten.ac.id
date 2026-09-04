<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print Label QR — {{ $barang->nama_barang }}</title>
  @include('bhp_kebersihan_admin.master-barang._barcode_print_styles')
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">Print</button>
    <p class="print-hint">Ukuran label 50 mm × 20 mm pada kertas A4. Pilih skala cetak 100% (bukan Fit to page).</p>
  </div>
  <div class="sheet">
    <div class="labels">
      <div class="barcode-label">
        <img src="{{ $imageUrl }}" alt="{{ $barang->nama_barang }} {{ $barang->kode_barang }}">
      </div>
    </div>
  </div>
  @if(!empty($autoPrint))
  <script>window.addEventListener('load', function () { window.print(); });</script>
  @endif
</body>
</html>

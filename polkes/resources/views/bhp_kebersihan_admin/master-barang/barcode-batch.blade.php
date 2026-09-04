<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print Label QR Batch — BHP Kebersihan</title>
  @include('bhp_kebersihan_admin.master-barang._barcode_print_styles')
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">Print Semua</button>
    <p class="print-hint">Setiap label 50 mm × 20 mm pada kertas A4. Pilih skala cetak 100% (bukan Fit to page).</p>
  </div>
  <div class="sheet">
    <div class="labels">
      @foreach($items as $item)
        <div class="barcode-label">
          <img src="{{ $item['imageUrl'] }}" alt="{{ $item['barang']->nama_barang }} {{ $item['barang']->kode_barang }}">
        </div>
      @endforeach
    </div>
  </div>
  <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>

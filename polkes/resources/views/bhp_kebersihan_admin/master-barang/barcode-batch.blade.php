<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print Barcode Batch — BHP Kebersihan</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; }
    .grid { display: flex; flex-wrap: wrap; gap: 12px; padding: 16px; justify-content: center; }
    .label { border: 1px dashed #ccc; width: 200px; text-align: center; padding: 12px; font-size: 12px; page-break-inside: avoid; }
    .label img { width: 140px; height: 140px; object-fit: contain; }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>
  <p class="no-print" style="text-align:center;padding:12px;"><button onclick="window.print()">Print Semua</button></p>
  <div class="grid">
    @foreach($items as $item)
      <div class="label">
        <strong>{{ $item['barang']->kode_barang }}</strong><br>
        {{ $item['barang']->nama_barang }}<br>
        <img src="{{ $item['imageUrl'] }}" alt="Barcode"><br>
        <small style="word-break:break-all;">{{ $item['publicUrl'] }}</small>
      </div>
    @endforeach
  </div>
  <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>

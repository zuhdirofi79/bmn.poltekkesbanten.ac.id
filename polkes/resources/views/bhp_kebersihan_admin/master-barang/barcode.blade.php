<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print Label QR — {{ $barang->nama_barang }}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 24px; background: #fff; }
    .sheet { width: 100%; max-width: 920px; margin: 0 auto; }
    .sheet img { width: 100%; height: auto; display: block; }
    @media print {
      .no-print { display: none !important; }
      body { padding: 0; }
      .sheet { max-width: none; }
    }
  </style>
</head>
<body>
  <p class="no-print" style="text-align:center;">
    <button onclick="window.print()">Print</button>
  </p>
  <div class="sheet">
    <img src="{{ $imageUrl }}" alt="{{ $barang->nama_barang }} {{ $barang->kode_barang }}">
  </div>
  @if(!empty($autoPrint))
  <script>window.addEventListener('load', function () { window.print(); });</script>
  @endif
</body>
</html>

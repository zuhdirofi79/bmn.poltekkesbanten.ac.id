<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print Label QR Batch — BHP Kebersihan</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; background: #fff; }
    .wrap { padding: 16px; }
    .label {
      width: 100%;
      max-width: 920px;
      margin: 0 auto 18px;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .label img { width: 100%; height: auto; display: block; }
    @media print {
      .no-print { display: none !important; }
      .wrap { padding: 0; }
      .label { margin-bottom: 12mm; }
    }
  </style>
</head>
<body>
  <p class="no-print" style="text-align:center;padding:12px;">
    <button onclick="window.print()">Print Semua</button>
  </p>
  <div class="wrap">
    @foreach($items as $item)
      <div class="label">
        <img src="{{ $item['imageUrl'] }}" alt="{{ $item['barang']->nama_barang }} {{ $item['barang']->kode_barang }}">
      </div>
    @endforeach
  </div>
  <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>

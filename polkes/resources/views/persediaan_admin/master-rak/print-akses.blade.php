<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print QR Pintu</title>
  <style>
    @page { size: A4 portrait; margin: 15mm; }
    body { font-family: Arial, Helvetica, sans-serif; text-align: center; }
    .no-print { padding: 10px; }
    img { width: 90mm; height: 90mm; }
    h1 { font-size: 22px; }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">Print</button>
  </div>
  <h1>{{ $akses->nama }}</h1>
  <p>Direktorat — Scan untuk membuka aplikasi Persediaan</p>
  <img src="{{ $imageUrl }}" alt="QR Pintu">
</body>
</html>

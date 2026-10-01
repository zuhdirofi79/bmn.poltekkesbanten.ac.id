<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print QR Rak</title>
  <style>
    @page { size: A4 portrait; margin: 10mm; }
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; }
    .no-print { text-align: center; padding: 10px; }
    .sheet { display: flex; flex-wrap: wrap; gap: 8mm; padding: 8mm; }
    .qr-card {
      width: 70mm; height: 80mm; border: 0.3mm solid #ccc;
      page-break-inside: avoid; text-align: center; padding: 4mm;
    }
    .qr-card img { width: 52mm; height: 52mm; object-fit: contain; }
    .qr-card .kode { font-size: 16px; font-weight: 700; margin-top: 2mm; }
    .qr-card .nama { font-size: 12px; }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">Print</button>
    <p>Skala cetak 100% (bukan Fit to page).</p>
  </div>
  <div class="sheet">
    @foreach($items as $item)
      <div class="qr-card">
        <img src="{{ $item['imageUrl'] }}" alt="{{ $item['rak']->kode_rak }}">
        <div class="kode">{{ $item['rak']->kode_rak }}</div>
        <div class="nama">{{ $item['rak']->nama_rak }}</div>
      </div>
    @endforeach
  </div>
</body>
</html>

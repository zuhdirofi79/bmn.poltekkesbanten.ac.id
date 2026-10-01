<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print Barcode Code 128</title>
  <style>
    @page { size: A4 portrait; margin: 8mm; }
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; font-family: Arial, Helvetica, sans-serif; }
    .no-print { text-align: center; padding: 10px 12px; }
    .sheet { width: 210mm; max-width: 100%; margin: 0 auto; padding: 8mm; }
    .labels { display: flex; flex-wrap: wrap; align-content: flex-start; gap: 2.5mm; }
    .barcode-label {
      width: 50mm; height: 20mm; min-width: 50mm; min-height: 20mm;
      max-width: 50mm; max-height: 20mm; flex: 0 0 50mm; margin: 0; padding: 0.4mm;
      overflow: hidden; background: #fff; border: 0.15mm solid #c8c8c8; border-radius: 1mm;
      page-break-inside: avoid; break-inside: avoid;
    }
    .barcode-label img {
      display: block; width: 100%; height: 100%; object-fit: contain; object-position: left center;
    }
    @media print {
      html, body { width: 210mm; background: #fff; }
      .no-print { display: none !important; }
      .sheet { width: auto; padding: 0; margin: 0; }
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button type="button" onclick="window.print()">Print Semua</button>
    <p>Setiap label 50 mm × 20 mm. Skala cetak 100% (bukan Fit to page).</p>
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
</body>
</html>

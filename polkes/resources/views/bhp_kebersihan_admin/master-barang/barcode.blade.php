<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Print Barcode — {{ $barang->nama_barang }}</title>
  <style>
    body { font-family: Arial, sans-serif; text-align: center; padding: 24px; margin: 0; }
    .label { display: inline-block; padding: 24px; }
    .label strong { font-size: 1.25rem; display: block; margin-bottom: 4px; }
    .label .nama { font-size: 1rem; margin-bottom: 16px; }
    .label img { width: 280px; height: 280px; object-fit: contain; }
    .label small { display: block; margin-top: 12px; color: #666; word-break: break-all; max-width: 320px; }
    @media print {
      .no-print { display: none !important; }
      body { padding: 0; }
    }
  </style>
</head>
<body>
  <div class="label">
    <strong>{{ $barang->kode_barang }}</strong>
    <div class="nama">{{ $barang->nama_barang }}</div>
    <img src="{{ $imageUrl }}" alt="Barcode {{ $barang->kode_barang }}">
    <small>{{ $publicUrl }}</small>
  </div>
  <p class="no-print"><button onclick="window.print()">Print</button></p>
  @if(!empty($autoPrint))
  <script>window.addEventListener('load', function () { window.print(); });</script>
  @endif
</body>
</html>

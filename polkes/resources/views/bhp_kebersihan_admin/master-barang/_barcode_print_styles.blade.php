{{-- Ukuran fisik label print: 50mm × 20mm. Jangan pakai transform:scale. --}}
<style>
  @page {
    size: A4 portrait;
    margin: 8mm;
  }
  *, *::before, *::after { box-sizing: border-box; }
  html, body {
    margin: 0;
    padding: 0;
    background: #fff;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
  }
  .no-print { text-align: center; padding: 10px 12px; }
  .print-hint {
    margin: 6px 0 0;
    color: #555;
    font-size: 12px;
  }
  .sheet {
    width: 210mm;
    max-width: 100%;
    margin: 0 auto;
    padding: 8mm;
    background: #fff;
  }
  .labels {
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
    align-items: flex-start;
    gap: 2.5mm;
  }
  .barcode-label {
    width: 50mm;
    height: 20mm;
    min-width: 50mm;
    min-height: 20mm;
    max-width: 50mm;
    max-height: 20mm;
    flex: 0 0 50mm;
    margin: 0;
    padding: 0.4mm;
    overflow: hidden;
    background: #fff;
    border: 0.15mm solid #c8c8c8;
    border-radius: 1mm;
    page-break-inside: avoid;
    break-inside: avoid;
    -webkit-column-break-inside: avoid;
  }
  .barcode-label img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: left center;
    image-rendering: auto;
  }
  @media print {
    html, body { width: 210mm; background: #fff; }
    .no-print { display: none !important; }
    .sheet { width: auto; padding: 0; margin: 0; }
    .barcode-label {
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
  }
</style>

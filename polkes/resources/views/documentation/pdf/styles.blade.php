<style>
  * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
  body {
    color: #1a2b3c;
    font-size: 11px;
    line-height: 1.55;
    margin: 0;
    padding: 0;
  }
  .page-break { page-break-after: always; }
  .avoid-break { page-break-inside: avoid; }

  h1 { font-size: 18px; color: #16315c; margin: 0 0 10px; }
  h2 { font-size: 14px; color: #16315c; margin: 18px 0 8px; border-bottom: 1.5px solid #2e8b57; padding-bottom: 4px; }
  h3 { font-size: 12px; color: #1e4a7a; margin: 14px 0 6px; }
  h4 { font-size: 11px; color: #245a8c; margin: 10px 0 4px; }
  p { margin: 0 0 8px; text-align: justify; }
  ul, ol { margin: 0 0 10px 16px; padding: 0; }
  li { margin-bottom: 3px; }

  .muted { color: #6b7a90; }
  .badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 9px;
    background: #e8f0f8;
    color: #16315c;
  }
  .badge-green { background: #e6f4ea; color: #1e6b3a; }
  .badge-yellow { background: #fff6e0; color: #8a6d1d; }
  .badge-gray { background: #eef1f4; color: #556070; }

  table.doc {
    width: 100%;
    border-collapse: collapse;
    margin: 8px 0 14px;
    font-size: 10px;
  }
  table.doc th {
    background: #16315c;
    color: #fff;
    text-align: left;
    padding: 6px 7px;
  }
  table.doc td {
    border: 1px solid #c9d4e3;
    padding: 5px 7px;
    vertical-align: top;
  }
  table.doc tr:nth-child(even) td { background: #f5f8fc; }

  .flow-box {
    border: 1px solid #c9d4e3;
    background: #f7fafc;
    padding: 10px 14px;
    margin: 10px 0 14px;
    text-align: center;
    font-size: 10px;
  }
  .flow-box .arrow { color: #2e8b57; font-weight: bold; margin: 2px 0; }

  .shot {
    margin: 8px 0 14px;
    text-align: center;
    page-break-inside: avoid;
  }
  .shot img {
    max-width: 100%;
    max-height: 280px;
    border: 1px solid #c9d4e3;
  }
  .shot .cap {
    font-size: 9px;
    color: #6b7a90;
    margin-top: 4px;
  }

  .feature {
    border-left: 3px solid #2e8b57;
    padding: 6px 0 6px 10px;
    margin: 0 0 10px;
    page-break-inside: avoid;
  }
  .feature .meta { font-size: 10px; color: #445566; }

  .cover {
    text-align: center;
    padding-top: 60px;
  }
  .cover .logo { width: 110px; height: auto; margin-bottom: 24px; }
  .cover .instansi {
    font-size: 12px;
    color: #2e8b57;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 28px;
  }
  .cover .judul {
    font-size: 20px;
    color: #16315c;
    font-weight: bold;
    line-height: 1.35;
    margin: 0 0 8px;
  }
  .cover .subjudul {
    font-size: 13px;
    color: #1e4a7a;
    margin: 0 0 40px;
  }
  .cover .meta-box {
    display: inline-block;
    border: 1.5px solid #16315c;
    padding: 14px 28px;
    margin-top: 20px;
    text-align: left;
    font-size: 11px;
  }
  .cover .footer-note {
    position: absolute;
    bottom: 60px;
    left: 0;
    right: 0;
    text-align: center;
    color: #6b7a90;
    font-size: 10px;
  }

  .toc-table td { border: none; border-bottom: 1px dotted #c9d4e3; padding: 5px 0; }
  .toc-table .num { width: 40px; color: #16315c; font-weight: bold; }
  .toc-table .page { width: 40px; text-align: right; color: #6b7a90; }

  .header-bar {
    background: #16315c;
    color: #fff;
    padding: 8px 12px;
    margin: -10px -10px 16px;
    font-size: 10px;
  }
  .header-bar span { color: #a8d5b5; }

  .callout {
    background: #eef6ff;
    border: 1px solid #b7cce6;
    padding: 8px 10px;
    margin: 8px 0 12px;
    font-size: 10px;
  }
  .callout.warn {
    background: #fff8e8;
    border-color: #e0c98a;
  }
</style>

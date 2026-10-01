@php
  $shot = fn (string $key, string $caption) => [
      'path' => $screenshots[$key] ?? null,
      'caption' => $caption,
  ];
@endphp

<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB IX — PANDUAN BHP KEBERSIHAN</h1>

@include('documentation.pdf.partials.screenshot', $shot('bhp', 'Gambar 9.1 — Portal BHP Kebersihan'))

<h2>9.1 Pengguna Publik</h2>
<div class="feature">
  <h3>Cari / scan barcode</h3>
  <div class="meta">URL: /public/bhp-kebersihan</div>
  <p><strong>Langkah:</strong> Buka landing → cari kode/nama atau scan barcode → buka halaman barang
    <code>/public/bhp-kebersihan/barang/{token}</code> → isi formulir pengambilan → terima nomor sukses.</p>
</div>

<h2>9.2 Admin BHP</h2>
<p>Login: <code>/bhp-kebersihan/login</code> (middleware <code>bhp.admin</code>). Menu difilter
  oleh role pada tabel <code>bhp_kebersihan_admins</code> melalui <code>BhpAccess</code>.</p>

<table class="doc">
  <thead><tr><th>Fitur</th><th>Keterangan</th></tr></thead>
  <tbody>
    <tr><td>Master Barang</td><td>CRUD, foto, koreksi stok, export Excel</td></tr>
    <tr><td>Barcode</td><td>Generate per item / massal, print, download; logo Kemenkes pada label</td></tr>
    <tr><td>Import Foto OCR</td><td>OCR multi-provider (Gemini/OpenAI/OCR.Space/Tesseract) via config</td></tr>
    <tr><td>Permintaan</td><td>Verifikasi setujui/tolak (role verifikator/superadmin)</td></tr>
    <tr><td>Pengambilan</td><td>Tandai sudah diambil (role pengambilan/superadmin)</td></tr>
    <tr><td>Stok Akhir / Opname / Laporan</td><td>Monitoring stok dan ekspor</td></tr>
  </tbody>
</table>

<h2>9.3 Alur Singkat</h2>
<div class="flow-box">
  Scan barcode publik<br><div class="arrow">↓</div>
  Ajukan pengambilan<br><div class="arrow">↓</div>
  Verifikasi admin<br><div class="arrow">↓</div>
  Proses penyerahan<br><div class="arrow">↓</div>
  Stok berkurang
</div>
<div class="page-break"></div>

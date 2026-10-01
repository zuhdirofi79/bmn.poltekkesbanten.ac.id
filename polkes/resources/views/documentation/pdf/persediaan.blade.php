@php
  $shot = fn (string $key, string $caption) => [
      'path' => $screenshots[$key] ?? null,
      'caption' => $caption,
  ];
@endphp

<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB VIII — PANDUAN PERSEDIAAN ASET BMN</h1>

@include('documentation.pdf.partials.screenshot', $shot('persediaan', 'Gambar 8.1 — Portal Persediaan Aset BMN'))

<h2>8.1 Alur Stok</h2>
<div class="flow-box">
  Barang masuk (pembelian / import)<br><div class="arrow">↓</div>
  Stok bertambah<br><div class="arrow">↓</div>
  Permintaan pemohon<br><div class="arrow">↓</div>
  Approval admin unit<br><div class="arrow">↓</div>
  Barang dikeluarkan<br><div class="arrow">↓</div>
  Stok berkurang
</div>

<h2>8.2 Panduan Publik / Pemohon</h2>
<div class="feature">
  <h3>Pilih unit kerja &amp; autentikasi</h3>
  <ol>
    <li>Buka <code>/public/persediaan</code>.</li>
    <li>Pilih unit (Keperawatan, Kebidanan, TLM, Direktorat, dll.).</li>
    <li>Registrasi atau login pemohon.</li>
  </ol>
</div>

<div class="feature">
  <h3>Membuat permintaan barang</h3>
  <p>Menu permintaan (middleware <code>persediaan.pemohon</code>): buat draft → ajukan →
    pantau status → unduh PDF form permintaan.</p>
</div>

<div class="feature">
  <h3>Alur QR Rak (unit Direktorat)</h3>
  <p>
    Unit pada <code>config/persediaan.php → rack_enabled_units</code> (saat ini: Direktorat)
    memakai QR akses pintu dan QR rak. Scan mengarah ke
    <code>/public/persediaan/akses/{token}</code> atau <code>/public/persediaan/rak/{token}</code>
    untuk konfirmasi identitas dan membuat permintaan dari lokasi rak.
  </p>
</div>

<h2>8.3 Panduan Admin Persediaan</h2>
<p>Login: <code>/persediaan/login</code> (middleware <code>persediaan.admin</code>).</p>
<table class="doc">
  <thead><tr><th>Menu</th><th>Fungsi</th></tr></thead>
  <tbody>
    <tr><td>Dashboard</td><td>Ringkasan operasional unit</td></tr>
    <tr><td>Master Barang</td><td>CRUD, import Excel, generate/print barcode Code128</td></tr>
    <tr><td>Master Rak</td><td>QR rak, assign barang, mutasi, QR akses (Direktorat)</td></tr>
    <tr><td>Pembelian</td><td>Input pembelian, import, lihat detail</td></tr>
    <tr><td>Permintaan</td><td>Setujui / tolak / keluarkan stok, PDF</td></tr>
    <tr><td>Stok Akhir</td><td>Posisi stok + export Excel</td></tr>
    <tr><td>Stock Opname</td><td>Opname, laporan PDF/Excel, berita acara</td></tr>
    <tr><td>Laporan</td><td>Rekap transaksi + export</td></tr>
    <tr><td>Pengaturan</td><td>Konfigurasi modul</td></tr>
  </tbody>
</table>

<p>
  Approver per unit dikonfigurasi di <code>config/persediaan.php</code>.
  Superadmin persediaan dapat melihat seluruh unit.
</p>
<div class="page-break"></div>

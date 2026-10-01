@php
  $shot = fn (string $key, string $caption) => [
      'path' => $screenshots[$key] ?? null,
      'caption' => $caption,
  ];
@endphp

<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB IV — PANDUAN MODUL ASET BMN</h1>

<p>
  Modul Aset BMN dikelola setelah login (<code>middleware: auth</code>) pada prefix
  <code>/bmn</code>. Menu sidebar mencakup Dashboard, Data Aset, Mutasi Lokasi, dan Laporan BMN.
</p>

@include('documentation.pdf.partials.screenshot', $shot('dashboard_aset', 'Gambar 4.1 — Dashboard Aset BMN'))

<div class="feature">
  <h3>4.1 Dashboard</h3>
  <div class="meta"><strong>URL:</strong> /bmn &nbsp;|&nbsp; <strong>Route:</strong> bmn.dashboard.index</div>
  <p><strong>Tujuan:</strong> Ringkasan status layanan BMN bagi petugas yang telah login.</p>
  <p><strong>Langkah:</strong> Login → buka menu Aset BMN / Dashboard.</p>
  <p><strong>Output:</strong> Tampilan ringkasan dashboard admin BMN.</p>
</div>

<div class="feature">
  <h3>4.2 Daftar Aset, Filter, dan Pencarian</h3>
  <div class="meta"><strong>URL:</strong> /bmn/aset &nbsp;|&nbsp; <strong>Route:</strong> bmn.aset.index</div>
  <p><strong>Tujuan:</strong> Menampilkan inventaris aset dengan filter multi-kriteria.</p>
  <p><strong>Input filter (dari AsetController):</strong> kata kunci (q), kode barang, NUP,
    nama barang, merk, kategori, kondisi, status, lokasi, satker, pengguna ruangan,
    tahun/tanggal perolehan, rentang nilai, rentang tanggal dibuat.</p>
  <p><strong>Langkah:</strong> Buka Data Aset → isi filter → terapkan → navigasi paginasi.</p>
  <p><strong>Output:</strong> Tabel aset terfilter.</p>
</div>

@include('documentation.pdf.partials.screenshot', $shot('master_aset', 'Gambar 4.2 — Daftar / Master Aset BMN'))

<div class="feature">
  <h3>4.3 Tambah dan Edit Aset</h3>
  <div class="meta">Routes: bmn.aset.create / store / edit / update</div>
  <p><strong>Tujuan:</strong> Mencatat atau memperbarui data aset termasuk foto.</p>
  <p><strong>Input utama:</strong> kode barang, NUP, nama, merk, kategori, nilai &amp; tanggal perolehan,
    kondisi, pengguna, satker, lokasi, foto.</p>
  <p><strong>Output:</strong> Data tersimpan; foto disimpan melalui AsetBmnFotoService.</p>
</div>

<div class="feature">
  <h3>4.4 Import &amp; Export Excel</h3>
  <ul>
    <li>Unduh template: <code>bmn.aset.template</code></li>
    <li>Import: <code>POST bmn.aset.import</code></li>
    <li>Export seluruh (mengikuti filter): <code>bmn.aset.export.excel</code></li>
    <li>Export terpilih: <code>POST bmn.aset.export.selected.excel</code></li>
    <li>Bulk hapus: <code>POST bmn.aset.bulk-delete</code></li>
  </ul>
</div>

<div class="feature">
  <h3>4.5 Mutasi Lokasi</h3>
  <div class="meta">Routes: bmn.mutasi.index / create / store</div>
  <p><strong>Tujuan:</strong> Memindahkan lokasi aset dan mencatat histori mutasi.</p>
  <p><strong>Langkah:</strong> Menu Mutasi Lokasi → buat mutasi → pilih aset &amp; lokasi baru → simpan.</p>
</div>

<div class="feature">
  <h3>4.6 Laporan Aset</h3>
  <div class="meta">URL: /bmn/laporan &nbsp;|&nbsp; Route: bmn.laporan.index</div>
  <p><strong>Tujuan:</strong> Monitoring dan rekapitulasi data aset untuk pimpinan/unit BMN.</p>
</div>

<div class="callout">
  Kartu portal “Aset BMN” mengarahkan ke <code>/login</code> karena pengelolaan aset memerlukan autentikasi.
</div>
<div class="page-break"></div>

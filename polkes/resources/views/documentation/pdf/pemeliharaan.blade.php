@php
  $shot = fn (string $key, string $caption) => [
      'path' => $screenshots[$key] ?? null,
      'caption' => $caption,
  ];
@endphp

<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB VII — PANDUAN PEMELIHARAAN BMN</h1>

<div class="callout warn">
  <strong>Status modul (hasil audit):</strong> Halaman publik dan dashboard admin bersifat
  <em>informasional</em>. Belum tersedia form pengajuan online, tracking status pekerjaan,
  atau workflow penyelesaian di database. Pengajuan resmi tetap melalui nota dinas /
  koordinasi unit BMN.
</div>

@include('documentation.pdf.partials.screenshot', $shot('pemeliharaan', 'Gambar 7.1 — Halaman informasi Pemeliharaan BMN'))

<h2>7.1 Halaman Publik</h2>
<div class="feature">
  <div class="meta">URL: /public/pemeliharaan &nbsp;|&nbsp; Route: public.pemeliharaan.index</div>
  <p><strong>Tujuan:</strong> Menyampaikan alur permintaan pemeliharaan kepada unit kerja.</p>
  <p><strong>Langkah penggunaan yang didokumentasikan di UI:</strong></p>
  <ol>
    <li>Unit kerja mengajukan permintaan melalui nota dinas / surat resmi.</li>
    <li>Tim BMN mengidentifikasi kerusakan dan ketersediaan suku cadang.</li>
    <li>Pemeliharaan dijadwalkan dan dilaporkan setelah selesai.</li>
  </ol>
</div>

<h2>7.2 Dashboard Admin</h2>
<div class="feature">
  <div class="meta">URL: /bmn/pemeliharaan-aset &nbsp;|&nbsp; Route: bmn.pemeliharaan-aset.index</div>
  <p>
    Menampilkan kartu statistik placeholder (Total Barang, Jadwal Hari Ini, Perlu Tindak Lanjut,
    Dalam Perbaikan) beserta ringkasan alur yang sama. Fitur operasional penuh akan menyusul
    pada tahap pengembangan berikutnya.
  </p>
</div>
<div class="page-break"></div>

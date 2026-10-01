@php
  $shot = fn (string $key, string $caption) => [
      'path' => $screenshots[$key] ?? null,
      'caption' => $caption,
  ];
@endphp

<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB V — PANDUAN PEMINJAMAN BMN</h1>

<p>
  Modul peminjaman memiliki jalur publik/internal dan jalur admin setelah login.
  Endpoint publik utama: <code>/public/peminjaman-internal</code> serta form
  <code>/bmn/peminjaman-aset/create</code> (tanpa login, throttle).
</p>

@include('documentation.pdf.partials.screenshot', $shot('peminjaman', 'Gambar 5.1 — Layanan Peminjaman BMN'))

<h2>5.1 Alur Proses</h2>
<div class="flow-box">
  Pengguna/Unit Kerja<br><div class="arrow">↓</div>
  Pengajuan + unggah dokumen<br><div class="arrow">↓</div>
  Screening Rumah Tangga<br><div class="arrow">↓</div>
  Approval Singgih → Approval Hendri<br><div class="arrow">↓</div>
  Penandaan Dipinjam<br><div class="arrow">↓</div>
  Pengembalian &amp; Verifikasi
</div>

<h2>5.2 Panduan Pengguna / Pemohon</h2>
<div class="feature">
  <h3>Mengajukan peminjaman</h3>
  <p><strong>Tujuan:</strong> Mengajukan peminjaman aset untuk keperluan unit/kegiatan.</p>
  <p><strong>Langkah:</strong></p>
  <ol>
    <li>Buka Portal → Peminjaman BMN, atau langsung <code>/public/peminjaman-internal</code> / form create.</li>
    <li>Isi identitas pemohon, data aset, periode pinjam, dan keperluan.</li>
    <li>Unggah dokumen pendukung (nota dinas) sesuai formulir.</li>
    <li>Kirim pengajuan; catat nomor/referensi untuk pelacakan.</li>
  </ol>
  <p><strong>Output:</strong> Permohonan tersimpan dengan status menunggu proses admin.</p>
</div>

<div class="feature">
  <h3>Pengembalian oleh pemohon</h3>
  <p>Form publik: <code>/bmn/pengembalian-aset/create</code> (route bmn.pengembalian-aset.create-public)
    dan jalur internal pinjam/kembali di <code>/public/peminjaman-internal/*</code>.</p>
</div>

<h2>5.3 Panduan Admin</h2>
<table class="doc">
  <thead><tr><th>Aktivitas</th><th>Route / Aksi</th><th>Keterangan</th></tr></thead>
  <tbody>
    <tr><td>Daftar permintaan</td><td>bmn.peminjaman-aset.index</td><td>Filter status, pantau antrian</td></tr>
    <tr><td>Detail &amp; dokumen</td><td>show / documents.view / download</td><td>Lihat nota dinas &amp; lampiran</td></tr>
    <tr><td>Screening</td><td>bmn.peminjaman-aset.screening</td><td>Rumah Tangga BMN</td></tr>
    <tr><td>Approve/Reject Singgih</td><td>approve-singgih / reject-singgih</td><td>Tahap 1 workflow baru</td></tr>
    <tr><td>Approve/Reject Hendri</td><td>approve-hendri / reject-hendri</td><td>Tahap 2 / final sesuai wewenang</td></tr>
    <tr><td>Mark dipinjam</td><td>mark-dipinjam</td><td>Aset berstatus dipinjam</td></tr>
    <tr><td>Pengembalian</td><td>pengembalian-aset.verify / reject</td><td>Verifikasi kondisi pengembalian</td></tr>
    <tr><td>Verifikasi publik</td><td>bmn.public-verification.*</td><td>Token 64 karakter</td></tr>
  </tbody>
</table>

<div class="callout">
  Wewenang actor (Hendri, Singgih, Munawir, superadmin) dikunci melalui konstanta ID
  pada <code>App\User</code>, bukan sekadar nama role generik.
</div>
<div class="page-break"></div>

@php
  $shot = fn (string $key, string $caption) => [
      'path' => $screenshots[$key] ?? null,
      'caption' => $caption,
  ];
@endphp

<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB VI — PANDUAN SEWA BMN</h1>

<p>
  Layanan sewa tersedia untuk publik pada <code>/public/sewa</code> dan dikelola admin pada
  <code>/sewa-admin</code> (middleware <code>sewa.admin</code>). Panel BMN juga memiliki
  ringkasan sewa di <code>/bmn/sewa</code> untuk user yang login.
</p>

@include('documentation.pdf.partials.screenshot', $shot('sewa', 'Gambar 6.1 — Portal Sewa BMN'))

<h2>6.1 Pengajuan oleh Penyewa</h2>
<div class="feature">
  <h3>Pemilihan jenis penyewa</h3>
  <ul>
    <li><strong>Umum:</strong> <code>/public/sewa/umum</code> (storeUmum)</li>
    <li><strong>Pegawai:</strong> <code>/public/sewa/pegawai</code> (storePegawai)</li>
  </ul>
  <p><strong>Langkah:</strong> Buka portal sewa → pilih jenis → isi formulir → unggah dokumen
    yang diminta → kirim → halaman sukses <code>/public/sewa/success/{id}</code>.</p>
</div>

<div class="feature">
  <h3>Cek status</h3>
  <p>Formulir <code>/public/sewa/status</code> (POST lookup) untuk memantau perkembangan pengajuan
    tanpa login admin.</p>
</div>

<h2>6.2 Tarif / SK Tarif BLU</h2>
<p>
  Admin mengelola master tarif pada <code>/sewa-admin/tarif</code> termasuk unggah foto,
  set foto utama, urutan, dan hapus foto. Fitur ini mendukung publikasi informasi tarif
  (referensi SK Tarif BLU) kepada calon penyewa melalui kanal admin sewa.
</p>

<h2>6.3 Verifikasi Admin &amp; Pembayaran</h2>
<table class="doc">
  <thead><tr><th>Aksi Admin</th><th>Route</th><th>Peran tipikal</th></tr></thead>
  <tbody>
    <tr><td>Daftar &amp; detail sewa</td><td>sewa-admin.sewa.index/show</td><td>Semua admin sewa</td></tr>
    <tr><td>Teruskan ke verifikator</td><td>forward-to-verifier</td><td>Koordinator</td></tr>
    <tr><td>Konfirmasi pembayaran</td><td>confirm-payment</td><td>Verifikator (Indah) / superadmin</td></tr>
    <tr><td>Tolak pembayaran</td><td>reject-payment</td><td>Verifikator / superadmin</td></tr>
    <tr><td>Tandai dikirim ke tim</td><td>mark-sent-to-team</td><td>Koordinator</td></tr>
    <tr><td>Koordinasi selesai</td><td>mark-coordination-done</td><td>Koordinator</td></tr>
  </tbody>
</table>

<h2>6.4 Pelaporan &amp; Komplain</h2>
<ul>
  <li>Pelaporan bulanan + export Excel/CSV: <code>sewa-admin.pelaporan.*</code></li>
  <li>Komplain asrama: <code>sewa-admin.komplain.index</code></li>
</ul>
<div class="page-break"></div>

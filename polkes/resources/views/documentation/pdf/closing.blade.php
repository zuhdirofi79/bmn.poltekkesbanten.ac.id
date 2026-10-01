<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB X — LAPORAN DAN MONITORING</h1>

<table class="doc">
  <thead><tr><th>Modul</th><th>Laporan / Monitoring</th><th>Format</th></tr></thead>
  <tbody>
    <tr><td>Aset BMN</td><td>/bmn/laporan, export Excel aset</td><td>Web + Excel</td></tr>
    <tr><td>Peminjaman</td><td>Index status, dokumen, final report approval</td><td>Web + file dokumen</td></tr>
    <tr><td>Sewa</td><td>Pelaporan bulanan admin sewa</td><td>Excel / CSV</td></tr>
    <tr><td>Persediaan</td><td>Laporan, stok akhir, opname (PDF berita acara)</td><td>Excel / PDF</td></tr>
    <tr><td>BHP Kebersihan</td><td>Laporan &amp; stok akhir</td><td>Excel</td></tr>
  </tbody>
</table>

<p>
  Histori aktivitas operasional tercermin melalui status transaksi pada masing-masing modul
  (permintaan, approval, mutasi stok, mutasi lokasi aset). Modul audit trail terpusat generik
  tidak ditemukan sebagai fitur mandiri pada audit route; jejak proses mengikuti status
  record dan log aksi di controller terkait.
</p>
<div class="page-break"></div>

<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB XI — TROUBLESHOOTING</h1>

<table class="doc">
  <thead><tr><th>Masalah</th><th>Penyebab Umum</th><th>Solusi</th></tr></thead>
  <tbody>
    <tr>
      <td>Tidak bisa login</td>
      <td>Username/password salah; akun tidak punya akses modul; session habis</td>
      <td>Pastikan URL login sesuai modul; hubungi admin untuk reset; clear cookie browser</td>
    </tr>
    <tr>
      <td>Login berhasil tapi menu sewa/persediaan/BHP ditolak</td>
      <td>Middleware sewa.admin / persediaan.admin / bhp.admin menolak ID/role</td>
      <td>Pastikan user terdaftar di allow-list / tabel admin modul</td>
    </tr>
    <tr>
      <td>File upload gagal</td>
      <td>Ukuran melebihi limit PHP; path uploads production salah</td>
      <td>Cek upload_max_filesize; set BMN_*_BASE_PATH / PERSEDIAAN_UPLOADS_PATH di .env</td>
    </tr>
    <tr>
      <td>Data tidak muncul</td>
      <td>Filter aktif; unit kerja berbeda; paginasi</td>
      <td>Reset filter; pastikan satker/unit sesuai role admin</td>
    </tr>
    <tr>
      <td>Barcode / QR tidak tampil</td>
      <td>Belum digenerate; file gambar hilang; permission folder</td>
      <td>Jalankan generate barcode/QR di master; pastikan folder uploads writable</td>
    </tr>
    <tr>
      <td>Stok tidak berubah</td>
      <td>Permintaan belum “dikeluarkan”; opname belum disimpan; transaksi gagal</td>
      <td>Lengkapi approval → keluarkan; cek log error; ulangi transaksi</td>
    </tr>
    <tr>
      <td>Screenshot dokumentasi kosong/placeholder</td>
      <td>Belum menjalankan capture script saat server hidup</td>
      <td>Jalankan <code>node scripts/capture-documentation-screenshots.js</code> lalu regenerate PDF</td>
    </tr>
  </tbody>
</table>
<div class="page-break"></div>

<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB XII — PENUTUP</h1>

<p>
  Panduan ini disusun otomatis dari source code Portal Layanan BMN Poltekkes Kemenkes Banten
  agar isi pedoman operasional selaras dengan fitur yang benar-benar diimplementasikan.
  Modul yang masih informasional atau dalam pengembangan (pemeliharaan penuh, penghapusan aset)
  dicantumkan secara jujur agar tidak menimbulkan ekspektasi berlebih kepada pengguna.
</p>
<p>
  Untuk memperbarui dokumen setelah ada rilis fitur baru, jalankan kembali perintah:
</p>
<p><code>php artisan documentation:generate</code></p>
<p>
  Semoga sistem ini mendukung tertib administrasi BMN, meningkatkan kualitas layanan publik,
  serta mempermudah monitoring oleh pimpinan Poltekkes Kemenkes Banten.
</p>

<div class="callout" style="margin-top:24px;">
  <strong>Hasil audit ringkas saat generate:</strong><br>
  Route bernama: {{ $audit['route_count'] }} &nbsp;|&nbsp;
  Controller: {{ $audit['controller_count'] }} &nbsp;|&nbsp;
  Model: {{ $audit['model_count'] }} &nbsp;|&nbsp;
  Views: {{ $audit['view_count'] }}<br>
  Middleware terkait: {{ implode(', ', $audit['middleware']) }}
</div>

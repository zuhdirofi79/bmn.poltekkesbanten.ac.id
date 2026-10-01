@php
  /** @var array<string,string> $screenshots */
  $shot = fn (string $key, string $caption) => [
      'path' => $screenshots[$key] ?? null,
      'caption' => $caption,
  ];
@endphp

{{-- ===================== BAB I ===================== --}}
<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB I — PENDAHULUAN</h1>

<h2>1.1 Latar Belakang</h2>
<p>
  Pengelolaan Barang Milik Negara (BMN) di Poltekkes Kemenkes Banten menuntut
  akurasi data aset, kemudahan layanan peminjaman dan sewa, serta pengendalian
  persediaan barang. Portal Layanan BMN dibangun sebagai sistem informasi berbasis
  web (Laravel) untuk mengintegrasikan layanan tersebut dalam satu pintu publik
  dan beberapa panel administrasi berwenang.
</p>

<h2>1.2 Tujuan Aplikasi</h2>
<ol>
  <li>Menyediakan portal tunggal layanan BMN bagi pengguna internal dan publik.</li>
  <li>Mencatat dan mengelola data aset BMN secara digital, termasuk mutasi dan laporan.</li>
  <li>Memfasilitasi alur peminjaman, pengembalian, dan sewa dengan verifikasi berjenjang.</li>
  <li>Mengendalikan stok persediaan aset dan BHP kebersihan melalui permintaan, approval, serta barcode/QR.</li>
</ol>

<h2>1.3 Manfaat</h2>
<ul>
  <li>Transparansi status pengajuan bagi pemohon.</li>
  <li>Pengurangan kesalahan pencatatan manual.</li>
  <li>Jejak proses approval dan dokumen pendukung yang terdokumentasi.</li>
  <li>Laporan dan ekspor data untuk keperluan monitoring pimpinan.</li>
</ul>

<h2>1.4 Ruang Lingkup Sistem</h2>
<p>Berdasarkan audit source code, modul yang tersedia pada portal adalah:</p>
<table class="doc">
  <thead>
    <tr><th>No</th><th>Modul</th><th>Status di Source</th><th>URL Utama</th></tr>
  </thead>
  <tbody>
    @foreach($modules as $i => $m)
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $m['name'] }}</td>
        <td>
          @if($m['status'] === 'aktif')
            <span class="badge badge-green">Aktif</span>
          @elseif($m['status'] === 'informasional')
            <span class="badge badge-yellow">Informasional</span>
          @else
            <span class="badge badge-gray">Dalam pengembangan</span>
          @endif
        </td>
        <td>{{ implode(', ', array_slice($m['urls'] ?? [], 0, 2)) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
<div class="page-break"></div>

{{-- ===================== BAB II ===================== --}}
<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB II — GAMBARAN UMUM SISTEM</h1>

<h2>2.1 Arsitektur Aplikasi</h2>
<p>
  Aplikasi menggunakan kerangka kerja Laravel 9, autentikasi Laravel UI + Spatie Permission,
  serta middleware khusus modul (sewa.admin, persediaan.admin, persediaan.pemohon, bhp.admin).
  Antarmuka publik dipisahkan dari panel admin per layanan agar hak akses tetap terjaga.
</p>
<ul>
  <li><strong>Backend:</strong> PHP 8.2+ / Laravel 9</li>
  <li><strong>PDF laporan:</strong> barryvdh/laravel-dompdf</li>
  <li><strong>Excel:</strong> maatwebsite/excel</li>
  <li><strong>Permission:</strong> spatie/laravel-permission</li>
</ul>

<h2>2.2 Modul Utama Portal</h2>
<p>
  Beranda publik (<code>/</code>) menampilkan enam kartu layanan: Aset BMN, Peminjaman BMN,
  Sewa BMN, Pemeliharaan BMN, Persediaan Aset BMN, dan BHP Kebersihan.
</p>

@include('documentation.pdf.partials.screenshot', $shot('homepage', 'Gambar 2.1 — Portal Layanan BMN (beranda publik)'))

<h2>2.3 Alur Umum Penggunaan</h2>
<div class="flow-box">
  Pengguna mengakses Portal<br><div class="arrow">↓</div>
  Login / pilih layanan publik<br><div class="arrow">↓</div>
  Mengisi formulir / memilih barang<br><div class="arrow">↓</div>
  Proses administrasi &amp; unggah dokumen<br><div class="arrow">↓</div>
  Persetujuan / verifikasi pejabat berwenang<br><div class="arrow">↓</div>
  Pelaksanaan layanan &amp; pelaporan
</div>
<div class="page-break"></div>

{{-- ===================== BAB III ===================== --}}
<div class="header-bar">SIM-BMN <span>|</span> Poltekkes Kemenkes Banten</div>
<h1>BAB III — LOGIN DAN AUTENTIKASI</h1>

<h2>3.1 Login Admin Aplikasi</h2>
<p><strong>Alamat:</strong> <code>/login</code> (Laravel Auth UI). Digunakan untuk masuk ke panel
  yang dilindungi middleware <code>auth</code>, termasuk modul <code>/bmn/*</code> dan
  area admin Spatie <code>role:admin</code>.</p>
@include('documentation.pdf.partials.screenshot', $shot('login', 'Gambar 3.1 — Halaman login aplikasi'))

<h2>3.2 Login Admin Modul Khusus</h2>
<table class="doc">
  <thead><tr><th>Portal Admin</th><th>URL</th><th>Middleware</th></tr></thead>
  <tbody>
    @foreach($loginUrls as $label => $url)
      <tr><td>{{ $label }}</td><td><code>{{ $url }}</code></td><td>—</td></tr>
    @endforeach
  </tbody>
</table>
@include('documentation.pdf.partials.screenshot', $shot('sewa_admin', 'Gambar 3.2 — Login Admin Sewa BMN (/sewa-admin/login)'))

<h2>3.3 Login / Registrasi Pemohon Persediaan</h2>
<p>
  Pemohon persediaan menggunakan guard terpisah (<code>persediaan</code>). Alur:
  pilih unit kerja di <code>/public/persediaan</code> → registrasi
  <code>/{unit}/register</code> atau login <code>/{unit}/login</code> → akses menu permintaan.
</p>

<h2>3.4 Registrasi Umum</h2>
<p>
  Route <code>Auth::routes()</code> menyediakan registrasi bawaan Laravel UI
  (<code>/register</code>). Untuk layanan publik sewa, peminjaman internal, dan BHP,
  formulir pengajuan tersedia tanpa akun admin.
</p>

<h2>3.5 Hak Akses Pengguna</h2>
<p>Matriks berikut diambil dari konstanta <code>User.php</code>, model admin modul,
  middleware, dan Spatie roles — bukan perkiraan.</p>
<table class="doc">
  <thead>
    <tr><th>Role</th><th>Akses Menu</th><th>Fungsi</th></tr>
  </thead>
  <tbody>
    @foreach($roles as $row)
      <tr>
        <td>{{ $row['role'] }}</td>
        <td>{{ $row['menu'] }}</td>
        <td>{{ $row['fungsi'] }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
<div class="page-break"></div>

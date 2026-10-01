<div class="cover">
  <img class="logo" src="{{ $logoPath }}" alt="Logo Poltekkes">
  <div class="instansi">Politeknik Kesehatan Kementerian Kesehatan Banten</div>
  <div class="judul">PANDUAN PENGGUNAAN</div>
  <div class="judul" style="font-size:16px; margin-bottom:6px;">
    SISTEM INFORMASI BARANG MILIK NEGARA (BMN)
  </div>
  <div class="subjudul">POLTEKKES KEMENKES BANTEN</div>

  <div class="meta-box">
    <div><strong>Dokumen</strong> : Manual Book / Panduan Pengguna</div>
    <div><strong>Versi aplikasi</strong> : {{ $version }}</div>
    <div><strong>Tahun</strong> : {{ $year }}</div>
    <div><strong>Dibuat otomatis</strong> : {{ $generatedAt->format('d F Y H:i') }} WIB</div>
    <div><strong>Sumber</strong> : Audit source code Laravel (routes, controllers, models, views)</div>
  </div>

  <div class="footer-note">
    SIM-BMN &mdash; Portal Layanan Barang Milik Negara<br>
    Dokumen resmi untuk keperluan operasional internal
  </div>
</div>
<div class="page-break"></div>

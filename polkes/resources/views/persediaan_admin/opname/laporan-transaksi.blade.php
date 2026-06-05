<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #000; font-size: 11px; margin: 24px; }
    .title { text-align: center; margin-bottom: 16px; }
    .title h1 { font-size: 13px; font-weight: bold; margin: 0 0 4px; text-transform: uppercase; }
    .title p { margin: 2px 0; font-size: 11px; }
    .meta { margin-bottom: 12px; }
    .meta table { width: 100%; border-collapse: collapse; }
    .meta td { padding: 2px 0; vertical-align: top; font-size: 11px; }
    .meta .lbl { width: 120px; }
    .jenis { margin: 10px 0 14px; font-size: 11px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border: 1px solid #000; padding: 5px 6px; font-size: 10px; }
    table.data th { text-align: center; font-weight: bold; background: #fff; }
    table.data td.num { text-align: right; }
    table.data td.kode { text-align: center; white-space: nowrap; }
    .footer { margin-top: 8px; width: 100%; border-collapse: collapse; }
    .footer td { border: 1px solid #000; padding: 5px 6px; font-size: 10px; }
    .footer .lbl { font-weight: bold; width: 70%; }
    .footer .num { text-align: right; width: 30%; }
  </style>
</head>
<body>
  <div class="title">
    <h1>DAFTAR TRANSAKSI PERSEDIAAN</h1>
    <p>UNTUK PERIODE YANG BERAKHIR TANGGAL {{ optional($laporan['tanggal_opname'])->format('d-m-Y') }}</p>
    <p>TAHUN ANGGARAN : {{ $laporan['tahun_anggaran'] }}</p>
  </div>

  <div class="meta">
    <table>
      <tr>
        <td class="lbl">NAMA</td>
        <td>: {{ $laporan['nama_admin'] }}</td>
      </tr>
      <tr>
        <td class="lbl">KODE UAPKPB</td>
        <td>: {{ $kodeUapkpb }}</td>
      </tr>
    </table>
  </div>

  <div class="jenis">
    <strong>JENIS TRANSAKSI:</strong><br>
    {{ $jenisTransaksi }}
  </div>

  <table class="data">
    <thead>
      <tr>
        <th style="width:18%;">KODE</th>
        <th style="width:42%;">URAIAN</th>
        <th style="width:15%;">KUANTITAS</th>
        <th style="width:25%;">RUPIAH</th>
      </tr>
    </thead>
    <tbody>
      @forelse($laporan['rows'] as $row)
        <tr>
          <td class="kode">{{ $row['kode'] }}</td>
          <td>{{ $row['uraian'] }}</td>
          <td class="num">{{ $row['kuantitas'] }}</td>
          <td class="num">{{ $formatter->formatRupiah($row['rupiah']) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="4" style="text-align:center;">Tidak ada selisih opname.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <table class="footer">
    <tr>
      <td class="lbl">Jumlah</td>
      <td class="num">{{ $laporan['total_kuantitas'] }}</td>
    </tr>
    <tr>
      <td class="lbl">Total Jumlah</td>
      <td class="num">{{ $formatter->formatRupiah($laporan['total_rupiah']) }}</td>
    </tr>
  </table>
</body>
</html>

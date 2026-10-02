<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Laporan Persediaan</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1c2430; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    .meta { margin-bottom: 10px; color: #526070; }
    .cards { width: 100%; border-collapse: separate; margin-bottom: 12px; }
    .cards td { width: 25%; padding: 8px; color: #fff; }
    .blue { background: #2f6fdb; }
    .green { background: #2e9e5b; }
    .amber { background: #e0a52b; }
    .purple { background: #7a5cd0; }
    .lbl { font-size: 8px; }
    .val { font-size: 13px; font-weight: bold; margin-top: 3px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border: 1px solid #d7deea; padding: 3px 4px; }
    table.data th { background: #1e3a5f; color: #fff; }
    table.data tfoot th { background: #eef3fb; color: #1c2430; }
    .end { text-align: right; }
  </style>
</head>
<body>
  <h1>Laporan Persediaan</h1>
  <div class="meta">
    Periode {{ \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') }}
    – {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('d/m/Y') }}
    @if($filters['unit']) · Unit {{ $filters['unit'] }} @endif
    @if($filters['q']) · Cari: {{ $filters['q'] }} @endif
  </div>
  <table class="cards">
    <tr>
      <td class="blue"><div class="lbl">Total Stok Saat Ini</div><div class="val">{{ number_format($summary['total_stok_saat_ini'], 0, ',', '.') }}</div></td>
      <td class="green"><div class="lbl">Nilai Barang Masuk</div><div class="val">Rp {{ number_format($summary['nilai_barang_masuk'], 0, ',', '.') }}</div></td>
      <td class="amber"><div class="lbl">Nilai Barang Keluar</div><div class="val">Rp {{ number_format($summary['nilai_barang_keluar'], 0, ',', '.') }}</div></td>
      <td class="purple"><div class="lbl">Nilai Persediaan Saat Ini</div><div class="val">Rp {{ number_format($summary['nilai_persediaan_saat_ini'], 0, ',', '.') }}</div></td>
    </tr>
  </table>
  <table class="data">
    <thead>
      <tr>
        <th>Kode</th><th>Nama</th><th>Unit</th><th>Satuan</th>
        <th class="end">Pembuka</th><th class="end">Masuk</th><th class="end">Keluar</th><th class="end">Koreksi</th><th class="end">Saat Ini</th>
        <th class="end">Harga</th><th class="end">Nilai Saat Ini</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
        <tr>
          <td>{{ $row['kode'] }}</td>
          <td>{{ $row['nama'] }}</td>
          <td>{{ $row['unit'] }}</td>
          <td>{{ $row['satuan'] }}</td>
          <td class="end">{{ number_format($row['stok_pembuka'], 0, ',', '.') }}</td>
          <td class="end">{{ number_format($row['masuk'], 0, ',', '.') }}</td>
          <td class="end">{{ number_format($row['keluar'], 0, ',', '.') }}</td>
          <td class="end">{{ number_format($row['koreksi'], 0, ',', '.') }}</td>
          <td class="end">{{ number_format($row['stok_saat_ini'], 0, ',', '.') }}</td>
          <td class="end">{{ number_format($row['harga'], 0, ',', '.') }}</td>
          <td class="end">{{ number_format($row['nilai_saat_ini'], 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <th colspan="4">Total</th>
        <th class="end">{{ number_format($summary['total_stok_pembuka'], 0, ',', '.') }}</th>
        <th class="end">{{ number_format($summary['jumlah_barang_masuk'], 0, ',', '.') }}</th>
        <th class="end">{{ number_format($summary['jumlah_barang_keluar'], 0, ',', '.') }}</th>
        <th class="end">{{ number_format($summary['jumlah_koreksi'], 0, ',', '.') }}</th>
        <th class="end">{{ number_format($summary['total_stok_saat_ini'], 0, ',', '.') }}</th>
        <th></th>
        <th class="end">{{ number_format($summary['nilai_persediaan_saat_ini'], 0, ',', '.') }}</th>
      </tr>
    </tfoot>
  </table>
</body>
</html>

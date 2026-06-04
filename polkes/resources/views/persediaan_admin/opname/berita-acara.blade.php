@php use App\Support\Persediaan\UnitKerja;
  $kondisiLabel = [
    'sesuai' => 'Sesuai', 'selisih_kurang' => 'Selisih Kurang', 'selisih_lebih' => 'Selisih Lebih',
    'rusak' => 'Rusak', 'kadaluarsa' => 'Kadaluarsa',
  ];
@endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1f2d3d; font-size: 12px; }
    .head { text-align: center; border-bottom: 2px solid #16315c; padding-bottom: 8px; margin-bottom: 14px; }
    h2 { margin: 0; font-size: 16px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background: #16315c; color: #fff; padding: 6px; font-size: 11px; text-align: left; }
    td { border: 1px solid #d7deea; padding: 6px; font-size: 11px; }
    .num { text-align: right; }
    .rekap td { width: 25%; text-align: center; }
    .sign { margin-top: 40px; width: 100%; }
    .sign td { border: none; text-align: center; }
  </style>
</head>
<body>
  <div class="head">
    <h2>BERITA ACARA STOCK OPNAME PERSEDIAAN</h2>
    <div>Poltekkes Kemenkes Banten — {{ UnitKerja::label($opname->unit_kerja) }}</div>
    <div>Tanggal: {{ optional($opname->tanggal_opname)->format('d F Y') }}</div>
  </div>

  <table class="rekap">
    <tr>
      <td><strong>{{ $rekap['kurang'] }}</strong><br>Selisih Kurang</td>
      <td><strong>{{ $rekap['lebih'] }}</strong><br>Selisih Lebih</td>
      <td><strong>{{ $rekap['rusak'] }}</strong><br>Rusak</td>
      <td><strong>{{ $rekap['kadaluarsa'] }}</strong><br>Kadaluarsa</td>
    </tr>
  </table>

  <table>
    <thead>
      <tr><th>No</th><th>Kode</th><th>Nama Barang</th><th>Satuan</th><th class="num">Sistem</th><th class="num">Fisik</th><th class="num">Selisih</th><th>Kondisi</th></tr>
    </thead>
    <tbody>
      @foreach($opname->items as $i => $it)
        <tr>
          <td>{{ $i+1 }}</td>
          <td>{{ $it->kode_barang }}</td>
          <td>{{ $it->nama_barang }}</td>
          <td>{{ $it->satuan }}</td>
          <td class="num">{{ $it->stok_sistem }}</td>
          <td class="num">{{ $it->stok_fisik }}</td>
          <td class="num">{{ $it->selisih > 0 ? '+' : '' }}{{ $it->selisih }}</td>
          <td>{{ $kondisiLabel[$it->kondisi] ?? $it->kondisi }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  @if($opname->catatan)<p style="margin-top:10px;"><strong>Catatan:</strong> {{ $opname->catatan }}</p>@endif

  <table class="sign">
    <tr>
      <td>Mengetahui,<br><br><br><br>(........................)</td>
      <td>Petugas Opname,<br><br><br><br>(........................)</td>
    </tr>
  </table>
</body>
</html>

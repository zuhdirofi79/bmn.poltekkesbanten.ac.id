@php use App\Support\Persediaan\UnitKerja; @endphp
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1f2d3d; font-size: 12px; }
    h2 { margin: 0; font-size: 16px; }
    .muted { color: #6b7a90; }
    .head { text-align: center; border-bottom: 2px solid #16315c; padding-bottom: 8px; margin-bottom: 14px; }
    table.meta td { padding: 2px 4px; vertical-align: top; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.items th { background: #2f6fdb; color: #fff; padding: 6px; font-size: 11px; text-align: left; }
    table.items td { border: 1px solid #d7deea; padding: 6px; font-size: 11px; }
    .num { text-align: right; }
    .sign-wrap { width: 100%; margin-top: 30px; }
    .sign-col { width: 48%; display: inline-block; vertical-align: top; text-align: center; }
    .sign-img { height: 70px; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; background: #eef1f6; }
  </style>
</head>
<body>
  <div class="head">
    <h2>FORM PERMINTAAN BARANG PERSEDIAAN</h2>
    <div class="muted">Poltekkes Kemenkes Banten — {{ UnitKerja::label($permintaan->unit_kerja) }}</div>
  </div>

  <table class="meta">
    <tr>
      <td><strong>No. Permintaan</strong></td><td>: {{ $permintaan->nomor_permintaan }}</td>
      <td style="width:30px;"></td>
      <td><strong>Tanggal</strong></td><td>: {{ optional($permintaan->tanggal_permintaan)->format('d M Y') }}</td>
    </tr>
    <tr>
      <td><strong>Pemohon</strong></td><td>: {{ $pemohon->nama }}</td>
      <td></td>
      <td><strong>Status</strong></td><td>: <span class="badge">{{ $permintaan->statusLabel() }}</span></td>
    </tr>
    <tr>
      <td><strong>NIP</strong></td><td>: {{ $pemohon->nip }}</td>
      <td></td>
      <td><strong>Jabatan</strong></td><td>: {{ $pemohon->jabatan ?: '-' }}</td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr>
        <th style="width:30px;">No</th>
        <th>Kode</th>
        <th>Nama Barang</th>
        <th>Satuan</th>
        <th class="num">Stok Tersedia</th>
        <th class="num">Diminta</th>
        <th class="num">Disetujui</th>
      </tr>
    </thead>
    <tbody>
      @foreach($permintaan->items as $i => $item)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $item->kode_barang }}</td>
          <td>{{ $item->nama_barang }}</td>
          <td>{{ $item->satuan }}</td>
          <td class="num">{{ number_format($item->stok_tersedia, 0, ',', '.') }}</td>
          <td class="num">{{ number_format($item->jumlah_diminta, 0, ',', '.') }}</td>
          <td class="num">{{ is_null($item->jumlah_disetujui) ? '-' : number_format($item->jumlah_disetujui, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <p style="margin-top:10px;"><strong>Keterangan:</strong> {{ $permintaan->keterangan }}</p>

  @if($permintaan->status === \App\Models\Persediaan\PersediaanPermintaan::STATUS_DITOLAK && $permintaan->catatan_admin)
    <p style="color:#b02a26;"><strong>Alasan Penolakan:</strong> {{ $permintaan->catatan_admin }}</p>
  @endif

  <div class="sign-wrap">
    <div class="sign-col">
      <div>Tanda Tangan Pemohon</div>
      @if($permintaan->tanda_tangan_pemohon_path && file_exists(public_path($permintaan->tanda_tangan_pemohon_path)))
        <img class="sign-img" src="{{ public_path($permintaan->tanda_tangan_pemohon_path) }}" alt="ttd pemohon">
      @else
        <div style="height:70px;"></div>
      @endif
      <div>( {{ $pemohon->nama }} )</div>
      <div class="muted">NIP. {{ $pemohon->nip }}</div>
    </div>
    <div class="sign-col">
      <div>Mengetahui / Menyetujui<br>Admin Persediaan</div>
      @if($permintaan->tanda_tangan_admin_path && file_exists(public_path($permintaan->tanda_tangan_admin_path)))
        <img class="sign-img" src="{{ public_path($permintaan->tanda_tangan_admin_path) }}" alt="ttd admin">
      @else
        <div style="height:70px;"></div>
      @endif
      <div>(...........................)</div>
    </div>
  </div>
</body>
</html>

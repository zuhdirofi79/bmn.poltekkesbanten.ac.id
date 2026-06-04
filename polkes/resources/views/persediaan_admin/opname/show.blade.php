@extends('layouts.persediaan_admin')

@section('page_title', 'Detail Stock Opname')

@php
  $kondisiLabel = [
    'sesuai' => 'Sesuai', 'selisih_kurang' => 'Selisih Kurang', 'selisih_lebih' => 'Selisih Lebih',
    'rusak' => 'Rusak', 'kadaluarsa' => 'Kadaluarsa',
  ];
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="{{ route('persediaan.admin.opname.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="ti ti-arrow-left"></i> Kembali
  </a>
  <div class="d-flex gap-2">
    <a href="{{ route('persediaan.admin.opname.export', $opname->id) }}" class="btn btn-prs-green btn-sm">
      <i class="ti ti-file-spreadsheet"></i> Export Excel
    </a>
    <a href="{{ route('persediaan.admin.opname.berita-acara', $opname->id) }}" target="_blank" class="btn btn-prs-blue btn-sm">
      <i class="ti ti-printer"></i> Cetak Berita Acara
    </a>
  </div>
</div>

<div class="pa-card mt-0">
  <h2>Opname {{ $opname->unit_kerja }} — {{ optional($opname->tanggal_opname)->format('d M Y') }}</h2>
  <p class="text-muted mb-0">{{ $opname->catatan ?: 'Tanpa catatan' }}</p>
</div>

<div class="pa-card">
  <div class="pa-rekap">
    <div class="box kurang"><div class="n">{{ $rekap['kurang'] }}</div><div class="lbl">Selisih Kurang</div></div>
    <div class="box lebih"><div class="n">{{ $rekap['lebih'] }}</div><div class="lbl">Selisih Lebih</div></div>
    <div class="box rusak"><div class="n">{{ $rekap['rusak'] }}</div><div class="lbl">Rusak</div></div>
    <div class="box kadaluarsa"><div class="n">{{ $rekap['kadaluarsa'] }}</div><div class="lbl">Kadaluarsa</div></div>
  </div>
</div>

<div class="pa-card">
  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr><th>Kode</th><th>Nama Barang</th><th class="text-end">Stok Sistem</th><th class="text-end">Stok Fisik</th><th class="text-end">Selisih</th><th>Keterangan</th></tr>
      </thead>
      <tbody>
        @foreach($opname->items as $it)
          <tr>
            <td><code>{{ $it->kode_barang }}</code></td>
            <td>{{ $it->nama_barang }}</td>
            <td class="text-end">{{ number_format($it->stok_sistem, 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($it->stok_fisik, 0, ',', '.') }}</td>
            <td class="text-end" style="color: {{ $it->selisih < 0 ? '#d9534f' : ($it->selisih > 0 ? '#2e9e5b' : '#6b7a90') }};">
              {{ $it->selisih > 0 ? '+' : '' }}{{ $it->selisih }}
            </td>
            <td>{{ $kondisiLabel[$it->kondisi] ?? $it->kondisi }}{{ $it->keterangan ? ' — '.$it->keterangan : '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection

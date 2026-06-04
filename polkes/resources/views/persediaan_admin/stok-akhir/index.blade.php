@extends('layouts.persediaan_admin')

@section('page_title', 'Stok Akhir (Per Unit Kerja)')

@php use App\Support\Persediaan\UnitKerja; @endphp

@section('content')
<div class="pa-card mt-0">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Unit Kerja</label>
      <select name="unit" class="form-select form-select-sm">
        <option value="">Semua Unit</option>
        @foreach($units as $u)<option value="{{ $u }}" @selected(($filters['unit'] ?? '') === $u)>{{ UnitKerja::label($u) }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Kode / Nama Barang</label>
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label">Dari</label>
      <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label">Sampai</label>
      <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <div class="form-check mt-4">
        <input type="checkbox" name="menipis" value="1" id="menipis" class="form-check-input" @checked(!empty($filters['menipis']))>
        <label class="form-check-label" for="menipis">Stok menipis</label>
      </div>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-prs-blue btn-sm"><i class="ti ti-filter"></i> Terapkan Filter</button>
      <a href="{{ route('persediaan.admin.stok-akhir.export', request()->query()) }}" class="btn btn-prs-green btn-sm">
        <i class="ti ti-file-spreadsheet"></i> Export Excel (ikut filter)
      </a>
      <a href="{{ route('persediaan.admin.stok-akhir.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </form>
</div>

<div class="pa-card">
  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr>
          <th>Kode Barang</th><th>Nama Barang</th><th>Unit</th>
          <th class="text-end">Stok Awal</th><th class="text-end">Barang Masuk</th>
          <th class="text-end">Barang Keluar</th><th class="text-end">Stok Akhir</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td><code>{{ $r['kode'] }}</code></td>
            <td>{{ $r['nama'] }}</td>
            <td>{{ $r['unit'] }}</td>
            <td class="text-end">{{ number_format($r['awal'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($r['masuk'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($r['keluar'], 0, ',', '.') }}</td>
            <td class="text-end fw-bold {{ $r['minim'] ? 'text-danger' : '' }}">{{ number_format($r['akhir'], 0, ',', '.') }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="text-muted mt-3 mb-0"><strong>Rumus:</strong> Stok Akhir = Stok Awal + Barang Masuk &minus; Barang Keluar</p>
</div>
@endsection

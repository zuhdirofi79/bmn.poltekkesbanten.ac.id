@extends('layouts.persediaan_admin')

@section('page_title', 'Pembelian Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <span class="prs-section-title mb-0">Daftar Pembelian</span>
  <div class="d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
      <i class="ti ti-file-upload"></i> Import Excel
    </button>
    <a href="{{ route('persediaan.admin.pembelian.import.template') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-download"></i> Download Template Excel
    </a>
    <a href="{{ route('persediaan.admin.pembelian.export', request()->query()) }}" class="btn btn-outline-success btn-sm">
      <i class="ti ti-file-spreadsheet"></i> Export Excel
    </a>
    <a href="{{ route('persediaan.admin.pembelian.create') }}" class="btn btn-prs-green btn-sm">
      <i class="ti ti-plus"></i> Input Pembelian
    </a>
  </div>
</div>

<div class="pa-card mt-0">
  @if($lockedUnit)
    <p class="text-muted mb-3">Unit kerja: <strong>{{ UnitKerja::label($lockedUnit) }}</strong></p>
  @else
    <form method="GET" action="{{ route('persediaan.admin.pembelian.index') }}" class="row g-2 align-items-end mb-3">
      <div class="col-md-4">
        <label class="form-label">Unit Kerja</label>
        <select name="unit_kerja" class="form-select form-select-sm">
          <option value="">Semua unit</option>
          @foreach($units as $u)
            <option value="{{ $u }}" @selected($filterUnit === $u)>{{ UnitKerja::label($u) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <button class="btn btn-prs-blue btn-sm" type="submit"><i class="ti ti-filter"></i> Filter</button>
        <a href="{{ route('persediaan.admin.pembelian.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  @endif

  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr><th>No. Faktur</th><th>Tanggal</th><th>Penyedia</th><th>Unit</th><th class="text-end">Total</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($pembelian as $row)
          <tr>
            <td><code>{{ $row->nomor_faktur }}</code></td>
            <td>{{ optional($row->tanggal)->format('d/m/Y') }}</td>
            <td>{{ $row->penyedia ?: '-' }}</td>
            <td>{{ $row->unit_kerja ?: '-' }}</td>
            <td class="text-end">Rp {{ number_format($row->total, 0, ',', '.') }}</td>
            <td class="text-end"><a href="{{ route('persediaan.admin.pembelian.show', $row->id) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-3">Belum ada pembelian.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $pembelian->links() }}</div>
</div>

@include('persediaan_admin.pembelian.partials.import-modal')
@endsection

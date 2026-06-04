@extends('layouts.persediaan_admin')

@section('page_title', 'Laporan Persediaan')

@php use App\Support\Persediaan\UnitKerja; use App\Models\Persediaan\PersediaanPermintaan as P; @endphp

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
      <label class="form-label">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Semua Status</option>
        @foreach(P::STATUS_LABEL as $val => $lbl)<option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ $lbl }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Dari</label>
      <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label">Sampai</label>
      <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-prs-blue btn-sm"><i class="ti ti-filter"></i> Terapkan</button>
      <a href="{{ route('persediaan.admin.laporan.export', request()->query()) }}" class="btn btn-prs-green btn-sm">
        <i class="ti ti-file-spreadsheet"></i> Export Excel (ikut filter)
      </a>
      <a href="{{ route('persediaan.admin.laporan.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </form>
</div>

<div class="pa-card">
  <div class="table-responsive">
    <table class="pa-table">
      <thead><tr><th>Nomor</th><th>Tanggal</th><th>Unit</th><th>Pemohon</th><th>Status</th><th class="text-end">Item</th></tr></thead>
      <tbody>
        @forelse($rows as $p)
          <tr>
            <td><code>{{ $p->nomor_permintaan }}</code></td>
            <td>{{ optional($p->tanggal_permintaan)->format('d-m-Y') }}</td>
            <td>{{ $p->unit_kerja }}</td>
            <td>{{ optional($p->pemohon)->nama ?? '-' }}</td>
            <td><span class="prs-badge {{ $p->status }}">{{ $p->statusLabel() }}</span></td>
            <td class="text-end">{{ $p->items()->count() }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection

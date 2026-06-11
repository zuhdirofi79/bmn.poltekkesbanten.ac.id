@extends('layouts.persediaan_admin')

@section('page_title', 'Permintaan Barang')

@php use App\Support\Persediaan\UnitKerja; use App\Models\Persediaan\PersediaanPermintaan as P; @endphp

@section('content')
<div class="pa-card mt-0">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Unit Kerja</label>
      @if(!empty($lockedUnit))
        <input type="hidden" name="unit" value="{{ $lockedUnit }}">
        <select class="form-select form-select-sm" disabled>
          <option selected>{{ UnitKerja::label($lockedUnit) }}</option>
        </select>
      @else
        <select name="unit" class="form-select form-select-sm">
          <option value="">Semua Unit</option>
          @foreach($units as $u)
            <option value="{{ $u }}" @selected(request('unit') === $u)>{{ UnitKerja::label($u) }}</option>
          @endforeach
        </select>
      @endif
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Semua Status</option>
        @foreach(P::STATUS_LABEL as $val => $lbl)
          <option value="{{ $val }}" @selected(request('status') === $val)>{{ $lbl }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Cari Nomor</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="PRM-...">
    </div>
    <div class="col-md-2">
      <button class="btn btn-prs-blue btn-sm w-100"><i class="ti ti-filter"></i> Filter</button>
    </div>
  </form>
</div>

<div class="pa-card">
  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr><th>Nomor</th><th>Pemohon</th><th>Unit</th><th>Tanggal</th><th>Item</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($permintaan as $p)
          <tr>
            <td><code>{{ $p->nomor_permintaan }}</code></td>
            <td>{{ optional($p->pemohon)->nama ?? '-' }}</td>
            <td>{{ $p->unit_kerja }}</td>
            <td>{{ optional($p->tanggal_permintaan)->format('d-m-Y') }}</td>
            <td>{{ $p->items()->count() }}</td>
            <td><span class="prs-badge {{ $p->status }}">{{ $p->statusLabel() }}</span></td>
            <td class="text-end"><a href="{{ route('persediaan.admin.permintaan.show', $p->id) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-3">Tidak ada permintaan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $permintaan->links() }}</div>
</div>
@endsection

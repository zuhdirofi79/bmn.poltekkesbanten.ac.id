@extends('layouts.thema')

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title fw-semibold mb-0">Mutasi Lokasi Aset</h5>
        <a href="{{ route('bmn.mutasi.create', ['aset_id' => $asetId]) }}" class="btn btn-primary btn-sm">
          <i class="ti ti-plus"></i> Tambah Mutasi
        </a>
      </div>
      <hr>

      <form method="get" action="{{ route('bmn.mutasi.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
          <label class="form-label">Filter Aset ID</label>
          <input type="number" name="aset_id" class="form-control form-control-sm" value="{{ $asetId }}" placeholder="Opsional">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-outline-primary btn-sm me-2">Terapkan</button>
          <a href="{{ route('bmn.mutasi.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
          <thead>
            <tr>
              <th width="40">No</th>
              <th>Tanggal</th>
              <th>Aset</th>
              <th>Dari Lokasi</th>
              <th>Ke Lokasi</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
            @forelse($mutasi as $m)
              <tr>
                <td>{{ $mutasi->firstItem() + $loop->index }}</td>
                <td>{{ optional($m->tanggal_mutasi)->format('Y-m-d') }}</td>
                <td>
                  @if($m->aset)
                    {{ $m->aset->kode_barang }} — {{ $m->aset->nama_barang }}
                  @else
                    -
                  @endif
                </td>
                <td>{{ optional($m->dariLokasi)->nama_lokasi ?? '-' }}</td>
                <td>{{ optional($m->keLokasi)->nama_lokasi ?? '-' }}</td>
                <td>{{ $m->keterangan }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted">Belum ada mutasi.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-2">
        {{ $mutasi->links() }}
      </div>
    </div>
  </div>
</div>
@endsection

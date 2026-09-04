@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Permintaan BHP')

@section('content')
<div class="pa-card">
  <form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
      <label class="form-label">Cari</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nomor / nama pengambil">
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="">Semua</option>
        @foreach(['pending','disetujui','siap_diambil','sudah_diambil','ditolak'] as $s)
          <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-prs-blue w-100">Filter</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr>
          <th>Nomor</th><th>Barang</th><th>Pengambil</th><th>WA</th><th>Jumlah</th><th>Tanggal</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($permintaan as $p)
          <tr>
            <td><code>{{ $p->nomor_permintaan }}</code></td>
            <td>{{ $p->barang?->nama_barang }}</td>
            <td>{{ $p->nama_pengambil }}</td>
            <td>{{ $p->no_wa_pengambil }}</td>
            <td>{{ $p->jumlah_diminta }} {{ $p->barang?->satuan }}</td>
            <td>{{ optional($p->tanggal_pengambilan)->format('d-m-Y') }}</td>
            <td><span class="prs-badge {{ $p->status }}">{{ $p->statusLabel() }}</span></td>
            <td class="text-end">
              <a href="{{ route('bhp.admin.permintaan.show', $p->id) }}" class="btn btn-outline-primary btn-sm">Detail</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-3">Belum ada permintaan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="bhp-pagination">
    {{ $permintaan->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection

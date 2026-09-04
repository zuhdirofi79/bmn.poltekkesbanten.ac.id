@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Pengambilan Barang')

@section('content')
<div class="pa-card">
  <form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-md-6">
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor / nama pengambil">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-prs-blue w-100">Cari</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr><th>Nomor</th><th>Barang</th><th>Pengambil</th><th>Jumlah</th><th>Disetujui</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($permintaan as $p)
          <tr>
            <td><code>{{ $p->nomor_permintaan }}</code></td>
            <td>{{ $p->barang?->nama_barang }}</td>
            <td>{{ $p->nama_pengambil }}</td>
            <td>{{ $p->jumlah_diminta }} {{ $p->barang?->satuan }}</td>
            <td>{{ optional($p->verified_at)->format('d-m-Y H:i') }}</td>
            <td class="text-end">
              <a href="{{ route('bhp.admin.pengambilan.show', $p->id) }}" class="btn btn-prs-green btn-sm">Tindak Lanjut</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada permintaan siap diambil.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="bhp-pagination">
    {{ $permintaan->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection

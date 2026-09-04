@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Stock Opname')

@section('content')
<div class="pa-card">
  <div class="d-flex justify-content-between mb-3">
    <h2 class="mb-0">Riwayat Stock Opname</h2>
    <a href="{{ route('bhp.admin.opname.create') }}" class="btn btn-prs-green btn-sm">Buat Opname Baru</a>
  </div>
  <div class="table-responsive">
    <table class="pa-table">
      <thead><tr><th>Tanggal</th><th>Catatan</th><th>Jumlah Barang</th><th></th></tr></thead>
      <tbody>
        @forelse($opname as $o)
          <tr>
            <td>{{ optional($o->tanggal_opname)->format('d-m-Y') }}</td>
            <td>{{ $o->catatan ?: '-' }}</td>
            <td>{{ $o->details_count }}</td>
            <td class="text-end">
              <a href="{{ route('bhp.admin.opname.show', $o->id) }}" class="btn btn-outline-primary btn-sm">Detail</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-3">Belum ada opname.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="bhp-pagination">
    {{ $opname->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection

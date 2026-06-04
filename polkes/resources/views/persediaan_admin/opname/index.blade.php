@extends('layouts.persediaan_admin')

@section('page_title', 'Stock Opname')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="prs-section-title mb-0">Riwayat Stock Opname</span>
  <a href="{{ route('persediaan.admin.opname.create') }}" class="btn btn-prs-green btn-sm">
    <i class="ti ti-plus"></i> Opname Baru
  </a>
</div>

<div class="pa-card mt-0">
  <div class="table-responsive">
    <table class="pa-table">
      <thead><tr><th>Tanggal</th><th>Unit Kerja</th><th>Jumlah Item</th><th>Catatan</th><th></th></tr></thead>
      <tbody>
        @forelse($opname as $o)
          <tr>
            <td>{{ optional($o->tanggal_opname)->format('d-m-Y') }}</td>
            <td>{{ $o->unit_kerja }}</td>
            <td>{{ $o->items_count }}</td>
            <td>{{ \Illuminate\Support\Str::limit($o->catatan, 40) ?: '-' }}</td>
            <td class="text-end"><a href="{{ route('persediaan.admin.opname.show', $o->id) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-3">Belum ada opname.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $opname->links() }}</div>
</div>
@endsection

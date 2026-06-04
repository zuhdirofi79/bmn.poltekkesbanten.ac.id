@extends('layouts.persediaan_admin')

@section('page_title', 'Pembelian Barang')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="prs-section-title mb-0">Daftar Pembelian</span>
  <a href="{{ route('persediaan.admin.pembelian.create') }}" class="btn btn-prs-green btn-sm">
    <i class="ti ti-plus"></i> Input Pembelian
  </a>
</div>

<div class="pa-card mt-0">
  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr><th>No. Faktur</th><th>Tanggal</th><th>Penyedia</th><th>Unit</th><th class="text-end">Total</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($pembelian as $row)
          <tr>
            <td><code>{{ $row->nomor_faktur }}</code></td>
            <td>{{ optional($row->tanggal)->format('d-m-Y') }}</td>
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
@endsection

@extends('layouts.persediaan_admin')

@section('page_title', 'Preview Import Pembelian')

@php
  $rows = $preview['rows'] ?? [];
  $messages = array_slice($preview['messages'] ?? [], 0, 80);
  $extra = count($preview['messages'] ?? []) - count($messages);
  $groups = $preview['groups'] ?? [];
  $canImport = ! empty($preview['can_import']);
  $badge = [
    'MASTER' => 'bg-success',
    'MANUAL' => 'bg-secondary',
    'ERROR' => 'bg-danger',
    'DUPLIKAT' => 'bg-warning text-dark',
  ];
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <span class="prs-section-title mb-0">Preview Import Pembelian</span>
  <a href="{{ route('persediaan.admin.pembelian.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
</div>

<div class="pa-card mt-0">
  <p class="mb-2">
    {{ count($groups) }} transaksi siap disimpan
    · {{ count($rows) }} baris
    · {{ count($preview['messages'] ?? []) }} catatan
  </p>
  <p class="small text-muted mb-0">Periksa status setiap baris. Barang MASTER menambah stok. Barang MANUAL tersimpan tanpa mengubah stok master. No. Faktur yang sama digabung menjadi satu transaksi.</p>
</div>

@if($messages !== [])
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($messages as $message)
        <li>{{ $message }}</li>
      @endforeach
    </ul>
    @if($extra > 0)
      <div class="mt-2">Dan {{ $extra }} catatan lainnya.</div>
    @endif
  </div>
@endif

<div class="pa-card">
  <div class="table-responsive">
    <table class="prs-table">
      <thead>
        <tr>
          <th>Baris</th>
          <th>No. Faktur</th>
          <th>Tanggal</th>
          <th>Penyedia</th>
          <th>Unit</th>
          <th>Kode</th>
          <th>Nama Barang</th>
          <th>Satuan</th>
          <th class="text-end">Harga</th>
          <th class="text-end">Jumlah</th>
          <th class="text-end">Subtotal</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          <tr>
            <td>{{ $row['line'] }}</td>
            <td><code>{{ $row['no_faktur'] ?: '-' }}</code></td>
            <td>{{ $row['tanggal'] ?: '-' }}</td>
            <td>{{ $row['penyedia'] ?: '-' }}</td>
            <td>{{ $row['unit_label'] ?: '-' }}</td>
            <td>{{ $row['kode_barang'] ?: '-' }}</td>
            <td>{{ $row['nama_barang'] ?: '-' }}</td>
            <td>{{ $row['satuan'] ?: '-' }}</td>
            <td class="text-end">{{ $row['harga_satuan'] === null ? '-' : number_format((float) $row['harga_satuan'], 0, ',', '.') }}</td>
            <td class="text-end">{{ $row['jumlah'] === null ? '-' : number_format((int) $row['jumlah'], 0, ',', '.') }}</td>
            <td class="text-end">{{ $row['subtotal'] === null ? '-' : number_format((float) $row['subtotal'], 0, ',', '.') }}</td>
            <td>
              <span class="badge {{ $badge[$row['status']] ?? 'bg-light text-dark' }}">{{ $row['status'] }}</span>
              @if(!empty($row['error']))
                <div class="small text-danger mt-1">{{ $row['error'] }}</div>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="12" class="text-center text-muted py-3">Tidak ada baris.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<form method="POST" action="{{ route('persediaan.admin.pembelian.import.confirm') }}" id="confirmImportForm">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <button type="submit" class="btn btn-prs-green" id="confirmImportBtn" @disabled(! $canImport)>
    Simpan / Import
  </button>
  @unless($canImport)
    <span class="text-danger ms-2">Perbaiki error pada file Excel sebelum import.</span>
  @endunless
</form>
@endsection

@push('scripts')
<script>
document.getElementById('confirmImportForm').addEventListener('submit', function (e) {
  var btn = document.getElementById('confirmImportBtn');
  if (btn.disabled) {
    e.preventDefault();
    return;
  }
  btn.disabled = true;
  btn.textContent = 'Menyimpan...';
});
</script>
@endpush

@extends('layouts.persediaan_admin')

@section('page_title', 'Sampah Master Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <span class="prs-section-title mb-0">Sampah Master Barang Persediaan</span>
    <p class="text-muted small mb-0 mt-1">Barang yang dihapus sementara akan tersimpan di sini dan dapat dipulihkan atau dihapus permanen.</p>
  </div>
  <a href="{{ route('persediaan.admin.master-barang.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="ti ti-arrow-left"></i> Kembali ke Master Data
  </a>
</div>

<div class="pa-card mt-0">
  <form method="GET" action="{{ route('persediaan.admin.master-barang.trash') }}" class="row g-2 align-items-end">
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <div class="col-md-4">
      <label class="form-label">Unit Kerja</label>
      <select name="unit" class="form-select form-select-sm">
        <option value="">Semua Unit</option>
        @foreach($units as $u)
          <option value="{{ $u }}" @selected(request('unit') === $u)>{{ UnitKerja::label($u) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label">Kode / Nama</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Pencarian kode atau nama...">
    </div>
    <div class="col-md-3">
      <button class="btn btn-prs-blue btn-sm w-100"><i class="ti ti-filter"></i> Filter</button>
    </div>
  </form>
</div>

<div class="pa-card">
  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <div class="small text-muted d-flex flex-wrap align-items-center gap-2">
      <form method="GET" action="{{ route('persediaan.admin.master-barang.trash') }}" class="d-flex align-items-center gap-2 mb-0">
        @foreach(request()->except(['per_page', 'page']) as $key => $val)
          @if(is_array($val))
            @foreach($val as $fv)
              <input type="hidden" name="{{ $key }}[]" value="{{ $fv }}">
            @endforeach
          @elseif($val !== null && $val !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
          @endif
        @endforeach
        <label for="perPageTrash" class="mb-0">Show</label>
        <select name="per_page" id="perPageTrash" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
          @foreach($perPageOptions as $option)
            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
          @endforeach
        </select>
        <span>rows</span>
      </form>
      <span><span id="trashSelectedCount">0</span> barang dipilih</span>
    </div>
  </div>

  <div id="trashBulkBar" class="pa-bulk-bar" hidden>
    <strong id="trashBulkLabel">0 barang dipilih</strong>
    <button type="button" class="btn btn-outline-success btn-sm js-pa-bulk"
            data-checkbox=".persediaan-trash-checkbox"
            data-url="{{ route('persediaan.admin.master-barang.trash.bulk-restore') }}"
            data-method="POST"
            data-tone="warn"
            data-title="Pulihkan {n} Barang?"
            data-message="Barang yang dipilih akan dikembalikan ke daftar aktif. Kode yang sama dengan barang lain tetap diperbolehkan."
            data-confirm="Ya, Pulihkan"
            data-busy-label="Memulihkan...">Pulihkan Terpilih</button>
    <button type="button" class="btn btn-danger btn-sm js-pa-bulk"
            data-checkbox=".persediaan-trash-checkbox"
            data-url="{{ route('persediaan.admin.master-barang.trash.bulk-force-delete') }}"
            data-method="POST"
            data-tone="danger"
            data-title="Hapus permanen {n} barang terpilih?"
            data-message="Tindakan ini tidak dapat dibatalkan. Jika satu barang masih memiliki histori transaksi, seluruh pilihan tidak dihapus."
            data-confirm="Ya, Hapus Permanen">Hapus Permanen Terpilih</button>
  </div>

  <div class="table-responsive">
    <table class="pa-table mb-0">
      <thead>
        <tr>
          <th width="40" class="text-center">
            <input type="checkbox" id="checkAllTrash" aria-label="Pilih semua barang sampah di halaman ini">
          </th>
          <th>Kode</th>
          <th>Nama Barang</th>
          <th>Unit</th>
          <th>Satuan</th>
          <th class="text-end">Stok Awal</th>
          <th class="text-end">Harga</th>
          <th>Tanggal Dihapus</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($barang as $b)
          <tr>
            <td class="text-center">
              <input type="checkbox" class="persediaan-trash-checkbox" value="{{ $b->id }}" aria-label="Pilih {{ $b->nama_barang }}">
            </td>
            <td><code>{{ $b->kode_barang }}</code></td>
            <td>{{ $b->nama_barang }}</td>
            <td>{{ $b->unit_kerja }}</td>
            <td>{{ $b->satuan }}</td>
            <td class="text-end">{{ number_format($b->stok_awal, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($b->harga_satuan, 0, ',', '.') }}</td>
            <td>{{ optional($b->deleted_at)->format('d-m-Y H:i') }}</td>
            <td class="text-end">
              <div class="pa-row-actions">
                <button type="button" class="btn btn-outline-success btn-sm js-pa-action"
                        data-url="{{ route('persediaan.admin.master-barang.restore', $b->id) }}"
                        data-method="POST" data-tone="warn" data-title="Pulihkan Barang"
                        data-message="Barang akan dikembalikan ke daftar aktif. Kode yang sama dengan barang lain tetap diperbolehkan."
                        data-confirm="Ya, Pulihkan" data-busy-label="Memulihkan...">Pulihkan</button>
                <button type="button" class="btn btn-outline-danger btn-sm js-pa-action"
                        data-url="{{ route('persediaan.admin.master-barang.force-destroy', $b->id) }}"
                        data-method="DELETE" data-tone="danger" data-title="Hapus Barang Permanen?"
                        data-subject="{{ $b->nama_barang }}&#10;{{ $b->kode_barang }}&#10;{{ $b->unit_kerja }}"
                        data-message="Tindakan ini tidak dapat dibatalkan. Barang akan dihapus permanen dari master data. Barang yang masih memiliki histori transaksi tidak akan dihapus."
                        data-confirm="Ya, Hapus Permanen">Hapus Permanen</button>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-3">Sampah kosong.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $barang->links('persediaan_admin.master-barang.pagination') }}</div>
</div>

@include('persediaan_admin._delete_modal')
@endsection

@push('scripts')
<script>
(function () {
  var checkAll = document.getElementById('checkAllTrash');
  var counter = document.getElementById('trashSelectedCount');
  var bulkBar = document.getElementById('trashBulkBar');
  var bulkLabel = document.getElementById('trashBulkLabel');

  function rows() {
    return document.querySelectorAll('.persediaan-trash-checkbox');
  }

  function update() {
    var checked = document.querySelectorAll('.persediaan-trash-checkbox:checked');
    var total = rows().length;
    if (counter) counter.textContent = String(checked.length);
    if (bulkLabel) bulkLabel.textContent = checked.length + ' barang dipilih';
    if (bulkBar) bulkBar.hidden = checked.length === 0;
    if (checkAll) {
      checkAll.checked = total > 0 && checked.length === total;
      checkAll.indeterminate = checked.length > 0 && checked.length < total;
    }
  }

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      var state = this.checked;
      rows().forEach(function (el) { el.checked = state; });
      update();
    });
  }
  rows().forEach(function (el) { el.addEventListener('change', update); });
  update();
})();
</script>
@endpush

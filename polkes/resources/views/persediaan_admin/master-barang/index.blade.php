@extends('layouts.persediaan_admin')

@section('page_title', 'Master Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <span class="prs-section-title mb-0">Master Barang Persediaan</span>
  <div class="d-flex flex-wrap gap-2">
    <a href="{{ route('persediaan.admin.master-barang.template') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-download"></i> Download Template Excel
    </a>
    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
      <i class="ti ti-file-spreadsheet"></i> Import Excel
    </button>
    @if($barang->total() === 0)
      <button type="button" class="btn btn-outline-success btn-sm" disabled>
        <i class="ti ti-file-export"></i> Export Excel
      </button>
    @else
      <a href="{{ route('persediaan.admin.master-barang.export', request()->except('page')) }}"
         class="btn btn-outline-success btn-sm" id="exportMasterBarangBtn">
        <i class="ti ti-file-export"></i> Export Excel
      </a>
    @endif
    <button class="btn btn-prs-green btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="ti ti-plus"></i> Tambah Barang
    </button>
    <form method="POST" action="{{ route('persediaan.admin.master-barang.generate-barcodes') }}" class="d-inline" id="generateAllBarcodeForm">
      @csrf
      <input type="hidden" name="scope" value="all">
      <button class="btn btn-outline-primary btn-sm" type="submit">
        <i class="ti ti-barcode"></i> Generate Semua Barcode
      </button>
    </form>
    <a href="{{ route('persediaan.admin.master-barang.print-barcodes') }}" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-printer"></i> Print Semua Barcode
    </a>
  </div>
</div>

@if(session('master_barang_import_errors') && is_array(session('master_barang_import_errors')) && count(session('master_barang_import_errors')) > 0)
  <div class="pa-card mt-0 mb-3">
    <h6 class="fw-semibold mb-2 {{ session('success') ? 'text-warning' : 'text-danger' }}">
      {{ session('success') ? 'Baris yang dilewati saat import' : 'Error import Excel' }}
    </h6>
    <div class="table-responsive">
      <table class="pa-table table-sm">
        <thead>
          <tr><th>Baris</th><th>Kolom</th><th>Pesan error</th></tr>
        </thead>
        <tbody>
          @foreach(session('master_barang_import_errors') as $err)
            <tr>
              <td>{{ $err['baris'] ?? '-' }}</td>
              <td><code>{{ $err['kolom'] ?? '-' }}</code></td>
              <td>{{ $err['pesan'] ?? '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<div class="pa-card mt-0">
  <form method="GET" id="globalFilterForm" class="row g-2 align-items-end">
    @foreach($filters as $fKey => $fVal)
      @if(is_array($fVal))
        @foreach($fVal as $fv)
          <input type="hidden" name="{{ $fKey }}[]" value="{{ $fv }}">
        @endforeach
      @elseif($fKey !== 'unit' && $fKey !== 'q' && $fVal !== null && $fVal !== '')
        <input type="hidden" name="{{ $fKey }}" value="{{ $fVal }}">
      @endif
    @endforeach
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <div class="col-md-3">
      <label class="form-label">Unit Kerja</label>
      <select name="unit" class="form-select form-select-sm">
        <option value="">Semua Unit</option>
        @foreach($units as $u)<option value="{{ $u }}" @selected(($filters['unit'] ?? '') === $u)>{{ UnitKerja::label($u) }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Kode / Nama</label>
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Pencarian cepat...">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Tampilan</label>
      <select name="lifecycle" class="form-select form-select-sm">
        <option value="aktif" @selected(($lifecycle ?? 'aktif') === 'aktif')>Data Aktif</option>
        <option value="sampah" @selected(($lifecycle ?? '') === 'sampah')>Sampah</option>
        <option value="semua" @selected(($lifecycle ?? '') === 'semua')>Semua</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <button class="btn btn-prs-blue btn-sm w-100"><i class="ti ti-filter"></i> Filter</button>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <a href="{{ route('persediaan.admin.master-barang.index') }}" class="btn btn-outline-secondary btn-sm flex-grow-1">
        <i class="ti ti-refresh"></i> Reset Semua Filter
      </a>
    </div>
  </form>
  @if(!empty($hasColumnFilter))
    <div class="mt-2">
      <span class="badge bg-light text-primary border">
        <i class="ti ti-filter"></i> Filter per kolom aktif
      </span>
    </div>
  @endif
</div>

<div class="pa-card">
  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <div class="small text-muted d-flex flex-wrap align-items-center gap-2">
      <form method="GET" action="{{ route('persediaan.admin.master-barang.index') }}" class="d-flex align-items-center gap-2 mb-0">
        @foreach(request()->except(['per_page', 'page']) as $key => $val)
          @if(is_array($val))
            @foreach($val as $fv)
              <input type="hidden" name="{{ $key }}[]" value="{{ $fv }}">
            @endforeach
          @elseif($val !== null && $val !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
          @endif
        @endforeach
        <label for="perPageSelect" class="mb-0">Show</label>
        <select name="per_page" id="perPageSelect" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
          @foreach($perPageOptions as $option)
            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
          @endforeach
        </select>
        <span>rows</span>
      </form>
      <span id="barangSelectedCount">0</span> barang dipilih
      @if($barang->total() > 0)
        <span class="text-muted">
          · Menampilkan {{ $barang->firstItem() }}&ndash;{{ $barang->lastItem() }}
          dari {{ number_format($barang->total(), 0, ',', '.') }} barang
        </span>
      @endif
    </div>
    <div class="d-flex flex-wrap gap-2">
      <form method="POST" action="{{ route('persediaan.admin.master-barang.generate-barcodes') }}" id="generateSelectedBarcodeForm">
        @csrf
        <input type="hidden" name="scope" value="selected">
        <div id="generateSelectedIds"></div>
        <button type="submit" class="btn btn-outline-primary btn-sm" id="btnGenerateSelected" disabled>
          Generate Barcode Terpilih
        </button>
      </form>
      <form method="GET" action="{{ route('persediaan.admin.master-barang.print-barcodes') }}" target="_blank" id="printSelectedBarcodeForm">
        <div id="printSelectedIds"></div>
        <button type="submit" class="btn btn-outline-secondary btn-sm" id="btnPrintSelected" disabled>
          Print Barcode Terpilih
        </button>
      </form>
    </div>
  </div>

  <div id="barangBulkBar" class="pa-bulk-bar" hidden>
    <strong id="barangBulkLabel">0 barang dipilih</strong>
    @if(($lifecycle ?? 'aktif') !== 'sampah')
      <button type="button" class="btn btn-warning btn-sm js-pa-bulk"
              data-checkbox=".persediaan-barang-checkbox"
              data-url="{{ route('persediaan.admin.master-barang.bulk-delete') }}"
              data-method="POST"
              data-tone="warn"
              data-title="Soft Delete {n} Barang?"
              data-message="Barang yang dipilih akan disembunyikan dari daftar aktif, tetapi datanya masih tersimpan dan dapat dipulihkan."
              data-confirm="Ya, Soft Delete">Soft Delete Terpilih</button>
    @endif
    @if(($lifecycle ?? 'aktif') !== 'aktif')
      <button type="button" class="btn btn-outline-success btn-sm js-pa-bulk"
              data-checkbox=".persediaan-barang-checkbox"
              data-url="{{ route('persediaan.admin.master-barang.bulk-restore') }}"
              data-method="POST"
              data-tone="warn"
              data-title="Pulihkan {n} Barang?"
              data-message="Barang yang dipilih akan dikembalikan ke daftar aktif."
              data-confirm="Ya, Pulihkan"
              data-busy-label="Memulihkan...">Pulihkan Terpilih</button>
    @endif
    <button type="button" class="btn btn-danger btn-sm js-pa-bulk"
            data-checkbox=".persediaan-barang-checkbox"
            data-url="{{ route('persediaan.admin.master-barang.bulk-force-delete') }}"
            data-method="POST"
            data-tone="danger"
            data-title="Hapus Permanen {n} Barang?"
            data-message="Tindakan ini tidak dapat dibatalkan. Barang yang sudah memiliki histori transaksi tidak akan dihapus."
            data-confirm="Ya, Hapus Permanen">Hapus Permanen Terpilih</button>
  </div>

  <div class="pa-table-filter-wrap">
    <table class="pa-table pa-table--filterable mb-0">
      <thead>
        <tr>
          <th width="40" class="text-center">
            <input type="checkbox" id="checkAllBarang" aria-label="Pilih semua barang di halaman ini">
          </th>
          @include('persediaan_admin.master-barang._column_filter', [
            'column' => 'kode',
            'param' => 'kode_barang',
            'label' => 'Kode',
            'options' => $filterOptions['kode_barang'] ?? [],
            'active' => $filters['kode_barang'] ?? [],
          ])
          @include('persediaan_admin.master-barang._column_filter', [
            'column' => 'nama',
            'param' => 'nama_barang',
            'label' => 'Nama',
            'options' => $filterOptions['nama_barang'] ?? [],
            'active' => $filters['nama_barang'] ?? [],
          ])
          @include('persediaan_admin.master-barang._column_filter', [
            'column' => 'unit',
            'param' => 'unit_kerja',
            'label' => 'Unit',
            'options' => $filterOptions['unit_kerja'] ?? [],
            'active' => $filters['unit_kerja'] ?? [],
          ])
          @include('persediaan_admin.master-barang._column_filter', [
            'column' => 'satuan',
            'param' => 'satuan',
            'label' => 'Satuan',
            'options' => $filterOptions['satuan'] ?? [],
            'active' => $filters['satuan'] ?? [],
          ])
          @include('persediaan_admin.master-barang._column_filter', [
            'column' => 'stok_awal',
            'param' => 'stok_awal',
            'label' => 'Stok Awal',
            'options' => $filterOptions['stok_awal'] ?? [],
            'active' => $filters['stok_awal'] ?? [],
            'align' => 'end',
          ])
          @include('persediaan_admin.master-barang._column_filter', [
            'column' => 'harga_satuan',
            'param' => 'harga_satuan',
            'label' => 'Harga',
            'options' => $filterOptions['harga_satuan'] ?? [],
            'active' => $filters['harga_satuan'] ?? [],
            'align' => 'end',
          ])
          @include('persediaan_admin.master-barang._column_filter', [
            'column' => 'status',
            'param' => 'status',
            'label' => 'Status',
            'options' => $filterOptions['status'] ?? [],
            'active' => $filters['status'] ?? [],
          ])
          <th>Rak</th>
          <th>Barcode</th>
          <th width="90"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($barang as $b)
          <tr>
            <td class="text-center">
              <input type="checkbox"
                     class="persediaan-barang-checkbox"
                     value="{{ $b->id }}"
                     aria-label="Pilih {{ $b->nama_barang }}">
            </td>
            <td><code>{{ $b->kode_barang }}</code></td>
            <td>{{ $b->nama_barang }}</td>
            <td>{{ $b->unit_kerja }}</td>
            <td>{{ $b->satuan }}</td>
            <td class="text-end">{{ number_format($b->stok_awal, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($b->harga_satuan, 0, ',', '.') }}</td>
            <td>
              @if($b->trashed())<span class="prs-badge ditolak">Soft Deleted</span>@endif
              @if($b->is_active)<span class="prs-badge selesai">Aktif</span>@else<span class="prs-badge ditolak">Nonaktif</span>@endif
            </td>
            <td>{{ optional(optional($b->rakAssignment)->rak)->kode_rak ?: '-' }}</td>
            <td>
              @if(!empty($barcodeUrls[$b->id]))
                <span class="prs-badge disetujui">Ada</span>
              @else
                <span class="text-muted">Belum</span>
              @endif
            </td>
            <td class="text-end">
              <div class="pa-row-actions">
                @unless($b->trashed())
                  <button class="btn btn-outline-primary btn-sm"
                          data-bs-toggle="modal" data-bs-target="#editModal"
                          data-id="{{ $b->id }}" data-kode="{{ $b->kode_barang }}" data-nama="{{ $b->nama_barang }}"
                          data-unit="{{ $b->unit_kerja }}" data-satuan="{{ $b->satuan }}"
                          data-awal="{{ $b->stok_awal }}" data-min="{{ $b->stok_minimum }}"
                          data-harga="{{ $b->harga_satuan }}" data-active="{{ $b->is_active ? 1 : 0 }}">
                    Edit
                  </button>
                @endunless
                <div class="dropdown">
                  <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown"
                          data-bs-popper-config='{"strategy":"fixed"}' aria-label="Aksi {{ $b->nama_barang }}">
                    <i class="ti ti-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    @unless($b->trashed())
                      <li><button type="button" class="dropdown-item js-pa-action"
                                  data-url="{{ route('persediaan.admin.master-barang.deactivate', $b->id) }}"
                                  data-method="POST" data-tone="warn" data-title="Nonaktifkan Barang"
                                  data-subject="{{ $b->kode_barang }} — {{ $b->nama_barang }}"
                                  data-message="Status barang menjadi nonaktif. Ini bukan soft delete. Data tetap tampil di daftar dan histori stok tidak berubah."
                                  data-confirm="Ya, Nonaktifkan" data-busy-label="Menyimpan...">Nonaktifkan</button></li>
                      <li><button type="button" class="dropdown-item js-pa-action"
                                  data-url="{{ route('persediaan.admin.master-barang.destroy', $b->id) }}"
                                  data-method="DELETE" data-tone="warn" data-title="Soft Delete Barang"
                                  data-subject="{{ $b->kode_barang }} — {{ $b->nama_barang }}"
                                  data-message="Barang ini akan disembunyikan dari daftar aktif, tetapi datanya masih tersimpan dan dapat dipulihkan jika diperlukan. Apakah Anda yakin ingin melakukan soft delete?"
                                  data-confirm="Ya, Soft Delete">Soft Delete</button></li>
                    @else
                      <li><button type="button" class="dropdown-item js-pa-action"
                                  data-url="{{ route('persediaan.admin.master-barang.restore', $b->id) }}"
                                  data-method="POST" data-tone="warn" data-title="Pulihkan Barang"
                                  data-subject="{{ $b->kode_barang }} — {{ $b->nama_barang }}"
                                  data-message="Barang akan dikembalikan ke daftar aktif."
                                  data-confirm="Ya, Pulihkan" data-busy-label="Memulihkan...">Restore</button></li>
                    @endunless
                    <li><button type="button" class="dropdown-item text-danger js-pa-action"
                                data-url="{{ route('persediaan.admin.master-barang.force-destroy', $b->id) }}"
                                data-method="DELETE" data-tone="danger" data-title="Hapus Permanen Barang"
                                data-subject="{{ $b->kode_barang }} — {{ $b->nama_barang }}"
                                data-message="PERINGATAN: Barang akan dihapus permanen dari database dan tindakan ini tidak dapat dibatalkan. Barang yang sudah memiliki histori transaksi tidak akan dihapus. Apakah Anda yakin ingin menghapus barang ini secara permanen?"
                                data-confirm="Hapus Permanen">Permanent Delete</button></li>
                  </ul>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="11" class="text-center text-muted py-3">Belum ada barang.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $barang->links('persediaan_admin.master-barang.pagination') }}</div>
</div>

{{-- Modal Import Excel --}}
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('persediaan.admin.master-barang.import') }}" enctype="multipart/form-data" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Import Banyak Barang Persediaan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">File Excel</label>
        <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
        <p class="text-muted small mt-2 mb-0">
          Gunakan template resmi. Baris dengan <code>kode_barang</code> + <code>unit_kerja</code> yang sama akan diupdate, bukan diduplikasi.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-blue">Upload &amp; Import</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Tambah --}}
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('persediaan.admin.master-barang.store') }}" class="modal-content">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Tambah Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        @include('persediaan_admin.master-barang._fields', ['units' => $units])
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-green">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit --}}
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="editForm" class="modal-content">
      @csrf @method('PUT')
      <div class="modal-header"><h5 class="modal-title">Edit Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        @include('persediaan_admin.master-barang._fields', ['units' => $units, 'edit' => true])
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-blue">Perbarui</button>
      </div>
    </form>
  </div>
</div>

@include('persediaan_admin._delete_modal')
@endsection

@push('scripts')
<script>
(function () {
  /* ---------- Edit modal ---------- */
  var editModal = document.getElementById('editModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (ev) {
      var b = ev.relatedTarget;
      var f = document.getElementById('editForm');
      f.action = "{{ url('persediaan/master-barang') }}/" + b.dataset.id;
      f.querySelector('[name=kode_barang]').value = b.dataset.kode;
      f.querySelector('[name=nama_barang]').value = b.dataset.nama;
      f.querySelector('[name=unit_kerja]').value = b.dataset.unit;
      f.querySelector('[name=satuan]').value = b.dataset.satuan;
      f.querySelector('[name=stok_awal]').value = b.dataset.awal;
      f.querySelector('[name=stok_minimum]').value = b.dataset.min;
      f.querySelector('[name=harga_satuan]').value = b.dataset.harga;
      var act = f.querySelector('[name=is_active]');
      if (act) act.checked = b.dataset.active === '1';
    });
  }

  /* ---------- Checkbox pilih barang ---------- */
  var checkAll = document.getElementById('checkAllBarang');
  var counter = document.getElementById('barangSelectedCount');

  function rowChecks() {
    return document.querySelectorAll('.persediaan-barang-checkbox');
  }

  function updateSelection() {
    var checked = document.querySelectorAll('.persediaan-barang-checkbox:checked');
    var total = rowChecks().length;
    if (counter) counter.textContent = String(checked.length);
    if (checkAll) {
      checkAll.checked = total > 0 && checked.length === total;
      checkAll.indeterminate = checked.length > 0 && checked.length < total;
    }
    var genWrap = document.getElementById('generateSelectedIds');
    var printWrap = document.getElementById('printSelectedIds');
    if (genWrap) genWrap.innerHTML = '';
    if (printWrap) printWrap.innerHTML = '';
    checked.forEach(function (el) {
      if (genWrap) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = 'ids[]'; h.value = el.value;
        genWrap.appendChild(h);
      }
      if (printWrap) {
        var p = document.createElement('input');
        p.type = 'hidden'; p.name = 'ids[]'; p.value = el.value;
        printWrap.appendChild(p);
      }
    });
    var bulkBar = document.getElementById('barangBulkBar');
    if (bulkBar) {
      bulkBar.hidden = checked.length === 0;
    }
    var bulkLabel = document.getElementById('barangBulkLabel');
    if (bulkLabel) bulkLabel.textContent = checked.length + ' barang dipilih';
    var disabled = checked.length === 0;
    var btnG = document.getElementById('btnGenerateSelected');
    var btnP = document.getElementById('btnPrintSelected');
    if (btnG) btnG.disabled = disabled;
    if (btnP) btnP.disabled = disabled;
  }

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      var state = this.checked;
      rowChecks().forEach(function (el) { el.checked = state; });
      updateSelection();
    });
  }

  rowChecks().forEach(function (el) {
    el.addEventListener('change', updateSelection);
  });
  updateSelection();

  /* ---------- Filter per kolom (Excel-style) ---------- */
  var COLUMN_PARAMS = [
    'kode_barang', 'nama_barang', 'unit_kerja', 'satuan',
    'stok_awal', 'harga_satuan', 'status'
  ];
  var openDropdown = null;

  function getArrayParam(params, key) {
    var bracket = params.getAll(key + '[]');
    if (bracket.length) return bracket;
    var indexed = [];
    params.forEach(function (val, k) {
      if (k === key || (k.indexOf(key + '[') === 0 && k.charAt(key.length) === '[')) {
        indexed.push(val);
      }
    });
    return indexed;
  }

  function readCurrentParams() {
    var params = new URLSearchParams(window.location.search);
    var out = {};
    COLUMN_PARAMS.forEach(function (key) {
      out[key] = getArrayParam(params, key);
    });
    if (params.get('unit')) out.unit = params.get('unit');
    if (params.get('q')) out.q = params.get('q');
    if (params.get('per_page')) out.per_page = params.get('per_page');
    if (params.get('lifecycle')) out.lifecycle = params.get('lifecycle');
    return out;
  }

  function buildUrl(overrides) {
    var current = readCurrentParams();
    var merged = Object.assign({}, current);
    if (overrides) {
      Object.keys(overrides).forEach(function (k) {
        merged[k] = overrides[k];
      });
    }
    var parts = [];

    if (merged.unit) parts.push('unit=' + encodeURIComponent(merged.unit));
    if (merged.q) parts.push('q=' + encodeURIComponent(merged.q));
    if (merged.per_page) parts.push('per_page=' + encodeURIComponent(merged.per_page));
    if (merged.lifecycle) parts.push('lifecycle=' + encodeURIComponent(merged.lifecycle));

    COLUMN_PARAMS.forEach(function (key) {
      var vals = merged[key];
      if (!vals || !vals.length) return;
      vals.forEach(function (v) {
        parts.push(encodeURIComponent(key) + '[]=' + encodeURIComponent(v));
      });
    });

    var qs = parts.join('&');
    return window.location.pathname + (qs ? '?' + qs : '');
  }

  function closeDropdown(dd) {
    if (!dd) return;
    dd.hidden = true;
    var btn = document.querySelector('.pa-col-filter-btn[data-col="' + dd.dataset.col + '"]');
    if (btn) btn.setAttribute('aria-expanded', 'false');
    if (openDropdown === dd) openDropdown = null;
  }

  function closeAllDropdowns(except) {
    document.querySelectorAll('.pa-col-filter-dropdown').forEach(function (dd) {
      if (dd !== except) closeDropdown(dd);
    });
  }

  function positionDropdown(btn, dd) {
    var rect = btn.getBoundingClientRect();
    var width = 240;
    var left = rect.left;
    if (left + width > window.innerWidth - 12) {
      left = Math.max(12, window.innerWidth - width - 12);
    }
    dd.style.top = (rect.bottom + 4) + 'px';
    dd.style.left = left + 'px';
    dd.style.width = width + 'px';
  }

  function syncSelectAll(dd) {
    var items = dd.querySelectorAll('.pa-col-filter-item:not(:disabled)');
    var visible = Array.prototype.filter.call(items, function (el) {
      return el.closest('.pa-col-filter-option').style.display !== 'none';
    });
    var checked = visible.filter(function (el) { return el.checked; });
    var checkAllEl = dd.querySelector('.pa-col-filter-check-all');
    if (!checkAllEl) return;
    checkAllEl.checked = visible.length > 0 && checked.length === visible.length;
    checkAllEl.indeterminate = checked.length > 0 && checked.length < visible.length;
  }

  document.querySelectorAll('.pa-col-filter-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var col = btn.dataset.col;
      var dd = document.querySelector('.pa-col-filter-dropdown[data-col="' + col + '"]');
      if (!dd) return;

      if (!dd.hidden && openDropdown === dd) {
        closeDropdown(dd);
        return;
      }

      closeAllDropdowns(dd);
      positionDropdown(btn, dd);
      dd.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      openDropdown = dd;
      var search = dd.querySelector('.pa-col-filter-search');
      if (search) {
        search.value = '';
        dd.querySelectorAll('.pa-col-filter-option').forEach(function (opt) {
          opt.style.display = '';
        });
        search.focus();
      }
      syncSelectAll(dd);
    });
  });

  document.querySelectorAll('.pa-col-filter-search').forEach(function (input) {
    input.addEventListener('input', function () {
      var dd = input.closest('.pa-col-filter-dropdown');
      var term = input.value.trim().toLowerCase();
      dd.querySelectorAll('.pa-col-filter-option').forEach(function (opt) {
        var text = opt.getAttribute('data-text') || '';
        opt.style.display = !term || text.indexOf(term) !== -1 ? '' : 'none';
      });
      syncSelectAll(dd);
    });
    input.addEventListener('click', function (e) { e.stopPropagation(); });
  });

  document.querySelectorAll('.pa-col-filter-check-all').forEach(function (el) {
    el.addEventListener('change', function () {
      var dd = el.closest('.pa-col-filter-dropdown');
      var state = el.checked;
      dd.querySelectorAll('.pa-col-filter-option').forEach(function (opt) {
        if (opt.style.display === 'none') return;
        var item = opt.querySelector('.pa-col-filter-item');
        if (item) item.checked = state;
      });
      syncSelectAll(dd);
    });
    el.addEventListener('click', function (e) { e.stopPropagation(); });
  });

  document.querySelectorAll('.pa-col-filter-item').forEach(function (el) {
    el.addEventListener('change', function () {
      syncSelectAll(el.closest('.pa-col-filter-dropdown'));
    });
    el.addEventListener('click', function (e) { e.stopPropagation(); });
  });

  document.querySelectorAll('.pa-col-filter-apply').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dd = btn.closest('.pa-col-filter-dropdown');
      var param = dd.dataset.param;
      var values = Array.prototype.map.call(
        dd.querySelectorAll('.pa-col-filter-item:checked'),
        function (el) { return el.value; }
      );
      var overrides = {};
      overrides[param] = values;
      window.location.href = buildUrl(overrides);
    });
  });

  document.querySelectorAll('.pa-col-filter-reset-col').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dd = btn.closest('.pa-col-filter-dropdown');
      var param = dd.dataset.param;
      var overrides = {};
      overrides[param] = [];
      window.location.href = buildUrl(overrides);
    });
  });

  document.addEventListener('click', function () {
    closeAllDropdowns();
  });

  document.querySelectorAll('.pa-col-filter-dropdown').forEach(function (dd) {
    dd.addEventListener('click', function (e) { e.stopPropagation(); });
  });

  window.addEventListener('resize', function () {
    if (!openDropdown) return;
    var btn = document.querySelector('.pa-col-filter-btn[data-col="' + openDropdown.dataset.col + '"]');
    if (btn) positionDropdown(btn, openDropdown);
  });

  var exportBtn = document.getElementById('exportMasterBarangBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function (e) {
      if (exportBtn.dataset.loading === '1') {
        e.preventDefault();
        return;
      }
      exportBtn.dataset.loading = '1';
      exportBtn.classList.add('disabled');
      exportBtn.setAttribute('aria-disabled', 'true');
      var label = exportBtn.innerHTML;
      exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menyiapkan...';
      window.setTimeout(function () {
        exportBtn.dataset.loading = '0';
        exportBtn.classList.remove('disabled');
        exportBtn.removeAttribute('aria-disabled');
        exportBtn.innerHTML = label;
      }, 4000);
    });
  }

  window.addEventListener('scroll', function () {
    if (!openDropdown) return;
    var btn = document.querySelector('.pa-col-filter-btn[data-col="' + openDropdown.dataset.col + '"]');
    if (btn) positionDropdown(btn, openDropdown);
  }, true);
})();
</script>
@endpush

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
    <button class="btn btn-prs-green btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="ti ti-plus"></i> Tambah Barang
    </button>
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
    <div class="col-md-3">
      <label class="form-label">Unit Kerja</label>
      <select name="unit" class="form-select form-select-sm">
        <option value="">Semua Unit</option>
        @foreach($units as $u)<option value="{{ $u }}" @selected(($filters['unit'] ?? '') === $u)>{{ UnitKerja::label($u) }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Kode / Nama</label>
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Pencarian cepat...">
    </div>
    <div class="col-md-2">
      <button class="btn btn-prs-blue btn-sm w-100"><i class="ti ti-filter"></i> Filter</button>
    </div>
    <div class="col-md-3 d-flex gap-2">
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
    <div class="small text-muted">
      <span id="barangSelectedCount">0</span> barang dipilih
      @if($barang->total() > 0)
        <span class="text-muted">
          · Menampilkan {{ $barang->firstItem() }}&ndash;{{ $barang->lastItem() }}
          dari {{ number_format($barang->total(), 0, ',', '.') }} barang
        </span>
      @endif
    </div>
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
            <td>@if($b->is_active)<span class="prs-badge selesai">Aktif</span>@else<span class="prs-badge ditolak">Nonaktif</span>@endif</td>
            <td class="text-end">
              <button class="btn btn-outline-primary btn-sm"
                      data-bs-toggle="modal" data-bs-target="#editModal"
                      data-id="{{ $b->id }}" data-kode="{{ $b->kode_barang }}" data-nama="{{ $b->nama_barang }}"
                      data-unit="{{ $b->unit_kerja }}" data-satuan="{{ $b->satuan }}"
                      data-awal="{{ $b->stok_awal }}" data-min="{{ $b->stok_minimum }}"
                      data-harga="{{ $b->harga_satuan }}" data-active="{{ $b->is_active ? 1 : 0 }}">
                <i class="ti ti-edit"></i>
              </button>
              <form action="{{ route('persediaan.admin.master-barang.destroy', $b->id) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('Nonaktifkan barang ini?');">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm"><i class="ti ti-trash"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-3">Belum ada barang.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $barang->links() }}</div>
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

  window.addEventListener('scroll', function () {
    if (!openDropdown) return;
    var btn = document.querySelector('.pa-col-filter-btn[data-col="' + openDropdown.dataset.col + '"]');
    if (btn) positionDropdown(btn, openDropdown);
  }, true);
})();
</script>
@endpush

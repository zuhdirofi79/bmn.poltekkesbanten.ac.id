@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Form Permintaan Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=5">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="prs-section-title mb-0">Form Permintaan Barang</span>
    <div>
      <a href="{{ route('public.persediaan.permintaan.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-list"></i> Permintaan Saya
      </a>
      <form action="{{ route('public.persediaan.logout') }}" method="POST" class="d-inline">
        @csrf
        <button class="btn btn-outline-danger btn-sm"><i class="ti ti-logout"></i> Keluar</button>
      </form>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="prs-public-card persediaan-request-card">
    <p class="prs-unit-badge">{{ UnitKerja::label($pemohon->unit_kerja) }}</p>

    <div class="prs-meta">
      <div class="row">
        <div class="col-md-6">
          <label>No. Permintaan</label>
          <strong class="text-muted">PRM-{{ UnitKerja::kode($pemohon->unit_kerja) }}-{{ date('Y') }}-XXX
            <small>(otomatis saat disimpan)</small></strong>
        </div>
        <div class="col-md-6">
          <label for="tanggalPermintaan">Tanggal Permintaan *</label>
          <input type="date" id="tanggalPermintaan" name="tanggal_permintaan" form="prsForm"
                 value="{{ old('tanggal_permintaan', $tanggalValue) }}"
                 class="form-control form-control-sm" required>
        </div>
        <div class="col-md-6">
          <label>Pemohon</label>
          <strong>{{ $pemohon->nama }}</strong>
        </div>
        <div class="col-md-6">
          <label>NIP</label>
          <strong>{{ $pemohon->nip }}</strong>
        </div>
        <div class="col-md-6">
          <label>Jabatan</label>
          <strong>{{ $pemohon->jabatan ?: '-' }}</strong>
        </div>
        <div class="col-md-6">
          <label>Unit Kerja</label>
          <strong>{{ UnitKerja::label($pemohon->unit_kerja) }}</strong>
        </div>
      </div>
    </div>

    <form method="GET" action="{{ route('public.persediaan.permintaan.create') }}" id="prsBarangFilterForm"
          class="prs-barang-filter row g-2 align-items-end mb-3">
      <input type="hidden" name="tanggal_permintaan" id="filterTanggalPermintaan" value="{{ old('tanggal_permintaan', $tanggalValue) }}">
      @foreach(['kode_barang', 'nama_barang', 'stok_tersedia'] as $colKey)
        @foreach($columnFilters[$colKey] ?? [] as $fv)
          <input type="hidden" name="{{ $colKey }}[]" value="{{ $fv }}">
        @endforeach
      @endforeach
      @foreach($columnFilters['satuan'] ?? [] as $fv)
        <input type="hidden" name="satuan[]" value="{{ $fv }}">
      @endforeach
      <div class="col-lg-4 col-md-6">
        <label class="form-label">Cari Barang</label>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm"
               placeholder="Cari kode barang atau nama barang...">
      </div>
      <div class="col-lg-2 col-md-3 col-6">
        <label class="form-label">Satuan</label>
        <select name="satuan" class="form-select form-select-sm"
                @if(!empty($columnFilters['satuan'])) disabled title="Satuan difilter lewat kolom tabel" @endif>
          <option value="">Semua Satuan</option>
          @foreach($satuanOptions as $sat)
            <option value="{{ $sat }}" @selected(($filters['satuan'] ?? '') === $sat)>{{ $sat }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2 col-md-3 col-6">
        <label class="form-label">Status Stok</label>
        <select name="stok" class="form-select form-select-sm">
          <option value="">Semua Stok</option>
          <option value="tersedia" @selected(($filters['stok'] ?? '') === 'tersedia')>Tersedia</option>
          <option value="kosong" @selected(($filters['stok'] ?? '') === 'kosong')>Kosong</option>
          <option value="menipis" @selected(($filters['stok'] ?? '') === 'menipis')>Stok Menipis</option>
        </select>
      </div>
      <div class="col-lg-4 col-md-12">
        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-prs-blue btn-sm">
            <i class="ti ti-filter"></i> Filter
          </button>
          <a href="{{ route('public.persediaan.permintaan.create') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-refresh"></i> Reset
          </a>
        </div>
      </div>
    </form>
    @if(!empty($hasColumnFilter))
      <div class="mb-2">
        <span class="badge bg-light text-primary border">
          <i class="ti ti-filter"></i> Filter per kolom aktif
        </span>
      </div>
    @endif

    <form method="POST" action="{{ route('public.persediaan.permintaan.store') }}" id="prsForm">
      @csrf
      <input type="hidden" name="action" id="prsAction" value="draft">
      <input type="hidden" name="tanda_tangan_pemohon" id="prsTtdInput">

      <div class="pa-table-filter-wrap">
        <table class="prs-table pa-table--filterable mb-0">
          <thead>
            <tr>
              <th style="width:48px;">No</th>
              @include('persediaan_admin.master-barang._column_filter', [
                'column' => 'kode',
                'param' => 'kode_barang',
                'label' => 'Kode Barang',
                'options' => $filterOptions['kode_barang'] ?? [],
                'active' => $columnFilters['kode_barang'] ?? [],
              ])
              @include('persediaan_admin.master-barang._column_filter', [
                'column' => 'nama',
                'param' => 'nama_barang',
                'label' => 'Nama Barang',
                'options' => $filterOptions['nama_barang'] ?? [],
                'active' => $columnFilters['nama_barang'] ?? [],
              ])
              @include('persediaan_admin.master-barang._column_filter', [
                'column' => 'satuan',
                'param' => 'satuan',
                'label' => 'Satuan',
                'options' => $filterOptions['satuan'] ?? [],
                'active' => $columnFilters['satuan'] ?? [],
              ])
              @include('persediaan_admin.master-barang._column_filter', [
                'column' => 'stok',
                'param' => 'stok_tersedia',
                'label' => 'Stok Tersedia',
                'options' => $filterOptions['stok_tersedia'] ?? [],
                'active' => $columnFilters['stok_tersedia'] ?? [],
                'align' => 'end',
              ])
              <th style="width:150px;">Jumlah Diminta</th>
            </tr>
          </thead>
          <tbody>
            @forelse($barangs as $i => $barang)
              @php
                $akhir = (int) ($ringkasan[$barang->id]['akhir'] ?? $barang->stok_awal);
                $menipis = $akhir > 0 && $akhir <= (int) $barang->stok_minimum;
                $qtyOld = old(
                  'items.'.$barang->id.'.jumlah_diminta',
                  data_get(request()->input('items', []), $barang->id.'.jumlah_diminta', old('items.'.$i.'.jumlah_diminta')),
                );
              @endphp
              <tr>
                <td>{{ $i + 1 }}</td>
                <td><code>{{ $barang->kode_barang }}</code></td>
                <td>
                  {{ $barang->nama_barang }}
                  @if($menipis)
                    <span class="badge bg-warning text-dark ms-1">Stok menipis</span>
                  @endif
                  <input type="hidden" name="items[{{ $barang->id }}][barang_id]" value="{{ $barang->id }}">
                </td>
                <td>{{ $barang->satuan }}</td>
                <td class="num">{{ number_format($akhir, 0, ',', '.') }}</td>
                <td>
                  @if($akhir <= 0)
                    <span class="badge bg-secondary">Stok kosong</span>
                    <input type="number" min="0" max="0"
                           name="items[{{ $barang->id }}][jumlah_diminta]"
                           value=""
                           class="form-control form-control-sm mt-1" placeholder="0" disabled>
                  @else
                    <input type="number" min="0" max="{{ $akhir }}"
                           name="items[{{ $barang->id }}][jumlah_diminta]"
                           value="{{ $qtyOld }}"
                           class="form-control form-control-sm prs-qty-input" placeholder="0"
                           data-stok="{{ $akhir }}">
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-3">
                @if(!($hasBarangInUnit ?? true))
                  Belum ada master barang persediaan untuk unit ini.
                @elseif($filterActive ?? false)
                  Barang tidak ditemukan untuk filter ini.
                @else
                  Belum ada master barang persediaan untuk unit ini.
                @endif
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        <label class="form-label">Keterangan <span class="text-danger">*</span></label>
        <textarea name="keterangan" rows="2" class="form-control" required
                  placeholder="Contoh: Untuk kebutuhan praktikum dan administrasi.">{{ old('keterangan', $preservedKeterangan ?? '') }}</textarea>
      </div>

      <div class="prs-sign-grid">
        <div class="prs-sign-box">
          <div class="ttl">Tanda Tangan Pemohon</div>
          <canvas id="prsSignPad" class="prs-sign-pad"></canvas>
          <div class="prs-sign-actions">
            <button type="button" class="btn btn-sm btn-outline-danger" id="prsSignClear">
              <i class="ti ti-eraser"></i> Hapus TTD
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="prsSignRedo">
              <i class="ti ti-refresh"></i> TTD Ulang
            </button>
          </div>
        </div>
        <div class="prs-sign-box">
          <div class="ttl">Mengetahui / Menyetujui</div>
          <p class="text-muted" style="margin-top:48px;">
            <i class="ti ti-clock"></i><br>
            Tanda tangan admin Persediaan akan diisi setelah permintaan disetujui.
          </p>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-prs-green" data-action="draft">
          <i class="ti ti-device-floppy"></i> Simpan Draft
        </button>
        <button type="submit" class="btn btn-prs-blue" data-action="ajukan">
          <i class="ti ti-send"></i> Ajukan Permintaan
        </button>
        <button type="button" class="btn btn-outline-secondary" disabled title="Tersedia setelah tersimpan">
          <i class="ti ti-printer"></i> Cetak PDF
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var canvas = document.getElementById('prsSignPad');
  var input  = document.getElementById('prsTtdInput');
  var form   = document.getElementById('prsForm');
  var actionField = document.getElementById('prsAction');
  if (!canvas) return;

  var ctx = canvas.getContext('2d');
  var drawing = false, dirty = false;

  function resize() {
    var ratio = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#15315c';
  }
  resize();

  function pos(e) {
    var rect = canvas.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - rect.left, y: t.clientY - rect.top };
  }
  function start(e) { drawing = true; dirty = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
  function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
  function end() { drawing = false; }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);

  function clearPad() { ctx.clearRect(0, 0, canvas.width, canvas.height); dirty = false; }
  document.getElementById('prsSignClear').addEventListener('click', clearPad);
  document.getElementById('prsSignRedo').addEventListener('click', clearPad);

  form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
    btn.addEventListener('click', function () { actionField.value = btn.getAttribute('data-action'); });
  });

  form.addEventListener('submit', function () {
    input.value = dirty ? canvas.toDataURL('image/png') : '';
  });

  var filterForm = document.getElementById('prsBarangFilterForm');
  if (filterForm) {
    filterForm.addEventListener('submit', function () {
      var tgl = document.getElementById('tanggalPermintaan');
      var tglHidden = document.getElementById('filterTanggalPermintaan');
      if (tgl && tglHidden) tglHidden.value = tgl.value;
      var ket = form.querySelector('textarea[name="keterangan"]');
      if (ket && ket.value.trim() !== '') {
        var hk = document.createElement('input');
        hk.type = 'hidden';
        hk.name = 'keterangan';
        hk.value = ket.value;
        filterForm.appendChild(hk);
      }
      form.querySelectorAll('input.prs-qty-input').forEach(function (inp) {
        var val = parseInt(inp.value, 10);
        if (!val || val <= 0) return;
        var clone = inp.cloneNode(true);
        clone.removeAttribute('disabled');
        filterForm.appendChild(clone);
        var bid = inp.name.match(/items\[(\d+)\]/);
        if (bid) {
          var hid = document.createElement('input');
          hid.type = 'hidden';
          hid.name = 'items[' + bid[1] + '][barang_id]';
          hid.value = bid[1];
          filterForm.appendChild(hid);
        }
      });
    });
  }

  form.querySelectorAll('input.prs-qty-input').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var max = parseInt(inp.getAttribute('max'), 10);
      var val = parseInt(inp.value, 10);
      if (val > max) inp.value = max;
      if (val < 0) inp.value = 0;
    });
  });

  /* ---------- Filter per kolom (Excel-style) ---------- */
  var COLUMN_PARAMS = ['kode_barang', 'nama_barang', 'satuan', 'stok_tersedia'];
  var openDropdown = null;

  function getArrayParam(params, key) {
    var bracket = params.getAll(key + '[]');
    if (bracket.length) return bracket;
    return [];
  }

  function appendFormState(parts) {
    var tgl = document.getElementById('tanggalPermintaan');
    if (tgl && tgl.value) {
      parts.push('tanggal_permintaan=' + encodeURIComponent(tgl.value));
    }
    var ket = form.querySelector('textarea[name="keterangan"]');
    if (ket && ket.value.trim() !== '') {
      parts.push('keterangan=' + encodeURIComponent(ket.value));
    }
    form.querySelectorAll('input.prs-qty-input').forEach(function (inp) {
      var val = parseInt(inp.value, 10);
      if (!val || val <= 0) return;
      var bid = inp.name.match(/items\[(\d+)\]/);
      if (!bid) return;
      parts.push('items[' + bid[1] + '][barang_id]=' + encodeURIComponent(bid[1]));
      parts.push('items[' + bid[1] + '][jumlah_diminta]=' + encodeURIComponent(String(val)));
    });
  }

  function readCurrentParams() {
    var params = new URLSearchParams(window.location.search);
    var out = {};
    COLUMN_PARAMS.forEach(function (key) {
      out[key] = getArrayParam(params, key);
    });
    if (params.get('q')) out.q = params.get('q');
    if (params.get('stok')) out.stok = params.get('stok');
    if (params.get('satuan') && !params.getAll('satuan[]').length) {
      out.satuan_global = params.get('satuan');
    }
    if (params.get('keterangan')) out.keterangan = params.get('keterangan');
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
    if (merged.q) parts.push('q=' + encodeURIComponent(merged.q));
    if (merged.stok) parts.push('stok=' + encodeURIComponent(merged.stok));
    if (merged.satuan_global) parts.push('satuan=' + encodeURIComponent(merged.satuan_global));
    COLUMN_PARAMS.forEach(function (key) {
      var vals = merged[key];
      if (!vals || !vals.length) return;
      vals.forEach(function (v) {
        parts.push(encodeURIComponent(key) + '[]=' + encodeURIComponent(v));
      });
    });
    appendFormState(parts);
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

@extends('layouts.thema')

@php
  $advancedFilterKeys = [
    'kode_barang', 'nup', 'nama_barang', 'merk', 'kategori_id',
    'kondisi', 'status', 'lokasi', 'satker_id', 'satker',
    'pengguna_ruangan', 'tahun_perolehan', 'tanggal_from', 'tanggal_to',
    'nilai_min', 'nilai_max', 'created_from', 'created_to',
  ];
  $hasAdvancedFilter = false;
  foreach ($advancedFilterKeys as $k) {
    if (isset($filters[$k]) && $filters[$k] !== '' && $filters[$k] !== null) {
      $hasAdvancedFilter = true;
      break;
    }
  }
@endphp

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      @php
        // Bawa filter/search aktif ke URL export, tapi buang "page" karena
        // export TIDAK dipaginate (harus seluruh hasil filter, bukan 1 halaman).
        $exportQuery = collect(request()->query())->except(['page'])->all();
      @endphp
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="card-title fw-semibold mb-0">Aset BMN</h5>
        <div class="d-flex flex-wrap gap-2">
          <a href="{{ route('bmn.aset.export.excel', $exportQuery) }}" class="btn btn-success btn-sm">
            <i class="ti ti-file-spreadsheet"></i> Export Excel
          </a>
          <a href="{{ route('bmn.aset.template') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-download"></i> Unduh template Excel
          </a>
          <a href="{{ route('bmn.aset.create') }}" class="btn btn-primary btn-sm">
            <i class="ti ti-plus"></i> Tambah Aset
          </a>
        </div>
      </div>
      <hr>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif
      @if(session('import_summary') && is_array(session('import_summary')))
        @php($sum = session('import_summary'))
        <div class="alert alert-info py-2 small mb-2">
          Ringkasan: <strong>{{ $sum['imported'] ?? 0 }}</strong> tersimpan;
          <strong>{{ $sum['skipped_count'] ?? 0 }}</strong> baris dilewati.
          @if(!empty($sum['skipped_rows']))
            <span class="text-muted">(baris: {{ implode(', ', $sum['skipped_rows']) }})</span>
          @endif
        </div>
      @endif
      @if(session('import_errors') && is_array(session('import_errors')) && count(session('import_errors')) !== 0)
        <div class="alert {{ session('success') ? 'alert-warning' : 'alert-danger' }}">
          <strong>{{ session('success') ? 'Peringatan import (baris dilewati):' : 'Validasi import:' }}</strong>
          <ul class="mb-0 mt-2 small">
            @foreach(session('import_errors') as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card border mb-3">
        <div class="card-body py-3">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h6 class="fw-semibold mb-0">Mapping Kategori Aset</h6>
            <small class="text-muted">Gunakan kolom <code>kategori_id</code> ini saat mengisi Excel import.</small>
          </div>
          <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th width="80">ID</th>
                  <th>Nama Kategori</th>
                </tr>
              </thead>
              <tbody>
                @forelse($kategoriList as $kat)
                  <tr>
                    <td>{{ $kat->id }}</td>
                    <td>{{ $kat->nama_kategori }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="2" class="text-center text-muted">Belum ada kategori. Tambahkan kategori sebelum import.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card border mb-4">
        <div class="card-body py-3">
          <h6 class="fw-semibold mb-2">Import multi-item (Excel)</h6>
          <p class="small text-muted mb-2">Gunakan template resmi. Baris valid akan disimpan; baris bermasalah akan dilaporkan dan dilewati.</p>
          <form method="post" action="{{ route('bmn.aset.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-8">
              <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.csv" required>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-outline-primary btn-sm">Unggah & import</button>
            </div>
          </form>
        </div>
      </div>

      <form method="get" action="{{ route('bmn.aset.index') }}" class="card border mb-3">
        <div class="card-body py-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-8">
              <label class="form-label mb-1">Pencarian global</label>
              <input type="text" name="q"
                     class="form-control form-control-sm"
                     value="{{ $filters['q'] ?? '' }}"
                     placeholder="Cari pada kode barang, nama, NUP, lokasi, satker, pengguna, merk...">
            </div>
            <div class="col-md-4 d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="ti ti-search"></i> Terapkan
              </button>
              <a href="{{ route('bmn.aset.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-refresh"></i> Reset
              </a>
              <button type="button"
                      class="btn btn-outline-info btn-sm"
                      data-toggle="collapse"
                      data-target="#advancedFilter"
                      data-bs-toggle="collapse"
                      data-bs-target="#advancedFilter"
                      aria-expanded="{{ $hasAdvancedFilter ? 'true' : 'false' }}"
                      aria-controls="advancedFilter">
                <i class="ti ti-filter"></i> Filter Lanjutan
              </button>
            </div>
          </div>

          <div id="advancedFilter" class="collapse {{ $hasAdvancedFilter ? 'show' : '' }} mt-3">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label mb-1">Kode Barang</label>
                <input type="text" name="kode_barang" class="form-control form-control-sm"
                       value="{{ $filters['kode_barang'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">NUP</label>
                <input type="text" name="nup" class="form-control form-control-sm"
                       value="{{ $filters['nup'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Nama Barang</label>
                <input type="text" name="nama_barang" class="form-control form-control-sm"
                       value="{{ $filters['nama_barang'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Merk</label>
                <input type="text" name="merk" class="form-control form-control-sm"
                       value="{{ $filters['merk'] ?? '' }}">
              </div>

              <div class="col-md-3">
                <label class="form-label mb-1">Kategori</label>
                <select name="kategori_id" class="form-control form-control-sm">
                  <option value="">— Semua —</option>
                  @foreach($kategoriList as $kat)
                    <option value="{{ $kat->id }}" {{ (string)($filters['kategori_id'] ?? '') === (string)$kat->id ? 'selected' : '' }}>
                      {{ $kat->nama_kategori }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Kondisi</label>
                <select name="kondisi" class="form-control form-control-sm">
                  <option value="">— Semua —</option>
                  @foreach(['baik', 'rusak_ringan', 'rusak_berat'] as $k)
                    <option value="{{ $k }}" {{ ($filters['kondisi'] ?? '') === $k ? 'selected' : '' }}>
                      {{ ucfirst(str_replace('_', ' ', $k)) }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-control form-control-sm">
                  <option value="">— Semua —</option>
                  @foreach(['aktif', 'dipindahtangankan', 'dihapus'] as $s)
                    <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>
                      {{ ucfirst(str_replace('_', ' ', $s)) }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Satker (master)</label>
                <select name="satker_id" class="form-control form-control-sm">
                  <option value="">— Semua —</option>
                  @foreach($satkers as $satker)
                    <option value="{{ $satker->id }}" {{ (string)($filters['satker_id'] ?? '') === (string)$satker->id ? 'selected' : '' }}>
                      {{ $satker->nama }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-3">
                <label class="form-label mb-1">Satker (teks)</label>
                <input type="text" name="satker" class="form-control form-control-sm"
                       value="{{ $filters['satker'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Lokasi</label>
                <input type="text" name="lokasi" class="form-control form-control-sm"
                       value="{{ $filters['lokasi'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Pengguna / Ruangan</label>
                <input type="text" name="pengguna_ruangan" class="form-control form-control-sm"
                       value="{{ $filters['pengguna_ruangan'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Tahun Perolehan</label>
                <input type="number" name="tahun_perolehan" min="1900" max="2100"
                       class="form-control form-control-sm"
                       value="{{ $filters['tahun_perolehan'] ?? '' }}">
              </div>

              <div class="col-md-3">
                <label class="form-label mb-1">Tgl Perolehan dari</label>
                <input type="date" name="tanggal_from" class="form-control form-control-sm"
                       value="{{ $filters['tanggal_from'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Tgl Perolehan s/d</label>
                <input type="date" name="tanggal_to" class="form-control form-control-sm"
                       value="{{ $filters['tanggal_to'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Nilai Perolehan ≥</label>
                <input type="number" step="0.01" name="nilai_min" class="form-control form-control-sm"
                       value="{{ $filters['nilai_min'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Nilai Perolehan ≤</label>
                <input type="number" step="0.01" name="nilai_max" class="form-control form-control-sm"
                       value="{{ $filters['nilai_max'] ?? '' }}">
              </div>

              <div class="col-md-3">
                <label class="form-label mb-1">Dibuat dari</label>
                <input type="date" name="created_from" class="form-control form-control-sm"
                       value="{{ $filters['created_from'] ?? '' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label mb-1">Dibuat s/d</label>
                <input type="date" name="created_to" class="form-control form-control-sm"
                       value="{{ $filters['created_to'] ?? '' }}">
              </div>
            </div>
          </div>
        </div>
      </form>

      {{-- Bulk delete form ditaruh DI LUAR <table> untuk menghindari nested form
           (form delete per-baris di kolom Aksi tetap berfungsi normal).
           Checkbox dan tombol dihubungkan ke form ini lewat atribut HTML5 form="bulkDeleteForm". --}}
      <form id="bulkDeleteForm"
            method="POST"
            action="{{ route('bmn.aset.bulk-delete') }}"
            class="d-none">
        @csrf
      </form>

      {{-- Export "terpilih" form: SAMA polanya seperti bulkDeleteForm — diletakkan
           di luar <table> dan dihubungkan ke tombol via atribut form="export-selected-form".
           Berbeda dgn Hapus Terpilih, checkbox baris TIDAK dilampirkan langsung ke
           form ini; JS yang membaca .aset-checkbox lalu mengisi hidden input
           selected_ids (CSV id) sebelum submit. Backend tetap memvalidasi ulang. --}}
      <form id="export-selected-form"
            method="POST"
            action="{{ route('bmn.aset.export.selected.excel') }}"
            class="d-none">
        @csrf
        <input type="hidden" name="selected_ids" id="export-selected-ids" value="">
      </form>

      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div class="d-flex flex-wrap gap-3 align-items-center">
          {{-- Form mandiri untuk dropdown "Show per page".
               Filter aktif dibawa lewat hidden input agar value filter tidak hilang
               saat user mengubah jumlah baris per halaman.
               'page' sengaja TIDAK disertakan: setelah ganti ukuran halaman, paginator
               kembali ke halaman 1 (mencegah out-of-range page yang menampilkan kosong). --}}
          <form method="get"
                action="{{ route('bmn.aset.index') }}"
                class="d-flex gap-2 align-items-center mb-0">
            @foreach($filters as $fKey => $fVal)
              @if($fVal !== null && $fVal !== '')
                <input type="hidden" name="{{ $fKey }}" value="{{ $fVal }}">
              @endif
            @endforeach
            <label class="small text-muted mb-0">Show</label>
            <select name="per_page"
                    class="form-select form-select-sm"
                    onchange="this.form.submit()"
                    style="width:100px"
                    aria-label="Jumlah baris per halaman">
              @foreach([10, 25, 50, 100, 250, 500, 1000] as $size)
                <option value="{{ $size }}" {{ (int)($perPage ?? 10) === $size ? 'selected' : '' }}>
                  {{ $size }}
                </option>
              @endforeach
            </select>
            <label class="small text-muted mb-0">rows</label>
          </form>

          <div class="small text-muted">
            <span id="bulkSelectedCount">0</span> aset dipilih
            @if($aset->total() > 0)
              <span class="text-muted">
                · Menampilkan {{ $aset->firstItem() }}&ndash;{{ $aset->lastItem() }}
                dari {{ number_format($aset->total(), 0, ',', '.') }} aset
              </span>
            @endif
          </div>
        </div>

        <div class="d-flex align-items-center gap-2">
          @if(($perPage ?? 10) >= 500)
            <span class="badge bg-warning text-dark" title="Limit per submit bulk delete">
              <i class="ti ti-alert-triangle"></i> Maks 5.000 / hapus
            </span>
          @endif
          <button type="submit"
                  form="export-selected-form"
                  id="btn-export-selected"
                  class="btn btn-success btn-sm"
                  disabled>
            <i class="ti ti-file-spreadsheet"></i>
            <span id="btn-export-selected-label">Export Excel Terpilih</span>
          </button>
          <button type="submit"
                  form="bulkDeleteForm"
                  id="bulkDeleteBtn"
                  class="btn btn-danger btn-sm"
                  disabled
                  onclick="return confirm('Hapus aset terpilih? Aksi ini akan menandai aset sebagai dihapus (soft delete).');">
            <i class="ti ti-trash"></i> Hapus Terpilih
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle mb-0">
          <thead>
            <tr>
              <th width="40" class="text-center">
                <input type="checkbox" id="checkAll" form="bulkDeleteForm" aria-label="Pilih semua">
              </th>
              <th width="40">No</th>
              <th>Kode Barang</th>
              <th>NUP</th>
              <th>Nama Barang</th>
              <th>Merk</th>
              <th>Kategori</th>
              <th>Nilai Perolehan</th>
              <th>Tgl Perolehan</th>
              <th>Kondisi</th>
              <th>Lokasi</th>
              <th>Status</th>
              <th>Pengguna</th>
              <th>Satker</th>
              <th width="100">Foto</th>
              <th width="120">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($aset as $row)
              <tr>
                <td class="text-center">
                  <input type="checkbox"
                         class="row-check aset-checkbox"
                         name="ids[]"
                         value="{{ $row->id }}"
                         form="bulkDeleteForm"
                         aria-label="Pilih aset {{ $row->kode_barang }}">
                </td>
                <td>{{ $aset->firstItem() + $loop->index }}</td>
                <td>{{ $row->kode_barang }}</td>
                <td>{{ $row->nup ?? '-' }}</td>
                <td>{{ $row->nama_barang ?? '-' }}</td>
                <td>{{ $row->merk ?? '-' }}</td>
                <td>{{ optional($row->kategori)->nama_kategori ?? '-' }}</td>
                <td class="text-end">{{ $row->nilai_perolehan !== null ? number_format((float) $row->nilai_perolehan, 2, ',', '.') : '-' }}</td>
                <td>{{ optional($row->tanggal_perolehan)->format('Y-m-d') ?? '-' }}</td>
                <td>{{ $row->kondisi ? ucfirst(str_replace('_', ' ', $row->kondisi)) : '-' }}</td>
                <td>{{ $row->labelLokasi() }}</td>
                <td>{{ $row->status ? ucfirst(str_replace('_', ' ', $row->status)) : '-' }}</td>
                <td>{{ $row->pengguna_ruangan ?? '-' }}</td>
                <td>{{ $row->satker ?? '-' }}</td>
                <td>
                  <?php
                    $fotoList = [];

                    if (is_array($row->foto) && count($row->foto) > 0) {
                        $fotoList = $row->foto;
                    } elseif (is_string($row->foto) && $row->foto !== '') {
                        $decodedFoto = json_decode($row->foto, true);

                        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedFoto)) {
                            $fotoList = $decodedFoto;
                        }
                    }

                    if (count($fotoList) === 0 && !empty($row->foto_url)) {
                        $fotoList = [
                            [
                                'thumb' => $row->foto_url,
                                'full' => $row->foto_url,
                            ],
                        ];
                    }
                  ?>

                  <?php if (count($fotoList) > 0): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:5px;max-width:200px;">
                      <?php foreach ($fotoList as $img): ?>
                        <?php
                          $thumbUrl = is_array($img) ? ($img['thumb'] ?? $img['full'] ?? '') : '';
                          $fullUrl = is_array($img) ? ($img['full'] ?? $thumbUrl) : $thumbUrl;

                          $thumbUrl = (string) $thumbUrl;
                          $fullUrl = (string) $fullUrl;

                          $src = '';
                          $href = '';

                          if ($thumbUrl !== '') {
                              $src = preg_match('/^https?:\/\//i', $thumbUrl) === 1 ? $thumbUrl : url($thumbUrl);
                          }

                          if ($fullUrl !== '') {
                              $href = preg_match('/^https?:\/\//i', $fullUrl) === 1 ? $fullUrl : url($fullUrl);
                          }

                          if ($href === '') {
                              $href = $src;
                          }
                        ?>

                        <?php if ($src !== ''): ?>
                          <a href="{{ $href }}" target="_blank" rel="noopener noreferrer">
                            <img
                              src="{{ $src }}"
                              width="50"
                              height="50"
                              loading="lazy"
                              alt="Foto aset"
                              style="object-fit:cover;border-radius:4px;border:1px solid #e9ecef;"
                            >
                          </a>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    &mdash;
                  <?php endif; ?>
                </td>
                <td>
                  <a href="{{ route('bmn.aset.edit', $row) }}" class="btn btn-sm btn-primary"><i class="ti ti-edit"></i></a>
                  <form action="{{ route('bmn.aset.destroy', $row) }}" method="post" class="d-inline" onsubmit="return confirm('Hapus aset dari daftar? Data tetap tersimpan di basis data (penghapusan lunak).');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-trash"></i></button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="16" class="text-center text-muted">Belum ada data aset.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3 bmn-pagination">
        {{ $aset->appends(request()->query())->links('pagination::bootstrap-5') }}
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var checkAll = document.getElementById('checkAll');
  var bulkBtn = document.getElementById('bulkDeleteBtn');
  var counter = document.getElementById('bulkSelectedCount');

  // Export "terpilih" — reuse mekanisme checkbox baris yang sama (.row-check /
  // .aset-checkbox), jangan bikin sistem checkbox baru. Hidden input
  // selected_ids akan diisi CSV id (mis: "1,3,7") sebelum form di-submit.
  var exportBtn = document.getElementById('btn-export-selected');
  var exportLabel = document.getElementById('btn-export-selected-label');
  var exportIdsInput = document.getElementById('export-selected-ids');
  var exportForm = document.getElementById('export-selected-form');

  function rowChecks() {
    return document.querySelectorAll('.row-check');
  }

  function updateSelectedExport(checkedList) {
    if (!exportBtn) return;
    var ids = Array.prototype.map.call(checkedList, function (cb) { return cb.value; });
    if (exportIdsInput) exportIdsInput.value = ids.join(',');
    exportBtn.disabled = ids.length === 0;
    if (exportLabel) {
      exportLabel.textContent = ids.length > 0
        ? 'Export Excel Terpilih (' + ids.length + ')'
        : 'Export Excel Terpilih';
    }
  }

  function update() {
    var checked = document.querySelectorAll('.row-check:checked');
    var total = rowChecks().length;
    if (counter) counter.textContent = String(checked.length);
    if (bulkBtn) bulkBtn.disabled = checked.length === 0;
    if (checkAll) {
      checkAll.checked = total > 0 && checked.length === total;
      checkAll.indeterminate = checked.length > 0 && checked.length < total;
    }
    updateSelectedExport(checked);
  }

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      var state = this.checked;
      rowChecks().forEach(function (el) { el.checked = state; });
      update();
    });
  }

  rowChecks().forEach(function (el) {
    el.addEventListener('change', update);
  });

  // Safety net: kalau ada race condition / klik super cepat sebelum event
  // change ter-handle, sinkronkan ulang hidden input saat form submit.
  if (exportForm) {
    exportForm.addEventListener('submit', function (e) {
      var checked = document.querySelectorAll('.row-check:checked');
      updateSelectedExport(checked);
      if (checked.length === 0) {
        e.preventDefault();
      }
    });
  }

  update();
})();
</script>
@endsection

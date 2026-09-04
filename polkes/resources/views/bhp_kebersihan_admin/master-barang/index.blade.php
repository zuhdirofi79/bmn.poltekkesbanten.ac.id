@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Master Data Barang BHP')

@section('content')
<div class="pa-card mb-3">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap">
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Cari kode/nama/merk">
      <select name="status" class="form-select form-select-sm">
        <option value="">Semua status</option>
        <option value="aktif" @selected(($filters['status'] ?? '')==='aktif')>Aktif</option>
        <option value="nonaktif" @selected(($filters['status'] ?? '')==='nonaktif')>Nonaktif</option>
      </select>
      <button type="submit" class="btn btn-prs-blue btn-sm">Filter</button>
    </form>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('bhp.admin.master-barang.export') }}" class="btn btn-outline-success btn-sm">Export Excel</a>
      <form method="POST" action="{{ route('bhp.admin.master-barang.generate-all-barcodes') }}" class="d-inline" id="formGenerateAll">
        @csrf
        <button type="submit" class="btn btn-prs-green btn-sm">Generate Semua Barcode</button>
      </form>
      <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrintBatch">Print Barcode Batch</button>
    </div>
  </div>

  @if(!empty($canManageMaster))
  <div id="bhpBulkBar" class="bhp-bulk-bar d-none mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <strong id="bhpBulkCount">0 barang dipilih</strong>
      <button type="button" class="btn btn-outline-danger btn-sm" id="bhpBulkDeleteBtn" disabled>Hapus Terpilih</button>
    </div>
  </div>
  @endif

  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr>
          <th><input type="checkbox" id="checkAll"></th>
          <th>Foto</th>
          <th>Kode</th>
          <th>Nama</th>
          <th>Merk</th>
          <th>Satuan</th>
          <th>Stok Saat Ini</th>
          <th>Status</th>
          <th>Barcode</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($barang as $b)
          @php
            $barcodeUrl = $barcodeUrlMap[$b->id] ?? null;
            $fotoUrl = $fotoUrlMap[$b->id] ?? null;
          @endphp
          <tr>
            <td>
              <input
                type="checkbox"
                class="barang-check"
                value="{{ $b->id }}"
                data-kode="{{ $b->kode_barang }}"
                data-nama="{{ $b->nama_barang }}"
              >
            </td>
            <td>
              @if($fotoUrl)
                <button type="button" class="bhp-foto-thumb-btn" data-bs-toggle="modal" data-bs-target="#fotoPreviewModal" data-foto-src="{{ $fotoUrl }}" data-foto-alt="{{ $b->nama_barang }}">
                  <img src="{{ $fotoUrl }}" alt="Foto {{ $b->nama_barang }}" class="bhp-foto-thumb">
                </button>
              @else
                <div class="bhp-foto-placeholder" title="Belum ada foto">—</div>
              @endif
            </td>
            <td>{{ $b->kode_barang }}</td>
            <td>{{ $b->nama_barang }}</td>
            <td>{{ $b->merk ?: '—' }}</td>
            <td>{{ $b->satuan }}</td>
            <td>{{ $stokMap[$b->id] ?? 0 }}</td>
            <td>{{ ucfirst($b->status) }}</td>
            <td class="bhp-barcode-cell">
              @if($barcodeUrl)
                <div class="barcode-preview">
                  <img src="{{ $barcodeUrl }}" alt="Barcode {{ $b->kode_barang }}">
                </div>
                <div class="bhp-barcode-actions mt-1">
                  <a href="{{ $barcodeUrl }}" class="btn btn-outline-secondary btn-sm py-0 px-1" target="_blank" title="Lihat">Lihat</a>
                  <a href="{{ route('bhp.admin.master-barang.download-barcode', $b->id) }}" class="btn btn-outline-secondary btn-sm py-0 px-1" title="Download">Download</a>
                  <a href="{{ route('bhp.admin.master-barang.print-barcode', $b->id) }}" class="btn btn-outline-primary btn-sm py-0 px-1" target="_blank" title="Print">Print</a>
                </div>
              @else
                <div class="text-muted small mb-1">Barcode belum tersedia</div>
                <form method="POST" action="{{ route('bhp.admin.master-barang.generate-barcode', $b->id) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-prs-green btn-sm">Generate Barcode</button>
                </form>
              @endif
            </td>
            <td>
              <div class="bhp-aksi-wrap">
                <a href="{{ route('bhp.admin.master-barang.show', $b->id) }}" class="btn btn-outline-secondary btn-sm">Detail</a>
                <button
                  type="button"
                  class="btn btn-outline-secondary btn-sm bhp-edit-open"
                  data-id="{{ $b->id }}"
                  data-kode="{{ $b->kode_barang }}"
                  data-nama="{{ $b->nama_barang }}"
                  data-merk="{{ $b->merk }}"
                  data-satuan="{{ $b->satuan }}"
                  data-harga="{{ $b->harga_satuan }}"
                  data-status="{{ $b->status }}"
                  data-foto="{{ $fotoUrl }}"
                  data-action="{{ route('bhp.admin.master-barang.update', $b->id) }}"
                >Edit</button>
                @if(!empty($canManageMaster))
                <button
                  type="button"
                  class="btn btn-outline-success btn-sm bhp-stok-open"
                  data-id="{{ $b->id }}"
                  data-kode="{{ $b->kode_barang }}"
                  data-nama="{{ $b->nama_barang }}"
                  data-satuan="{{ $b->satuan }}"
                  data-stok="{{ $stokMap[$b->id] ?? 0 }}"
                  data-action="{{ route('bhp.admin.master-barang.koreksi-stok', $b->id) }}"
                >Edit Stok</button>
                <button
                  type="button"
                  class="btn btn-outline-danger btn-sm bhp-delete-open"
                  data-id="{{ $b->id }}"
                  data-kode="{{ $b->kode_barang }}"
                  data-nama="{{ $b->nama_barang }}"
                  data-merk="{{ $b->merk }}"
                  data-stok="{{ $stokMap[$b->id] ?? 0 }}"
                  data-satuan="{{ $b->satuan }}"
                  data-permintaan="{{ $b->permintaan_count ?? 0 }}"
                  data-movements="{{ $b->movements_count ?? 0 }}"
                  data-opname="{{ $b->opname_details_count ?? 0 }}"
                  data-action="{{ route('bhp.admin.master-barang.destroy', $b->id) }}"
                >Hapus</button>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-center text-muted py-3">Belum ada barang.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="bhp-pagination">
    {{ $barang->links('pagination::bootstrap-5') }}
  </div>
</div>

<div class="pa-card">
  <h2>Tambah Barang BHP</h2>
  <form method="POST" action="{{ route('bhp.admin.master-barang.store') }}" class="row g-2 mt-2" enctype="multipart/form-data">
    @csrf
    <div class="col-md-2"><input name="kode_barang" class="form-control" placeholder="Kode barang" required></div>
    <div class="col-md-2"><input name="nama_barang" class="form-control" placeholder="Nama barang" required></div>
    <div class="col-md-2"><input name="merk" class="form-control" placeholder="Merk (opsional)" maxlength="255"></div>
    <div class="col-md-1"><input name="satuan" class="form-control" placeholder="Satuan" value="Buah" required></div>
    <div class="col-md-1"><input name="stok_awal" type="number" min="0" class="form-control" placeholder="Stok awal" required></div>
    <div class="col-md-2"><input name="harga_satuan" type="number" step="0.01" min="0" class="form-control" placeholder="Harga (opsional)"></div>
    <div class="col-md-2"><input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></div>
    <div class="col-md-12 col-lg-1"><button type="submit" class="btn btn-prs-green w-100">+</button></div>
  </form>
  <small class="text-muted d-block mt-1">Foto opsional: JPG/PNG/WEBP, maks. 5 MB. Stok awal = saldo awal, tidak dicatat ulang sebagai barang masuk.</small>
</div>

<div class="modal fade bhp-edit-modal" id="bhpEditModal" tabindex="-1" aria-labelledby="bhpEditModalTitle" aria-modal="true" role="dialog" data-bs-backdrop="true" data-bs-keyboard="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable bhp-edit-modal-dialog">
    <form method="POST" action="#" class="modal-content bhp-edit-modal-content" id="bhpEditForm" enctype="multipart/form-data">
      @csrf @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title" id="bhpEditModalTitle">Edit Barang BHP</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger{{ session('open_edit_id') && $errors->any() ? '' : ' d-none' }}" id="bhpEditErrorBox">
          @if($errors->any())
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          @endif
        </div>

        <div class="mb-3">
          <label class="form-label" for="bhpEditKode">Kode Barang</label>
          <input
            type="text"
            name="kode_barang"
            id="bhpEditKode"
            class="form-control{{ session('open_edit_id') && $errors->has('kode_barang') ? ' is-invalid' : '' }}"
            required
            maxlength="100"
            autocomplete="off"
          >
          <small class="text-muted" id="bhpEditKodeHint">Kode barang harus unik.</small>
          <div class="invalid-feedback{{ session('open_edit_id') && $errors->has('kode_barang') ? ' d-block' : ' d-none' }}" id="bhpEditKodeErr">
            @error('kode_barang'){{ $message }}@enderror
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="bhpEditNama">Nama Barang</label>
          <input type="text" name="nama_barang" id="bhpEditNama" class="form-control" required maxlength="200">
          @error('nama_barang')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label" for="bhpEditMerk">Merk</label>
          <input type="text" name="merk" id="bhpEditMerk" class="form-control" maxlength="255" placeholder="Opsional">
          @error('merk')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label" for="bhpEditSatuan">Satuan</label>
          <input type="text" name="satuan" id="bhpEditSatuan" class="form-control" required maxlength="50">
          @error('satuan')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label" for="bhpEditHarga">Harga Satuan</label>
          <input type="number" name="harga_satuan" id="bhpEditHarga" class="form-control" step="0.01" min="0" placeholder="Opsional">
          @error('harga_satuan')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label">Foto Barang</label>
          <div class="bhp-edit-foto-wrap mb-2">
            <img src="" alt="Foto barang" id="bhpEditFotoImg" class="bhp-edit-foto-img d-none">
            <div id="bhpEditFotoEmpty" class="bhp-foto-placeholder bhp-foto-placeholder-lg">Belum ada foto</div>
          </div>
          <input type="file" name="foto" id="bhpEditFoto" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
          <small class="text-muted">JPG, PNG, atau WEBP. Maks. 5 MB. Kosongkan jika tidak mengganti foto.</small>
          @error('foto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-1">
          <label class="form-label" for="bhpEditStatus">Status</label>
          <select name="status" id="bhpEditStatus" class="form-select" required>
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
          @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-blue" id="bhpEditSubmit">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade bhp-edit-modal" id="fotoPreviewModal" tabindex="-1" aria-labelledby="fotoPreviewTitle" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered bhp-edit-modal-dialog">
    <div class="modal-content bhp-edit-modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="fotoPreviewTitle">Foto Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" alt="Preview foto" id="fotoPreviewImg" class="bhp-foto-preview">
      </div>
    </div>
  </div>
</div>

<div class="modal fade bhp-edit-modal" id="bhpStokModal" tabindex="-1" aria-labelledby="bhpStokModalTitle" aria-modal="true" role="dialog" data-bs-backdrop="true" data-bs-keyboard="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable bhp-edit-modal-dialog">
    <form method="POST" action="#" class="modal-content bhp-edit-modal-content" id="bhpStokForm">
      @csrf
      <input type="hidden" name="idempotency_key" id="bhpStokIdem">
      <div class="modal-header">
        <h5 class="modal-title" id="bhpStokModalTitle">Koreksi Stok Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger{{ session('open_koreksi_id') && $errors->any() ? '' : ' d-none' }}" id="bhpStokErrorBox">
          @if(session('open_koreksi_id') && $errors->any())
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          @endif
        </div>

        <div class="bhp-meta-box mb-3">
          <div class="mb-2">
            <div class="text-muted small">Nama Barang</div>
            <div class="fw-semibold" id="bhpStokNama"></div>
          </div>
          <div class="mb-2">
            <div class="text-muted small">Kode</div>
            <div class="fw-semibold" id="bhpStokKode"></div>
          </div>
          <div>
            <div class="text-muted small">Stok Saat Ini</div>
            <div class="fw-semibold" id="bhpStokSekarangLabel"></div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Jenis Perubahan</label>
          <div class="bhp-radio-row">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="arah" id="bhpStokTambah" value="tambah" checked>
              <label class="form-check-label" for="bhpStokTambah">Tambah Stok</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="arah" id="bhpStokKurangi" value="kurangi">
              <label class="form-check-label" for="bhpStokKurangi">Kurangi Stok</label>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="bhpStokJumlah">Jumlah</label>
          <input type="number" name="jumlah" id="bhpStokJumlah" class="form-control" min="1" step="1" required>
          <div class="invalid-feedback d-none" id="bhpStokJumlahErr"></div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="bhpStokCatatan">Alasan / Keterangan</label>
          <textarea name="catatan" id="bhpStokCatatan" class="form-control" rows="3" required maxlength="1000" placeholder="Contoh: Penyesuaian stok hasil pengecekan fisik."></textarea>
          <div class="invalid-feedback d-none" id="bhpStokCatatanErr"></div>
        </div>

        <div class="bhp-stok-preview">
          <div class="text-muted small mb-2">Preview</div>
          <div class="bhp-stok-preview-row"><span>Stok saat ini</span><span id="bhpPrevSekarang">0</span></div>
          <div class="bhp-stok-preview-row"><span>Perubahan</span><span id="bhpPrevDelta">—</span></div>
          <div class="bhp-stok-preview-row"><span>Stok setelah</span><span id="bhpPrevSesudah">—</span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-blue" id="bhpStokSubmit">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade bhp-edit-modal" id="bhpDeleteModal" tabindex="-1" aria-labelledby="bhpDeleteModalTitle" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered bhp-edit-modal-dialog">
    <form method="POST" action="#" class="modal-content bhp-edit-modal-content" id="bhpDeleteForm">
      @csrf @method('DELETE')
      <div class="modal-header">
        <h5 class="modal-title" id="bhpDeleteModalTitle">Hapus Permanen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Anda yakin ingin menghapus permanen:</p>
        <div class="bhp-meta-box mb-3">
          <div class="mb-2">
            <div class="text-muted small">Kode</div>
            <div class="fw-semibold" id="bhpDeleteKode"></div>
          </div>
          <div class="mb-2">
            <div class="text-muted small">Nama</div>
            <div class="fw-semibold" id="bhpDeleteNama"></div>
          </div>
          <div class="mb-2">
            <div class="text-muted small">Merk</div>
            <div class="fw-semibold" id="bhpDeleteMerk"></div>
          </div>
          <div class="mb-2">
            <div class="text-muted small">Stok Saat Ini</div>
            <div class="fw-semibold" id="bhpDeleteStok"></div>
          </div>
          <div>
            <div class="text-muted small mb-1">Histori terkait</div>
            <div class="small">Permintaan : <span id="bhpDeleteCntPermintaan">0</span></div>
            <div class="small">Stock Movement : <span id="bhpDeleteCntMovement">0</span></div>
            <div class="small">Stock Opname : <span id="bhpDeleteCntOpname">0</span></div>
          </div>
        </div>
        <div class="alert alert-warning mb-0" id="bhpDeleteWarn">
          Data barang dan histori transaksi terkait akan dihapus secara permanen dan tidak dapat dipulihkan.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger" id="bhpDeleteSubmit">Ya, Hapus Permanen</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade bhp-edit-modal" id="bhpBulkDeleteModal" tabindex="-1" aria-labelledby="bhpBulkDeleteTitle" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable bhp-edit-modal-dialog">
    <form method="POST" action="{{ route('bhp.admin.master-barang.bulk-destroy') }}" class="modal-content bhp-edit-modal-content" id="bhpBulkDeleteForm">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title" id="bhpBulkDeleteTitle">Hapus barang terpilih?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" id="bhpBulkDeleteLead"></p>
        <ul class="mb-3" id="bhpBulkDeleteList"></ul>
        <div class="alert alert-warning mb-0">
          Semua barang terpilih akan dihapus permanen, termasuk histori permintaan, stock movement, dan stock opname terkait. Tindakan ini tidak dapat dipulihkan.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger" id="bhpBulkDeleteSubmit">Ya, Hapus Permanen</button>
      </div>
    </form>
  </div>
</div>

<form method="GET" action="{{ route('bhp.admin.master-barang.barcode-batch') }}" target="_blank" id="formPrintBatch" class="d-none"></form>
@endsection

@push('scripts')
<script>
(function () {
  function moveToBody(el) {
    if (el && el.parentElement !== document.body) {
      document.body.appendChild(el);
    }
  }

  var editModalEl = document.getElementById('bhpEditModal');
  var fotoModalEl = document.getElementById('fotoPreviewModal');
  var stokModalEl = document.getElementById('bhpStokModal');
  var deleteModalEl = document.getElementById('bhpDeleteModal');
  var bulkDeleteModalEl = document.getElementById('bhpBulkDeleteModal');
  moveToBody(editModalEl);
  moveToBody(fotoModalEl);
  moveToBody(stokModalEl);
  moveToBody(deleteModalEl);
  moveToBody(bulkDeleteModalEl);

  function refreshBulkUi() {
    var boxes = document.querySelectorAll('.barang-check');
    var checked = document.querySelectorAll('.barang-check:checked');
    var header = document.getElementById('checkAll');
    if (header) {
      header.checked = boxes.length > 0 && checked.length === boxes.length;
      header.indeterminate = checked.length > 0 && checked.length < boxes.length;
    }
    var bar = document.getElementById('bhpBulkBar');
    var countEl = document.getElementById('bhpBulkCount');
    var btn = document.getElementById('bhpBulkDeleteBtn');
    if (countEl) {
      countEl.textContent = checked.length + ' barang dipilih';
    }
    if (bar) {
      bar.classList.toggle('d-none', checked.length === 0);
    }
    if (btn) {
      btn.disabled = checked.length === 0;
    }
  }

  var checkAll = document.getElementById('checkAll');
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      document.querySelectorAll('.barang-check').forEach(function (cb) {
        cb.checked = checkAll.checked;
      });
      refreshBulkUi();
    });
  }
  document.querySelectorAll('.barang-check').forEach(function (cb) {
    cb.addEventListener('change', refreshBulkUi);
  });
  refreshBulkUi();

  var btnPrintBatch = document.getElementById('btnPrintBatch');
  var formPrintBatch = document.getElementById('formPrintBatch');
  if (btnPrintBatch && formPrintBatch) {
    btnPrintBatch.addEventListener('click', function () {
      formPrintBatch.innerHTML = '';
      var checked = document.querySelectorAll('.barang-check:checked');
      if (checked.length === 0) {
        window.open('{{ route('bhp.admin.master-barang.barcode-batch') }}', '_blank');
        return;
      }
      checked.forEach(function (cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        formPrintBatch.appendChild(input);
      });
      formPrintBatch.submit();
    });
  }

  var formGenerateAll = document.getElementById('formGenerateAll');
  if (formGenerateAll) {
    formGenerateAll.addEventListener('submit', function () {
      var checked = document.querySelectorAll('.barang-check:checked');
      formGenerateAll.querySelectorAll('input[name="ids[]"]').forEach(function (el) { el.remove(); });
      checked.forEach(function (cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        formGenerateAll.appendChild(input);
      });
    });
  }

  var previewImg = document.getElementById('fotoPreviewImg');
  document.querySelectorAll('.bhp-foto-thumb-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (previewImg) {
        previewImg.src = btn.getAttribute('data-foto-src') || '';
        previewImg.alt = btn.getAttribute('data-foto-alt') || 'Foto barang';
      }
    });
  });

  var form = document.getElementById('bhpEditForm');
  var kodeEl = document.getElementById('bhpEditKode');
  var kodeHint = document.getElementById('bhpEditKodeHint');
  var kodeErr = document.getElementById('bhpEditKodeErr');
  var editSubmit = document.getElementById('bhpEditSubmit');
  var namaEl = document.getElementById('bhpEditNama');
  var merkEl = document.getElementById('bhpEditMerk');
  var satuanEl = document.getElementById('bhpEditSatuan');
  var hargaEl = document.getElementById('bhpEditHarga');
  var statusEl = document.getElementById('bhpEditStatus');
  var fotoInput = document.getElementById('bhpEditFoto');
  var fotoImg = document.getElementById('bhpEditFotoImg');
  var fotoEmpty = document.getElementById('bhpEditFotoEmpty');
  var editModal = editModalEl && window.bootstrap ? new bootstrap.Modal(editModalEl) : null;
  var originalKode = '';
  var currentEditId = '';
  var kodeCheckTimer = null;
  var kodeCheckUrl = @json(route('bhp.admin.master-barang.check-kode'));
  var kodeDuplicateMsg = 'Kode tersebut telah digunakan! Silahkan coba kode lainnya.';

  function setKodeStatus(state, message) {
    if (!kodeEl) return;
    kodeEl.classList.remove('is-invalid', 'is-valid');
    if (kodeErr) {
      kodeErr.classList.add('d-none');
      if (message) kodeErr.textContent = message;
    }
    if (kodeHint) {
      kodeHint.classList.remove('text-danger', 'text-success');
      kodeHint.classList.add('text-muted');
      kodeHint.textContent = 'Kode barang harus unik.';
    }
    if (state === 'invalid') {
      kodeEl.classList.add('is-invalid');
      if (kodeErr) {
        kodeErr.textContent = message || kodeDuplicateMsg;
        kodeErr.classList.remove('d-none');
        kodeErr.classList.add('d-block');
      }
      if (kodeHint) {
        kodeHint.classList.remove('text-muted');
        kodeHint.classList.add('text-danger');
        kodeHint.textContent = message || kodeDuplicateMsg;
      }
      if (editSubmit) editSubmit.disabled = true;
      return;
    }
    if (state === 'valid') {
      kodeEl.classList.add('is-valid');
      if (kodeHint) {
        kodeHint.classList.remove('text-muted');
        kodeHint.classList.add('text-success');
        kodeHint.textContent = 'Kode tersedia.';
      }
    }
    if (editSubmit && editSubmit.dataset.busy !== '1') {
      editSubmit.disabled = false;
    }
  }

  function sameAsOriginal(kode) {
    return kode.toLowerCase() === String(originalKode || '').toLowerCase();
  }

  function checkKodeAvailability(kode) {
    if (!kode) {
      setKodeStatus('invalid', 'Kode barang wajib diisi.');
      return;
    }
    if (sameAsOriginal(kode)) {
      setKodeStatus('neutral');
      return;
    }
    var url = kodeCheckUrl + '?kode=' + encodeURIComponent(kode) + '&ignore_id=' + encodeURIComponent(currentEditId || '0');
    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.available) {
          setKodeStatus('valid');
        } else {
          setKodeStatus('invalid', kodeDuplicateMsg);
        }
      })
      .catch(function () {
        setKodeStatus('neutral');
      });
  }

  function setFotoPreview(url) {
    if (!fotoImg || !fotoEmpty) return;
    if (url) {
      fotoImg.src = url;
      fotoImg.classList.remove('d-none');
      fotoEmpty.classList.add('d-none');
    } else {
      fotoImg.src = '';
      fotoImg.classList.add('d-none');
      fotoEmpty.classList.remove('d-none');
    }
  }

  function fillEditFromButton(btn, overrides) {
    overrides = overrides || {};
    form.action = btn.getAttribute('data-action');
    currentEditId = btn.getAttribute('data-id') || '';
    originalKode = btn.getAttribute('data-kode') || '';
    kodeEl.value = overrides.kode_barang != null ? overrides.kode_barang : originalKode;
    namaEl.value = overrides.nama_barang != null ? overrides.nama_barang : (btn.getAttribute('data-nama') || '');
    merkEl.value = overrides.merk != null ? overrides.merk : (btn.getAttribute('data-merk') || '');
    satuanEl.value = overrides.satuan != null ? overrides.satuan : (btn.getAttribute('data-satuan') || '');
    hargaEl.value = overrides.harga_satuan != null ? overrides.harga_satuan : (btn.getAttribute('data-harga') || '');
    statusEl.value = overrides.status != null ? overrides.status : (btn.getAttribute('data-status') || 'aktif');
    fotoInput.value = '';
    setFotoPreview(btn.getAttribute('data-foto') || '');
    if (editSubmit) {
      editSubmit.disabled = false;
      editSubmit.dataset.busy = '0';
    }
    if (!overrides.keepKodeError) {
      setKodeStatus('neutral');
    }
  }

  document.querySelectorAll('.bhp-edit-open').forEach(function (btn) {
    btn.addEventListener('click', function () {
      fillEditFromButton(btn);
      var errorBox = document.getElementById('bhpEditErrorBox');
      if (errorBox) {
        errorBox.classList.add('d-none');
      }
      form.querySelectorAll('.invalid-feedback').forEach(function (el) {
        el.classList.add('d-none');
      });
      if (editModal) {
        editModal.show();
      }
    });
  });

  if (kodeEl) {
    kodeEl.addEventListener('input', function () {
      var kode = String(kodeEl.value || '').trim();
      if (kodeCheckTimer) clearTimeout(kodeCheckTimer);
      kodeCheckTimer = setTimeout(function () {
        checkKodeAvailability(kode);
      }, 400);
    });
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      if (editSubmit && editSubmit.dataset.busy === '1') {
        e.preventDefault();
        return;
      }
      if (kodeEl && kodeEl.classList.contains('is-invalid')) {
        e.preventDefault();
        return;
      }
      if (editSubmit) {
        editSubmit.dataset.busy = '1';
        editSubmit.disabled = true;
      }
    });
  }

  if (editModalEl) {
    editModalEl.addEventListener('shown.bs.modal', function () {
      if (kodeEl && kodeEl.classList.contains('is-invalid')) {
        kodeEl.focus();
        kodeEl.select();
        return;
      }
      if (namaEl) {
        namaEl.focus();
        namaEl.select();
      }
    });
  }

  if (fotoInput) {
    fotoInput.addEventListener('change', function () {
      var file = fotoInput.files && fotoInput.files[0];
      if (!file) return;
      var allowed = ['image/jpeg', 'image/png', 'image/webp'];
      if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran foto maksimal 5 MB.');
        fotoInput.value = '';
        return;
      }
      if (allowed.indexOf(file.type) === -1 && !/\.(jpe?g|png|webp)$/i.test(file.name)) {
        alert('Format foto harus JPG, PNG, atau WEBP.');
        fotoInput.value = '';
        return;
      }
      var reader = new FileReader();
      reader.onload = function (e) {
        setFotoPreview(e.target.result);
      };
      reader.readAsDataURL(file);
    });
  }

  @if(session('open_edit_id') && $errors->any())
  (function reopenEditAfterError() {
    var id = @json((string) session('open_edit_id'));
    var btn = document.querySelector('.bhp-edit-open[data-id="' + id + '"]');
    if (!btn || !editModal) return;
    fillEditFromButton(btn, {
      kode_barang: @json(old('kode_barang')),
      nama_barang: @json(old('nama_barang')),
      merk: @json(old('merk')),
      satuan: @json(old('satuan')),
      harga_satuan: @json(old('harga_satuan')),
      status: @json(old('status')),
      keepKodeError: @json($errors->has('kode_barang'))
    });
    @if($errors->has('kode_barang'))
    setKodeStatus('invalid', @json($errors->first('kode_barang')));
    @endif
    editModal.show();
  })();
  @endif

  function newIdempotencyKey() {
    if (window.crypto && crypto.randomUUID) {
      return crypto.randomUUID();
    }
    return Date.now() + '-' + Math.random().toString(16).slice(2);
  }

  var stokForm = document.getElementById('bhpStokForm');
  var stokModal = stokModalEl && window.bootstrap ? new bootstrap.Modal(stokModalEl) : null;
  var stokNama = document.getElementById('bhpStokNama');
  var stokKode = document.getElementById('bhpStokKode');
  var stokSekarangLabel = document.getElementById('bhpStokSekarangLabel');
  var stokJumlah = document.getElementById('bhpStokJumlah');
  var stokCatatan = document.getElementById('bhpStokCatatan');
  var stokIdem = document.getElementById('bhpStokIdem');
  var stokSubmit = document.getElementById('bhpStokSubmit');
  var currentStok = 0;
  var currentSatuan = '';

  function selectedArah() {
    var checked = stokForm ? stokForm.querySelector('input[name="arah"]:checked') : null;
    return checked ? checked.value : 'tambah';
  }

  function updateStokPreview() {
    var raw = stokJumlah ? String(stokJumlah.value || '').trim() : '';
    var jumlah = parseInt(raw, 10);
    var valid = raw !== '' && String(jumlah) === raw && jumlah >= 1;
    var arah = selectedArah();
    var deltaEl = document.getElementById('bhpPrevDelta');
    var afterEl = document.getElementById('bhpPrevSesudah');
    var nowEl = document.getElementById('bhpPrevSekarang');
    if (nowEl) nowEl.textContent = currentStok;
    if (!valid) {
      if (deltaEl) deltaEl.textContent = '—';
      if (afterEl) afterEl.textContent = '—';
      return;
    }
    var delta = arah === 'tambah' ? jumlah : -jumlah;
    var after = currentStok + delta;
    if (deltaEl) deltaEl.textContent = delta > 0 ? '+' + delta : String(delta);
    if (afterEl) {
      afterEl.textContent = after < 0 ? 'Tidak valid (stok negatif)' : String(after);
    }
  }

  function fillStokFromButton(btn, overrides) {
    overrides = overrides || {};
    currentStok = parseInt(btn.getAttribute('data-stok') || '0', 10) || 0;
    currentSatuan = btn.getAttribute('data-satuan') || '';
    stokForm.action = btn.getAttribute('data-action');
    stokNama.textContent = btn.getAttribute('data-nama') || '';
    stokKode.textContent = btn.getAttribute('data-kode') || '';
    stokSekarangLabel.textContent = currentStok + (currentSatuan ? ' ' + currentSatuan : '');
    stokJumlah.value = overrides.jumlah != null ? overrides.jumlah : '';
    stokCatatan.value = overrides.catatan != null ? overrides.catatan : '';
    var arah = overrides.arah || 'tambah';
    var radio = stokForm.querySelector('input[name="arah"][value="' + arah + '"]');
    if (radio) radio.checked = true;
    stokIdem.value = overrides.idempotency_key || newIdempotencyKey();
    if (stokSubmit) {
      stokSubmit.disabled = false;
      stokSubmit.dataset.busy = '0';
    }
    updateStokPreview();
  }

  document.querySelectorAll('.bhp-stok-open').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var errorBox = document.getElementById('bhpStokErrorBox');
      if (errorBox && !errorBox.querySelector('li')) {
        errorBox.classList.add('d-none');
      }
      fillStokFromButton(btn);
      if (stokModal) stokModal.show();
    });
  });

  if (stokJumlah) stokJumlah.addEventListener('input', updateStokPreview);
  document.querySelectorAll('#bhpStokForm input[name="arah"]').forEach(function (el) {
    el.addEventListener('change', updateStokPreview);
  });

  if (stokForm) {
    stokForm.addEventListener('submit', function (e) {
      if (stokSubmit && stokSubmit.dataset.busy === '1') {
        e.preventDefault();
        return;
      }
      var raw = String(stokJumlah.value || '').trim();
      var jumlah = parseInt(raw, 10);
      var catatan = String(stokCatatan.value || '').trim();
      var jumlahErr = document.getElementById('bhpStokJumlahErr');
      var catatanErr = document.getElementById('bhpStokCatatanErr');
      var ok = true;
      if (jumlahErr) {
        jumlahErr.classList.add('d-none');
        jumlahErr.textContent = '';
      }
      if (catatanErr) {
        catatanErr.classList.add('d-none');
        catatanErr.textContent = '';
      }
      if (raw === '' || String(jumlah) !== raw || jumlah < 1) {
        ok = false;
        if (jumlahErr) {
          jumlahErr.textContent = 'Jumlah harus bilangan bulat minimal 1.';
          jumlahErr.classList.remove('d-none');
        }
      } else if (selectedArah() === 'kurangi' && jumlah > currentStok) {
        ok = false;
        if (jumlahErr) {
          jumlahErr.textContent = 'Stok tidak mencukupi. Stok tersedia saat ini: ' + currentStok + '.';
          jumlahErr.classList.remove('d-none');
        }
      }
      if (catatan === '') {
        ok = false;
        if (catatanErr) {
          catatanErr.textContent = 'Alasan/keterangan wajib diisi.';
          catatanErr.classList.remove('d-none');
        }
      }
      if (!ok) {
        e.preventDefault();
        return;
      }
      if (stokSubmit) {
        stokSubmit.dataset.busy = '1';
        stokSubmit.disabled = true;
      }
    });
  }

  @if(session('open_koreksi_id') && $errors->any())
  (function reopenStokAfterError() {
    var id = @json((string) session('open_koreksi_id'));
    var btn = document.querySelector('.bhp-stok-open[data-id="' + id + '"]');
    if (!btn || !stokModal) return;
    fillStokFromButton(btn, {
      jumlah: @json(old('jumlah')),
      catatan: @json(old('catatan')),
      arah: @json(old('arah', 'tambah')),
      idempotency_key: @json(old('idempotency_key'))
    });
    stokModal.show();
  })();
  @endif

  var deleteForm = document.getElementById('bhpDeleteForm');
  var deleteModal = deleteModalEl && window.bootstrap ? new bootstrap.Modal(deleteModalEl) : null;
  var deleteSubmit = document.getElementById('bhpDeleteSubmit');
  document.querySelectorAll('.bhp-delete-open').forEach(function (btn) {
    btn.addEventListener('click', function () {
      deleteForm.action = btn.getAttribute('data-action');
      document.getElementById('bhpDeleteNama').textContent = btn.getAttribute('data-nama') || '';
      document.getElementById('bhpDeleteKode').textContent = btn.getAttribute('data-kode') || '';
      document.getElementById('bhpDeleteMerk').textContent = btn.getAttribute('data-merk') || '—';
      var stok = btn.getAttribute('data-stok') || '0';
      var satuan = btn.getAttribute('data-satuan') || '';
      document.getElementById('bhpDeleteStok').textContent = stok + (satuan ? ' ' + satuan : '');
      document.getElementById('bhpDeleteCntPermintaan').textContent = btn.getAttribute('data-permintaan') || '0';
      document.getElementById('bhpDeleteCntMovement').textContent = btn.getAttribute('data-movements') || '0';
      document.getElementById('bhpDeleteCntOpname').textContent = btn.getAttribute('data-opname') || '0';
      if (deleteSubmit) {
        deleteSubmit.disabled = false;
        deleteSubmit.dataset.busy = '0';
        deleteSubmit.textContent = 'Ya, Hapus Permanen';
      }
      if (deleteModal) deleteModal.show();
    });
  });
  if (deleteForm) {
    deleteForm.addEventListener('submit', function (e) {
      if (deleteSubmit && deleteSubmit.dataset.busy === '1') {
        e.preventDefault();
        return;
      }
      if (deleteSubmit) {
        deleteSubmit.dataset.busy = '1';
        deleteSubmit.disabled = true;
        deleteSubmit.textContent = 'Menghapus...';
      }
    });
  }

  var bulkBtn = document.getElementById('bhpBulkDeleteBtn');
  var bulkForm = document.getElementById('bhpBulkDeleteForm');
  var bulkModal = bulkDeleteModalEl && window.bootstrap ? new bootstrap.Modal(bulkDeleteModalEl) : null;
  var bulkSubmit = document.getElementById('bhpBulkDeleteSubmit');
  if (bulkBtn && bulkForm) {
    bulkBtn.addEventListener('click', function () {
      var checked = document.querySelectorAll('.barang-check:checked');
      if (checked.length === 0) return;
      document.getElementById('bhpBulkDeleteTitle').textContent = 'Hapus ' + checked.length + ' barang?';
      document.getElementById('bhpBulkDeleteLead').textContent = 'Hapus ' + checked.length + ' barang?';
      var list = document.getElementById('bhpBulkDeleteList');
      list.innerHTML = '';
      bulkForm.querySelectorAll('input[name="ids[]"]').forEach(function (el) { el.remove(); });
      checked.forEach(function (cb) {
        var li = document.createElement('li');
        li.textContent = (cb.getAttribute('data-kode') || '') + ' — ' + (cb.getAttribute('data-nama') || '');
        list.appendChild(li);
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = cb.value;
        bulkForm.appendChild(input);
      });
      if (bulkSubmit) {
        bulkSubmit.disabled = false;
        bulkSubmit.dataset.busy = '0';
        bulkSubmit.textContent = 'Ya, Hapus Permanen';
      }
      if (bulkModal) bulkModal.show();
    });
    bulkForm.addEventListener('submit', function (e) {
      if (bulkSubmit && bulkSubmit.dataset.busy === '1') {
        e.preventDefault();
        return;
      }
      if (bulkSubmit) {
        bulkSubmit.dataset.busy = '1';
        bulkSubmit.disabled = true;
        bulkSubmit.textContent = 'Menghapus...';
      }
    });
  }
})();
</script>
@endpush

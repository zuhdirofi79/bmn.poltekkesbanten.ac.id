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
            <td><input type="checkbox" class="barang-check" name="ids[]" value="{{ $b->id }}"></td>
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
              <a href="{{ route('bhp.admin.master-barang.show', $b->id) }}" class="btn btn-outline-secondary btn-sm mb-1">Detail</a>
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
            </td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-center text-muted py-3">Belum ada barang.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $barang->links() }}
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
          <input type="text" id="bhpEditKode" class="form-control" value="" readonly tabindex="-1">
          <small class="text-muted">Kode tidak dapat diubah.</small>
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
        <button type="submit" class="btn btn-prs-blue">Simpan Perubahan</button>
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
  moveToBody(editModalEl);
  moveToBody(fotoModalEl);

  var checkAll = document.getElementById('checkAll');
  if (checkAll) {
    checkAll.addEventListener('change', function () {
      document.querySelectorAll('.barang-check').forEach(function (cb) {
        cb.checked = checkAll.checked;
      });
    });
  }

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
  var namaEl = document.getElementById('bhpEditNama');
  var merkEl = document.getElementById('bhpEditMerk');
  var satuanEl = document.getElementById('bhpEditSatuan');
  var hargaEl = document.getElementById('bhpEditHarga');
  var statusEl = document.getElementById('bhpEditStatus');
  var fotoInput = document.getElementById('bhpEditFoto');
  var fotoImg = document.getElementById('bhpEditFotoImg');
  var fotoEmpty = document.getElementById('bhpEditFotoEmpty');
  var editModal = editModalEl && window.bootstrap ? new bootstrap.Modal(editModalEl) : null;

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
    kodeEl.value = btn.getAttribute('data-kode') || '';
    namaEl.value = overrides.nama_barang != null ? overrides.nama_barang : (btn.getAttribute('data-nama') || '');
    merkEl.value = overrides.merk != null ? overrides.merk : (btn.getAttribute('data-merk') || '');
    satuanEl.value = overrides.satuan != null ? overrides.satuan : (btn.getAttribute('data-satuan') || '');
    hargaEl.value = overrides.harga_satuan != null ? overrides.harga_satuan : (btn.getAttribute('data-harga') || '');
    statusEl.value = overrides.status != null ? overrides.status : (btn.getAttribute('data-status') || 'aktif');
    fotoInput.value = '';
    setFotoPreview(btn.getAttribute('data-foto') || '');
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

  if (editModalEl) {
    editModalEl.addEventListener('shown.bs.modal', function () {
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
      nama_barang: @json(old('nama_barang')),
      merk: @json(old('merk')),
      satuan: @json(old('satuan')),
      harga_satuan: @json(old('harga_satuan')),
      status: @json(old('status'))
    });
    editModal.show();
  })();
  @endif
})();
</script>
@endpush

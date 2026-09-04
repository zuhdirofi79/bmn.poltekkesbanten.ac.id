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
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal{{ $b->id }}">Edit</button>
            </td>
          </tr>

          <div class="modal fade" id="editModal{{ $b->id }}" tabindex="-1">
            <div class="modal-dialog">
              <form method="POST" action="{{ route('bhp.admin.master-barang.update', $b->id) }}" class="modal-content" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">Edit Barang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <div class="mb-2"><label class="form-label">Kode</label><input name="kode_barang" class="form-control" value="{{ $b->kode_barang }}" required></div>
                  <div class="mb-2"><label class="form-label">Nama</label><input name="nama_barang" class="form-control" value="{{ $b->nama_barang }}" required></div>
                  <div class="mb-2"><label class="form-label">Merk (opsional)</label><input name="merk" class="form-control" value="{{ $b->merk }}" maxlength="255"></div>
                  <div class="mb-2"><label class="form-label">Satuan</label><input name="satuan" class="form-control" value="{{ $b->satuan }}" required></div>
                  <div class="mb-2"><label class="form-label">Harga Satuan</label><input name="harga_satuan" type="number" step="0.01" class="form-control" value="{{ $b->harga_satuan }}"></div>
                  <div class="mb-2"><label class="form-label">Foto (opsional)</label>
                    <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <small class="text-muted">JPG, PNG, atau WEBP. Maks. 5 MB.</small>
                  </div>
                  <div class="mb-2"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                      <option value="aktif" @selected($b->status==='aktif')>Aktif</option>
                      <option value="nonaktif" @selected($b->status==='nonaktif')>Nonaktif</option>
                    </select>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="submit" class="btn btn-prs-blue">Simpan</button>
                </div>
              </form>
            </div>
          </div>
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

<div class="modal fade" id="fotoPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Foto Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
})();
</script>
@endpush

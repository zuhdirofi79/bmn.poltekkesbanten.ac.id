@extends('layouts.persediaan_admin')

@section('page_title', 'Sampah Master Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <span class="prs-section-title mb-0">Sampah Master Barang Persediaan</span>
    <p class="text-muted small mb-0 mt-1">Barang yang dihapus sementara akan tersimpan di sini dan dapat dipulihkan atau dihapus permanen.</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-danger btn-sm" id="purgeAllOpen">
      <i class="ti ti-trash"></i> Hapus Permanen Semua Sampah
    </button>
    <a href="{{ route('persediaan.admin.master-barang.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left"></i> Kembali ke Master Data
    </a>
  </div>
</div>

<div id="purgeProgress" class="pa-card" hidden>
  <strong>Sedang Membersihkan Sampah Master Barang</strong>
  <div class="progress my-2" style="height:10px;">
    <div id="purgeProgressBar" class="progress-bar" role="progressbar" style="width:0%"></div>
  </div>
  <div class="small text-muted" id="purgeProgressText">Total: 0</div>
  <div id="purgeDone" hidden>
    <strong>Proses Penghapusan Selesai</strong>
    <ul class="mb-2 small">
      <li>Berhasil dihapus: <span id="purgeOk">0</span></li>
      <li>Gagal: <span id="purgeFail">0</span></li>
      <li>Total diproses: <span id="purgeProcessed">0</span></li>
    </ul>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="purgeShowFailures">Lihat Detail Kegagalan</button>
    <a class="btn btn-outline-secondary btn-sm" id="purgeCsv" href="#">Download Laporan Kegagalan (CSV)</a>
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.location.reload()">Muat Ulang Daftar</button>
    <pre id="purgeFailureList" class="small mt-2 mb-0" hidden></pre>
  </div>
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
    <button type="button" class="btn btn-danger btn-sm" id="purgeSelectedOpen">Hapus Permanen Terpilih</button>
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
                <button type="button" class="btn btn-outline-danger btn-sm js-purge-one"
                        data-id="{{ $b->id }}"
                        data-kode="{{ $b->kode_barang }}"
                        data-nama="{{ $b->nama_barang }}"
                        data-unit="{{ $b->unit_kerja }}"
                        data-deleted="{{ optional($b->deleted_at)->format('d-m-Y H:i') }}"
                        data-history="{{ isset($historyIds[$b->id]) ? '1' : '0' }}">Hapus Permanen</button>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-4">
            <strong>Sampah Kosong</strong>
            <div>Seluruh barang yang telah dihapus sementara sudah dibersihkan.</div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $barang->links('persediaan_admin.master-barang.pagination') }}</div>
</div>

@include('persediaan_admin._delete_modal')

<div class="modal fade pa-delete-modal" id="purgeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header danger">
        <h5 class="modal-title" id="purgeModalTitle">Hapus Permanen Barang?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p id="purgeModalBody" class="mb-2" style="white-space:pre-line;"></p>
        <div id="purgeConfirmWrap" hidden>
          <label class="form-label" for="purgeConfirmInput">Ketik HAPUS PERMANEN untuk melanjutkan</label>
          <input type="text" class="form-control" id="purgeConfirmInput" autocomplete="off">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="purgeModalGo" disabled>Ya, Hapus Permanen</button>
      </div>
    </div>
  </div>
</div>
<input type="hidden" id="purgeCsrf" value="{{ csrf_token() }}">
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

  var csrf = document.getElementById('purgeCsrf');
  var modalEl = document.getElementById('purgeModal');
  var modal = modalEl && typeof bootstrap !== 'undefined' ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
  var mode = 'single';
  var singleId = null;
  var running = false;
  var failureItems = [];

  function selectedIds() {
    var ids = [];
    document.querySelectorAll('.persediaan-trash-checkbox:checked').forEach(function (el) { ids.push(el.value); });
    return ids;
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf ? csrf.value : '',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(body || {})
    }).then(function (response) {
      return response.json().then(function (data) { return { ok: response.ok, status: response.status, data: data }; });
    });
  }

  function openModal(nextMode, body, title, confirmLabel, requirePhrase) {
    mode = nextMode;
    document.getElementById('purgeModalTitle').textContent = title;
    document.getElementById('purgeModalBody').textContent = body;
    document.getElementById('purgeConfirmWrap').hidden = !requirePhrase;
    document.getElementById('purgeConfirmInput').value = '';
    var go = document.getElementById('purgeModalGo');
    go.textContent = confirmLabel;
    go.disabled = !!requirePhrase;
    if (modal) modal.show();
  }

  document.getElementById('purgeConfirmInput').addEventListener('input', function () {
    document.getElementById('purgeModalGo').disabled = this.value !== 'HAPUS PERMANEN';
  });

  document.querySelectorAll('.js-purge-one').forEach(function (btn) {
    btn.addEventListener('click', function () {
      singleId = btn.getAttribute('data-id');
      var history = btn.getAttribute('data-history') === '1'
        ? 'Barang ini mempunyai histori transaksi. Identitas historis akan diarsipkan sebelum master dihapus.'
        : 'Barang ini tidak mempunyai histori transaksi.';
      openModal('single',
        btn.getAttribute('data-nama') + '\n' + btn.getAttribute('data-kode') + '\n' + btn.getAttribute('data-unit') + '\nDihapus sementara: ' + (btn.getAttribute('data-deleted') || '-') + '\n\n' + history + '\n\nTindakan ini tidak dapat dibatalkan. Barang akan dihapus permanen dari master data.',
        'Hapus Permanen Barang?',
        'Ya, Hapus Permanen',
        false);
      document.getElementById('purgeModalGo').disabled = false;
    });
  });

  var selectedOpen = document.getElementById('purgeSelectedOpen');
  if (selectedOpen) {
    selectedOpen.addEventListener('click', function () {
      var ids = selectedIds();
      if (!ids.length) return;
      singleId = null;
      openModal('selected',
        'Anda akan menghapus permanen ' + ids.length + ' barang. Tindakan ini tidak dapat dibatalkan melalui aplikasi. Identitas historis diarsipkan bila barang pernah dipakai transaksi.',
        'Hapus Permanen Terpilih',
        'Ya, Hapus Permanen',
        false);
      document.getElementById('purgeModalGo').disabled = false;
    });
  }

  document.getElementById('purgeAllOpen').addEventListener('click', function () {
    post(@json(route('persediaan.admin.master-barang.trash.purge-preview')), { mode: 'all' }).then(function (res) {
      var data = res.data || {};
      openModal('all',
        'Seluruh barang di Sampah pada akses Anda akan dihapus, termasuk yang tidak tampil di halaman ini.\n\n' +
        'Total: ' + (data.total || 0) + '\n' +
        'Tanpa histori: ' + (data.without_history || 0) + '\n' +
        'Perlu arsip histori: ' + (data.with_history || 0) + '\n' +
        'Belum dapat diproses: ' + (data.unsupported || 0),
        'Hapus Permanen Semua Sampah',
        'Ya, Hapus Permanen Seluruh Sampah',
        true);
    });
  });

  function paint(data) {
    var box = document.getElementById('purgeProgress');
    box.hidden = false;
    var percent = data.percent || 0;
    document.getElementById('purgeProgressBar').style.width = percent + '%';
    document.getElementById('purgeProgressText').textContent =
      'Total: ' + (data.total || 0) + ' barang. Diproses: ' + (data.processed || 0) +
      '. Berhasil dihapus: ' + (data.succeeded || 0) + '. Gagal: ' + (data.failed || 0) + '. Progress: ' + percent + '%';
    if (data.status === 'done') {
      document.getElementById('purgeDone').hidden = false;
      document.getElementById('purgeOk').textContent = String(data.succeeded || 0);
      document.getElementById('purgeFail').textContent = String(data.failed || 0);
      document.getElementById('purgeProcessed').textContent = String(data.processed || 0);
      failureItems = data.failures || [];
      document.getElementById('purgeCsv').href = @json(url('/persediaan/master-barang/trash/purge')) + '/' + data.batch_id + '/failures.csv';
    }
  }

  function run(batchId) {
    running = true;
    post(@json(url('/persediaan/master-barang/trash/purge')) + '/' + batchId + '/step', {}).then(function (res) {
      if (!res.ok) {
        running = false;
        document.getElementById('purgeProgressText').textContent = (res.data && res.data.message) || 'Proses gagal.';
        return;
      }
      paint(res.data);
      if (res.data.status === 'running') {
        run(batchId);
        return;
      }
      running = false;
    });
  }

  document.getElementById('purgeShowFailures').addEventListener('click', function () {
    var list = document.getElementById('purgeFailureList');
    var lines = failureItems.map(function (row) {
      return (row.kode || '') + ' — ' + (row.nama || '') + ': ' + (row.message || '');
    });
    list.textContent = lines.length ? lines.join('\n') : 'Tidak ada kegagalan pada cuplikan ini. Unduh CSV untuk daftar lengkap.';
    list.hidden = false;
  });

  document.getElementById('purgeModalGo').addEventListener('click', function () {
    if (running) return;
    var ids = mode === 'single' ? [singleId] : (mode === 'selected' ? selectedIds() : []);
    var body = { mode: mode, ids: ids };
    if (mode === 'all') body.confirm = document.getElementById('purgeConfirmInput').value;
    if (modal) modal.hide();
    post(@json(route('persediaan.admin.master-barang.trash.purge-start')), body).then(function (res) {
      if (!res.ok) {
        document.getElementById('purgeProgress').hidden = false;
        document.getElementById('purgeProgressText').textContent = (res.data && res.data.message) || 'Proses tidak dapat dimulai.';
        return;
      }
      paint(res.data);
      if (res.data.status === 'running' && res.data.batch_id) run(res.data.batch_id);
    });
  });
})();
</script>
@endpush

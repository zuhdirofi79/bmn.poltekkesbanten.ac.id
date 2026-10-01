@extends('layouts.persediaan_admin')

@section('page_title', 'Input Pembelian Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=1">
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="prs-section-title mb-0">Form Pembelian Barang</span>
  <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
    <i class="ti ti-file-spreadsheet"></i> Import Excel
  </button>
</div>

<form method="POST" action="{{ route('persediaan.admin.pembelian.store') }}" enctype="multipart/form-data" id="pembelianForm">
  @csrf
  <div class="pa-card mt-0">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">No. Faktur</label>
        <input type="text" name="nomor_faktur" value="{{ old('nomor_faktur') }}" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Penyedia</label>
        <input type="text" name="penyedia" value="{{ old('penyedia') }}" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Unit Kerja</label>
        <select name="unit_kerja" id="unitSelect" class="form-select" required>
          <option value="">- pilih -</option>
          @foreach($units as $u)
            <option value="{{ $u }}" @selected(old('unit_kerja') === $u)>{{ UnitKerja::label($u) }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  <div class="pa-card">
    <div class="d-flex justify-content-between align-items-center">
      <h2>Item Barang</h2>
      <div class="d-flex gap-2">
        <input type="text" id="barcodeScan" class="form-control form-control-sm" placeholder="Scan / ketik Code 128" autocomplete="off">
        <button type="button" class="btn btn-outline-primary btn-sm" id="addRow"><i class="ti ti-plus"></i> Tambah Baris</button>
      </div>
    </div>
    <div class="table-responsive mt-2">
      <table class="prs-table" id="itemTable">
        <thead>
          <tr>
            <th style="width:40px;">No</th>
            <th>Barang</th>
            <th style="width:120px;">Kode</th>
            <th style="width:90px;">Satuan</th>
            <th style="width:130px;">Harga Satuan</th>
            <th style="width:90px;">Jumlah</th>
            <th style="width:140px;" class="text-end">Subtotal</th>
            <th style="width:40px;"></th>
          </tr>
        </thead>
        <tbody id="itemBody"></tbody>
        <tfoot>
          <tr>
            <td colspan="6" class="text-end fw-bold">Total</td>
            <td class="text-end fw-bold"><span id="grandTotal">0</span></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <small class="text-muted">Pilih barang dari master agar stok bertambah otomatis. Jika barang tidak terdaftar, isi kode/nama manual (tidak menambah stok).</small>
  </div>

  <div class="pa-card">
    <h2>Upload Dokumen</h2>
    <div class="row g-3 mt-1">
      <div class="col-md-3"><label class="form-label"><i class="ti ti-file-text text-danger"></i> Faktur (PDF)</label>
        <input type="file" name="faktur" accept="application/pdf" class="form-control"></div>
      <div class="col-md-3"><label class="form-label"><i class="ti ti-file-text text-danger"></i> BAST (PDF)</label>
        <input type="file" name="bast" accept="application/pdf" class="form-control"></div>
      <div class="col-md-3"><label class="form-label"><i class="ti ti-file-text text-danger"></i> Invoice (PDF)</label>
        <input type="file" name="invoice" accept="application/pdf" class="form-control"></div>
      <div class="col-md-3"><label class="form-label"><i class="ti ti-photo text-primary"></i> Foto Barang (JPG/PNG)</label>
        <input type="file" name="foto_barang" accept="image/jpeg,image/png" class="form-control"></div>
    </div>
  </div>

  <button type="submit" class="btn btn-prs-green"><i class="ti ti-device-floppy"></i> Simpan Pembelian</button>
</form>

<div class="alert alert-success mt-3">
  <i class="ti ti-info-circle"></i> Pembelian disimpan &rarr; <strong>Stok Bertambah Otomatis</strong>.
</div>

{{-- Modal Import --}}
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('persediaan.admin.pembelian.import') }}" enctype="multipart/form-data" class="modal-content">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Import Pembelian (Excel)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6"><label class="form-label">No. Faktur</label><input type="text" name="nomor_faktur" class="form-control" required></div>
          <div class="col-6"><label class="form-label">Tanggal</label><input type="date" name="tanggal" value="{{ date('Y-m-d') }}" class="form-control" required></div>
          <div class="col-6"><label class="form-label">Penyedia</label><input type="text" name="penyedia" class="form-control"></div>
          <div class="col-6"><label class="form-label">Unit Kerja</label>
            <select name="unit_kerja" class="form-select" required>
              @foreach($units as $u)<option value="{{ $u }}">{{ UnitKerja::label($u) }}</option>@endforeach
            </select>
          </div>
          <div class="col-12"><label class="form-label">File Excel</label>
            <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" required></div>
        </div>
        <a href="{{ route('persediaan.admin.pembelian.import.template') }}" class="small">Unduh template Excel</a>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-blue">Import</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var BARANG = @json($barangJson);
  var body = document.getElementById('itemBody');
  var unitSelect = document.getElementById('unitSelect');
  var idx = 0;

  function fmt(n) { return new Intl.NumberFormat('id-ID').format(Math.round(n)); }

  function barangOptions() {
    var unit = unitSelect.value;
    var list = (BARANG[unit] || []);
    var opts = '<option value="">- pilih / manual -</option>';
    list.forEach(function (b) {
      opts += '<option value="' + b.id + '" data-kode="' + b.kode + '" data-nama="' + b.nama +
              '" data-satuan="' + b.satuan + '" data-harga="' + b.harga + '">' + b.kode + ' - ' + b.nama + '</option>';
    });
    return opts;
  }

  function addRow() {
    var i = idx++;
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td class="rownum"></td>' +
      '<td><select class="form-select form-select-sm sel-barang" name="items[' + i + '][barang_id]">' + barangOptions() + '</select>' +
        '<input type="hidden" class="in-nama" name="items[' + i + '][nama_barang]">' +
        '<input type="hidden" class="in-satuan-h" name="items[' + i + '][satuan]"></td>' +
      '<td><input type="text" class="form-control form-control-sm in-kode" name="items[' + i + '][kode_barang]"></td>' +
      '<td><input type="text" class="form-control form-control-sm in-satuan" readonly></td>' +
      '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm in-harga" name="items[' + i + '][harga_satuan]" value="0"></td>' +
      '<td><input type="number" min="0" class="form-control form-control-sm in-jumlah" name="items[' + i + '][jumlah]" value="0"></td>' +
      '<td class="text-end sub">0</td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger del">&times;</button></td>';
    body.appendChild(tr);
    renumber();
  }

  function renumber() {
    body.querySelectorAll('tr').forEach(function (tr, n) { tr.querySelector('.rownum').textContent = n + 1; });
  }

  function recalc() {
    var total = 0;
    body.querySelectorAll('tr').forEach(function (tr) {
      var harga = parseFloat(tr.querySelector('.in-harga').value) || 0;
      var jumlah = parseInt(tr.querySelector('.in-jumlah').value) || 0;
      var sub = harga * jumlah;
      tr.querySelector('.sub').textContent = fmt(sub);
      total += sub;
    });
    document.getElementById('grandTotal').textContent = fmt(total);
  }

  body.addEventListener('change', function (e) {
    if (e.target.classList.contains('sel-barang')) {
      var opt = e.target.selectedOptions[0];
      var tr = e.target.closest('tr');
      if (opt && opt.value) {
        tr.querySelector('.in-kode').value = opt.dataset.kode || '';
        tr.querySelector('.in-nama').value = opt.dataset.nama || '';
        tr.querySelector('.in-satuan').value = opt.dataset.satuan || '';
        tr.querySelector('.in-satuan-h').value = opt.dataset.satuan || '';
        tr.querySelector('.in-harga').value = opt.dataset.harga || 0;
      }
      recalc();
    }
  });
  body.addEventListener('input', function (e) {
    if (e.target.classList.contains('in-harga') || e.target.classList.contains('in-jumlah')) recalc();
    if (e.target.classList.contains('in-satuan')) {
      e.target.closest('tr').querySelector('.in-satuan-h').value = e.target.value;
    }
  });
  body.addEventListener('click', function (e) {
    if (e.target.classList.contains('del')) { e.target.closest('tr').remove(); renumber(); recalc(); }
  });
  unitSelect.addEventListener('change', function () {
    body.querySelectorAll('.sel-barang').forEach(function (sel) { sel.innerHTML = barangOptions(); });
    recalc();
  });

  document.getElementById('addRow').addEventListener('click', addRow);
  addRow();

  var scan = document.getElementById('barcodeScan');
  if (scan) {
    scan.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      var code = scan.value.trim();
      if (!code) return;
      var unit = unitSelect.value;
      fetch("{{ route('persediaan.admin.master-barang.lookup-barcode') }}?code=" + encodeURIComponent(code) + (unit ? '&unit=' + encodeURIComponent(unit) : ''), {
        headers: { 'Accept': 'application/json' }
      }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (!res.ok || !res.j.ok) {
            alert((res.j && res.j.message) ? res.j.message : 'Barcode tidak dikenali.');
            return;
          }
          var b = res.j.barang;
          if (unit && b.unit_kerja !== unit) {
            alert('Barcode milik unit ' + b.unit_kerja + ', bukan unit yang dipilih.');
            return;
          }
          if (!unit) {
            unitSelect.value = b.unit_kerja;
            body.querySelectorAll('.sel-barang').forEach(function (sel) { sel.innerHTML = barangOptions(); });
          }
          addRow();
          var tr = body.lastElementChild;
          var sel = tr.querySelector('.sel-barang');
          sel.value = String(b.id);
          sel.dispatchEvent(new Event('change', { bubbles: true }));
          tr.querySelector('.in-jumlah').value = 1;
          recalc();
          scan.value = '';
          scan.focus();
        }).catch(function () { alert('Gagal membaca barcode.'); });
    });
  }
})();
</script>
@endpush

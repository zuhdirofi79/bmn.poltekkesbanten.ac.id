@extends('layouts.persediaan_admin')

@section('page_title', 'Input Pembelian Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=1">
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <span class="prs-section-title mb-0">Form Pembelian Barang</span>
  <div class="d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
      <i class="ti ti-file-upload"></i> Import Excel
    </button>
    <a href="{{ route('persediaan.admin.pembelian.import.template') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-download"></i> Download Template Excel
    </a>
    <a href="{{ route('persediaan.admin.pembelian.index') }}" class="btn btn-outline-secondary btn-sm">Daftar Pembelian</a>
  </div>
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h2 class="mb-0">Item Barang</h2>
      <div class="d-flex flex-wrap gap-2">
        <input type="text" id="barcodeScan" class="form-control form-control-sm" placeholder="Scan / ketik Code 128" autocomplete="off">
        <button type="button" class="btn btn-outline-primary btn-sm" id="addRow"><i class="ti ti-plus"></i> Tambah Baris</button>
      </div>
    </div>
    <div class="table-responsive mt-2">
      <table class="prs-table" id="itemTable">
        <thead>
          <tr>
            <th style="width:40px;">No</th>
            <th style="width:160px;">Mode</th>
            <th>Barang / Nama</th>
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
            <td colspan="7" class="text-end fw-bold">Total</td>
            <td class="text-end fw-bold"><span id="grandTotal">0</span></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <small class="text-muted">Pilih dari Master agar stok bertambah. Input Manual menyimpan item tanpa mengubah stok master, meskipun kodenya sama dengan master.</small>
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

  <button type="submit" class="btn btn-prs-green" id="savePembelianBtn"><i class="ti ti-device-floppy"></i> Simpan Pembelian</button>
</form>

<div class="alert alert-success mt-3">
  <i class="ti ti-info-circle"></i> Barang dari master menambah stok satu kali. Barang input manual tersimpan tanpa mengubah stok master.
</div>

@include('persediaan_admin.pembelian.partials.import-modal')
@endsection

@push('scripts')
<script>
(function () {
  var BARANG = @json($barangJson);
  var OLD = @json(array_values(old('items', [])));
  var body = document.getElementById('itemBody');
  var unitSelect = document.getElementById('unitSelect');
  var idx = 0;

  function fmt(n) { return new Intl.NumberFormat('id-ID').format(Math.round(n)); }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
    });
  }

  function barangOptions(selected) {
    var unit = unitSelect.value;
    var list = (BARANG[unit] || []);
    var opts = '<option value="">- pilih barang -</option>';
    list.forEach(function (b) {
      opts += '<option value="' + esc(b.id) + '" data-kode="' + esc(b.kode) + '" data-nama="' + esc(b.nama) +
        '" data-satuan="' + esc(b.satuan) + '" data-harga="' + esc(b.harga) + '"' +
        (String(selected || '') === String(b.id) ? ' selected' : '') + '>' + esc(b.kode) + ' - ' + esc(b.nama) + '</option>';
    });
    return opts;
  }

  function setMode(tr, mode) {
    var manual = mode === 'manual';
    tr.querySelector('.mode-select').value = manual ? 'manual' : 'master';
    tr.querySelector('.sel-barang').classList.toggle('d-none', manual);
    tr.querySelector('.sel-barang').disabled = manual;
    tr.querySelector('.in-nama').classList.toggle('d-none', !manual);
    tr.querySelector('.in-kode').readOnly = !manual;
    tr.querySelector('.in-satuan').readOnly = !manual;
    if (manual) {
      tr.querySelector('.sel-barang').value = '';
      tr.querySelector('.in-barang-id').value = '';
    }
  }

  function fillMaster(tr) {
    var sel = tr.querySelector('.sel-barang');
    var opt = sel.selectedOptions[0];
    var hidden = tr.querySelector('.in-barang-id');
    if (!opt || !opt.value) {
      hidden.value = '';
      return;
    }
    hidden.value = opt.value;
    tr.querySelector('.in-kode').value = opt.dataset.kode || '';
    tr.querySelector('.in-nama').value = opt.dataset.nama || '';
    tr.querySelector('.in-satuan').value = opt.dataset.satuan || '';
    tr.querySelector('.in-harga').value = opt.dataset.harga || 0;
  }

  function addRow(data) {
    data = data || {};
    var i = idx++;
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td class="rownum"></td>' +
      '<td><select class="form-select form-select-sm mode-select" name="items[' + i + '][mode]">' +
        '<option value="master">Pilih dari Master</option>' +
        '<option value="manual">Input Manual</option></select></td>' +
      '<td><select class="form-select form-select-sm sel-barang">' + barangOptions(data.barang_id) + '</select>' +
        '<input type="hidden" class="in-barang-id" name="items[' + i + '][barang_id]">' +
        '<input type="text" class="form-control form-control-sm in-nama d-none" name="items[' + i + '][nama_barang]" placeholder="Nama barang"></td>' +
      '<td><input type="text" class="form-control form-control-sm in-kode" name="items[' + i + '][kode_barang]"></td>' +
      '<td><input type="text" class="form-control form-control-sm in-satuan" name="items[' + i + '][satuan]"></td>' +
      '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm in-harga" name="items[' + i + '][harga_satuan]" value="0"></td>' +
      '<td><input type="number" min="0" step="1" class="form-control form-control-sm in-jumlah" name="items[' + i + '][jumlah]" value="0"></td>' +
      '<td class="text-end sub">0</td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger del">&times;</button></td>';
    body.appendChild(tr);
    tr.querySelector('.in-kode').value = data.kode_barang || '';
    tr.querySelector('.in-nama').value = data.nama_barang || '';
    tr.querySelector('.in-satuan').value = data.satuan || '';
    tr.querySelector('.in-harga').value = data.harga_satuan != null ? data.harga_satuan : 0;
    tr.querySelector('.in-jumlah').value = data.jumlah != null ? data.jumlah : 0;
    setMode(tr, data.mode === 'manual' ? 'manual' : 'master');
    if (data.mode !== 'manual' && data.barang_id) {
      tr.querySelector('.sel-barang').value = String(data.barang_id);
      fillMaster(tr);
      if (data.harga_satuan != null) tr.querySelector('.in-harga').value = data.harga_satuan;
      if (data.jumlah != null) tr.querySelector('.in-jumlah').value = data.jumlah;
    }
    renumber();
    recalc();
    return tr;
  }

  function renumber() {
    body.querySelectorAll('tr').forEach(function (tr, n) { tr.querySelector('.rownum').textContent = n + 1; });
  }

  function recalc() {
    var total = 0;
    body.querySelectorAll('tr').forEach(function (tr) {
      var harga = parseFloat(tr.querySelector('.in-harga').value) || 0;
      var jumlah = parseInt(tr.querySelector('.in-jumlah').value, 10) || 0;
      var sub = harga * jumlah;
      tr.querySelector('.sub').textContent = fmt(sub);
      total += sub;
    });
    document.getElementById('grandTotal').textContent = fmt(total);
  }

  body.addEventListener('change', function (e) {
    var tr = e.target.closest('tr');
    if (!tr) return;
    if (e.target.classList.contains('mode-select')) {
      setMode(tr, e.target.value);
      if (e.target.value === 'master') fillMaster(tr);
    }
    if (e.target.classList.contains('sel-barang')) fillMaster(tr);
    recalc();
  });
  body.addEventListener('input', function (e) {
    if (e.target.classList.contains('in-harga') || e.target.classList.contains('in-jumlah')) recalc();
  });
  body.addEventListener('click', function (e) {
    if (e.target.classList.contains('del')) {
      e.target.closest('tr').remove();
      renumber();
      recalc();
    }
  });
  unitSelect.addEventListener('change', function () {
    body.querySelectorAll('tr').forEach(function (tr) {
      if (tr.querySelector('.mode-select').value !== 'master') return;
      var current = tr.querySelector('.in-barang-id').value;
      tr.querySelector('.sel-barang').innerHTML = barangOptions(current);
      fillMaster(tr);
    });
    recalc();
  });

  document.getElementById('addRow').addEventListener('click', function () { addRow(); });
  if (OLD.length) OLD.forEach(function (row) { addRow(row); });
  else addRow();

  document.getElementById('pembelianForm').addEventListener('submit', function () {
    var btn = document.getElementById('savePembelianBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan...';
  });

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
          if (!unit) unitSelect.value = b.unit_kerja;
          var tr = addRow({ mode: 'master', barang_id: b.id, jumlah: 1 });
          if (!tr.querySelector('.sel-barang').value) {
            tr.querySelector('.sel-barang').innerHTML = barangOptions(b.id);
            tr.querySelector('.sel-barang').value = String(b.id);
            fillMaster(tr);
            tr.querySelector('.in-jumlah').value = 1;
            recalc();
          }
          scan.value = '';
          scan.focus();
        }).catch(function () { alert('Gagal membaca barcode.'); });
    });
  }
})();
</script>
@endpush

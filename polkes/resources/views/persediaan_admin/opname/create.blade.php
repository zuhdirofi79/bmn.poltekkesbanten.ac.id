@extends('layouts.persediaan_admin')

@section('page_title', 'Input Stock Opname')

@php use App\Support\Persediaan\UnitKerja; @endphp

@section('content')
<div class="pa-card mt-0">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Pilih Unit Kerja</label>
      <select name="unit" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($units as $u)<option value="{{ $u }}" @selected($unit === $u)>{{ UnitKerja::label($u) }}</option>@endforeach
      </select>
    </div>
  </form>
</div>

<form method="POST" action="{{ route('persediaan.admin.opname.store') }}">
  @csrf
  <input type="hidden" name="unit_kerja" value="{{ $unit }}">

  <div class="pa-card">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Tanggal Opname</label>
        <input type="date" name="tanggal_opname" value="{{ date('Y-m-d') }}" class="form-control" required>
      </div>
      <div class="col-md-9">
        <label class="form-label">Catatan</label>
        <input type="text" name="catatan" class="form-control" placeholder="Catatan opname (opsional)">
      </div>
    </div>

    <div class="table-responsive mt-3">
      <table class="prs-table" id="opnameTable">
        <thead>
          <tr>
            <th>Kode Barang</th><th>Nama Barang</th>
            <th class="text-end">Stok Sistem</th><th>Stok Fisik</th>
            <th class="text-end">Selisih</th><th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $i => $r)
            <tr data-row>
              <td><code>{{ $r['kode'] }}</code>
                <input type="hidden" name="items[{{ $i }}][barang_id]" value="{{ $r['id'] }}">
                <input type="hidden" name="items[{{ $i }}][stok_sistem]" value="{{ $r['sistem'] }}" class="v-sistem">
              </td>
              <td>{{ $r['nama'] }}</td>
              <td class="text-end">{{ number_format($r['sistem'], 0, ',', '.') }}</td>
              <td><input type="number" min="0" name="items[{{ $i }}][stok_fisik]" value="{{ $r['sistem'] }}" class="form-control form-control-sm v-fisik"></td>
              <td class="text-end v-selisih">0</td>
              <td><input type="text" name="items[{{ $i }}][keterangan]" class="form-control form-control-sm"></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada barang untuk unit ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="pa-card">
    <h2>Rekap Hasil Opname</h2>
    <div class="pa-rekap mt-2">
      <div class="box kurang"><div class="n" id="rKurang">0</div><div class="lbl">Selisih Kurang</div></div>
      <div class="box lebih"><div class="n" id="rLebih">0</div><div class="lbl">Selisih Lebih</div></div>
    </div>
  </div>

  <button type="submit" class="btn btn-prs-green"><i class="ti ti-device-floppy"></i> Simpan Opname</button>
</form>
@endsection

@push('scripts')
<script>
(function () {
  var table = document.getElementById('opnameTable');
  if (!table) return;

  function recalc() {
    var kurang = 0, lebih = 0;
    table.querySelectorAll('tr[data-row]').forEach(function (tr) {
      var sistem = parseInt(tr.querySelector('.v-sistem').value) || 0;
      var fisik = parseInt(tr.querySelector('.v-fisik').value);
      if (isNaN(fisik)) fisik = sistem;
      var selisih = fisik - sistem;
      var cell = tr.querySelector('.v-selisih');
      cell.textContent = (selisih > 0 ? '+' : '') + selisih;
      cell.style.color = selisih < 0 ? '#d9534f' : (selisih > 0 ? '#2e9e5b' : '#6b7a90');

      if (selisih < 0) { kurang++; }
      else if (selisih > 0) { lebih++; }
    });
    document.getElementById('rKurang').textContent = kurang;
    document.getElementById('rLebih').textContent = lebih;
  }

  table.addEventListener('input', recalc);
  table.addEventListener('change', recalc);
  recalc();
})();
</script>
@endpush

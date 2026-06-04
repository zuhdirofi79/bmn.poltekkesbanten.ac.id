@php use App\Support\Persediaan\UnitKerja; $edit = $edit ?? false; @endphp
<div class="row g-2">
  <div class="col-6">
    <label class="form-label">Kode Barang</label>
    <input type="text" name="kode_barang" class="form-control" required>
  </div>
  <div class="col-6">
    <label class="form-label">Nama Barang</label>
    <input type="text" name="nama_barang" class="form-control" required>
  </div>
  <div class="col-6">
    <label class="form-label">Unit Kerja</label>
    <select name="unit_kerja" class="form-select" required>
      @foreach($units as $u)<option value="{{ $u }}">{{ UnitKerja::label($u) }}</option>@endforeach
    </select>
  </div>
  <div class="col-6">
    <label class="form-label">Satuan</label>
    <input type="text" name="satuan" class="form-control" value="Buah" required>
  </div>
  <div class="col-4">
    <label class="form-label">Stok Awal</label>
    <input type="number" min="0" name="stok_awal" class="form-control" value="0" required>
  </div>
  <div class="col-4">
    <label class="form-label">Stok Minimum</label>
    <input type="number" min="0" name="stok_minimum" class="form-control" value="0" required>
  </div>
  <div class="col-4">
    <label class="form-label">Harga Satuan</label>
    <input type="number" min="0" step="0.01" name="harga_satuan" class="form-control" value="0" required>
  </div>
  @if($edit)
    <div class="col-12">
      <div class="form-check mt-2">
        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="editActive" checked>
        <label class="form-check-label" for="editActive">Aktif</label>
      </div>
    </div>
  @endif
</div>

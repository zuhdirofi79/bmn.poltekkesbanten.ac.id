@extends('layouts.persediaan_admin')

@section('page_title', 'Master Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="prs-section-title mb-0">Master Barang Persediaan</span>
  <button class="btn btn-prs-green btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="ti ti-plus"></i> Tambah Barang
  </button>
</div>

<div class="pa-card mt-0">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Unit Kerja</label>
      <select name="unit" class="form-select form-select-sm">
        <option value="">Semua Unit</option>
        @foreach($units as $u)<option value="{{ $u }}" @selected(($filters['unit'] ?? '') === $u)>{{ UnitKerja::label($u) }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Kode / Nama</label>
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2"><button class="btn btn-prs-blue btn-sm w-100"><i class="ti ti-filter"></i> Filter</button></div>
  </form>
</div>

<div class="pa-card">
  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr><th>Kode</th><th>Nama</th><th>Unit</th><th>Satuan</th><th class="text-end">Stok Awal</th><th class="text-end">Min</th><th class="text-end">Harga</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($barang as $b)
          <tr>
            <td><code>{{ $b->kode_barang }}</code></td>
            <td>{{ $b->nama_barang }}</td>
            <td>{{ $b->unit_kerja }}</td>
            <td>{{ $b->satuan }}</td>
            <td class="text-end">{{ number_format($b->stok_awal, 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($b->stok_minimum, 0, ',', '.') }}</td>
            <td class="text-end">Rp {{ number_format($b->harga_satuan, 0, ',', '.') }}</td>
            <td>@if($b->is_active)<span class="prs-badge selesai">Aktif</span>@else<span class="prs-badge ditolak">Nonaktif</span>@endif</td>
            <td class="text-end">
              <button class="btn btn-outline-primary btn-sm"
                      data-bs-toggle="modal" data-bs-target="#editModal"
                      data-id="{{ $b->id }}" data-kode="{{ $b->kode_barang }}" data-nama="{{ $b->nama_barang }}"
                      data-unit="{{ $b->unit_kerja }}" data-satuan="{{ $b->satuan }}"
                      data-awal="{{ $b->stok_awal }}" data-min="{{ $b->stok_minimum }}"
                      data-harga="{{ $b->harga_satuan }}" data-active="{{ $b->is_active ? 1 : 0 }}">
                <i class="ti ti-edit"></i>
              </button>
              <form action="{{ route('persediaan.admin.master-barang.destroy', $b->id) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('Nonaktifkan barang ini?');">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm"><i class="ti ti-trash"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-3">Belum ada barang.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $barang->links() }}</div>
</div>

{{-- Modal Tambah --}}
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('persediaan.admin.master-barang.store') }}" class="modal-content">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Tambah Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        @include('persediaan_admin.master-barang._fields', ['units' => $units])
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-green">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit --}}
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="editForm" class="modal-content">
      @csrf @method('PUT')
      <div class="modal-header"><h5 class="modal-title">Edit Barang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        @include('persediaan_admin.master-barang._fields', ['units' => $units, 'edit' => true])
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-prs-blue">Perbarui</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var editModal = document.getElementById('editModal');
  if (!editModal) return;
  editModal.addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget;
    var f = document.getElementById('editForm');
    f.action = "{{ url('persediaan/master-barang') }}/" + b.dataset.id;
    f.querySelector('[name=kode_barang]').value = b.dataset.kode;
    f.querySelector('[name=nama_barang]').value = b.dataset.nama;
    f.querySelector('[name=unit_kerja]').value = b.dataset.unit;
    f.querySelector('[name=satuan]').value = b.dataset.satuan;
    f.querySelector('[name=stok_awal]').value = b.dataset.awal;
    f.querySelector('[name=stok_minimum]').value = b.dataset.min;
    f.querySelector('[name=harga_satuan]').value = b.dataset.harga;
    var act = f.querySelector('[name=is_active]');
    if (act) act.checked = b.dataset.active === '1';
  });
})();
</script>
@endpush

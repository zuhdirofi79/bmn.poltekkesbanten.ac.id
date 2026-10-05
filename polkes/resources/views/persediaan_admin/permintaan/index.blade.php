@extends('layouts.persediaan_admin')

@section('page_title', 'Permintaan Barang')

@php use App\Support\Persediaan\UnitKerja; use App\Models\Persediaan\PersediaanPermintaan as P; @endphp

@section('content')
<div class="pa-card mt-0">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Unit Kerja</label>
      @if(!empty($lockedUnit))
        <input type="hidden" name="unit" value="{{ $lockedUnit }}">
        <select class="form-select form-select-sm" disabled>
          <option selected>{{ UnitKerja::label($lockedUnit) }}</option>
        </select>
      @else
        <select name="unit" class="form-select form-select-sm">
          <option value="">Semua Unit</option>
          @foreach($units as $u)
            <option value="{{ $u }}" @selected(request('unit') === $u)>{{ UnitKerja::label($u) }}</option>
          @endforeach
        </select>
      @endif
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Semua Status</option>
        @foreach(P::STATUS_LABEL as $val => $lbl)
          <option value="{{ $val }}" @selected(request('status') === $val)>{{ $lbl }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Tampilan</label>
      <select name="lifecycle" class="form-select form-select-sm">
        <option value="aktif" @selected(($lifecycle ?? 'aktif') === 'aktif')>Data Aktif</option>
        <option value="sampah" @selected(($lifecycle ?? '') === 'sampah')>Soft Deleted</option>
        <option value="semua" @selected(($lifecycle ?? '') === 'semua')>Semua Data</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Cari Nomor</label>
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="PRM-...">
    </div>
    <div class="col-md-2">
      <button class="btn btn-prs-blue btn-sm w-100"><i class="ti ti-filter"></i> Filter</button>
    </div>
  </form>
</div>

<div class="pa-card">
  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <span class="small text-muted"><span id="permintaanSelectedCount">0</span> permintaan dipilih</span>
  </div>

  <div id="permintaanBulkBar" class="pa-bulk-bar" hidden>
    <strong id="permintaanBulkLabel">0 permintaan dipilih</strong>
    @if(($lifecycle ?? 'aktif') !== 'sampah')
      <button type="button" class="btn btn-warning btn-sm js-pa-bulk"
              data-checkbox=".persediaan-permintaan-checkbox"
              data-url="{{ route('persediaan.admin.permintaan.bulk-delete') }}"
              data-method="POST"
              data-tone="warn"
              data-title="Soft Delete {n} Permintaan?"
              data-message="Permintaan yang dipilih akan disembunyikan dari daftar utama tetapi tetap tersimpan untuk keperluan administrasi."
              data-confirm="Ya, Soft Delete">Soft Delete Terpilih</button>
    @endif
    @if(($lifecycle ?? 'aktif') !== 'aktif')
      <button type="button" class="btn btn-outline-success btn-sm js-pa-bulk"
              data-checkbox=".persediaan-permintaan-checkbox"
              data-url="{{ route('persediaan.admin.permintaan.bulk-restore') }}"
              data-method="POST"
              data-tone="warn"
              data-title="Pulihkan {n} Permintaan?"
              data-message="Permintaan yang dipilih akan dikembalikan ke daftar utama."
              data-confirm="Ya, Pulihkan"
              data-busy-label="Memulihkan...">Pulihkan Terpilih</button>
    @endif
    <button type="button" class="btn btn-danger btn-sm js-pa-bulk"
            data-checkbox=".persediaan-permintaan-checkbox"
            data-url="{{ route('persediaan.admin.permintaan.bulk-force-delete') }}"
            data-method="POST"
            data-tone="danger"
            data-title="Hapus Permanen {n} Permintaan?"
            data-message="Tindakan ini tidak dapat dibatalkan. Permintaan yang sudah memengaruhi stok tidak akan dihapus."
            data-confirm="Ya, Hapus Permanen">Hapus Permanen Terpilih</button>
  </div>

  <div class="table-responsive">
    <table class="pa-table">
      <thead>
        <tr>
          <th width="40" class="text-center">
            <input type="checkbox" id="checkAllPermintaan" aria-label="Pilih semua permintaan di halaman ini">
          </th>
          <th>Nomor</th><th>Pemohon</th><th>Unit</th><th>Tanggal</th><th>Item</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($permintaan as $p)
          <tr>
            <td class="text-center">
              <input type="checkbox" class="persediaan-permintaan-checkbox" value="{{ $p->id }}" aria-label="Pilih {{ $p->nomor_permintaan }}">
            </td>
            <td><code>{{ $p->nomor_permintaan }}</code></td>
            <td>{{ optional($p->pemohon)->nama ?? '-' }}</td>
            <td>{{ $p->unit_kerja }}</td>
            <td>{{ optional($p->tanggal_permintaan)->format('d-m-Y') }}</td>
            <td>{{ $p->items_count }}</td>
            <td>
              @if($p->trashed())<span class="prs-badge ditolak">Soft Deleted</span>@endif
              <span class="prs-badge {{ $p->status }}">{{ $p->statusLabel() }}</span>
            </td>
            <td class="text-end">
              <div class="pa-row-actions">
                <a href="{{ route('persediaan.admin.permintaan.show', $p->id) }}" class="btn btn-outline-primary btn-sm">Detail</a>
                <div class="dropdown">
                  <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown"
                          data-bs-popper-config='{"strategy":"fixed"}' aria-label="Aksi {{ $p->nomor_permintaan }}">
                    <i class="ti ti-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    @unless($p->trashed())
                      <li><button type="button" class="dropdown-item js-pa-action"
                                  data-url="{{ route('persediaan.admin.permintaan.destroy', $p->id) }}"
                                  data-method="DELETE" data-tone="warn" data-title="Soft Delete Permintaan"
                                  data-subject="{{ $p->nomor_permintaan }}"
                                  data-message="Permintaan akan disembunyikan dari daftar utama tetapi tetap tersimpan untuk keperluan administrasi. Lanjutkan?"
                                  data-confirm="Ya, Soft Delete">Soft Delete</button></li>
                    @else
                      <li><button type="button" class="dropdown-item js-pa-action"
                                  data-url="{{ route('persediaan.admin.permintaan.restore', $p->id) }}"
                                  data-method="POST" data-tone="warn" data-title="Pulihkan Permintaan"
                                  data-subject="{{ $p->nomor_permintaan }}"
                                  data-message="Permintaan akan dikembalikan ke daftar utama. Status transaksi tidak diubah."
                                  data-confirm="Ya, Pulihkan" data-busy-label="Memulihkan...">Restore</button></li>
                    @endunless
                    <li><button type="button" class="dropdown-item text-danger js-pa-action"
                                data-url="{{ route('persediaan.admin.permintaan.force-destroy', $p->id) }}"
                                data-method="DELETE" data-tone="danger" data-title="Hapus Permanen Permintaan"
                                data-subject="{{ $p->nomor_permintaan }}"
                                data-message="PERINGATAN: Permintaan yang sudah menghasilkan transaksi barang keluar tidak akan dihapus, agar stok dan audit trail tetap utuh. Tindakan ini tidak dapat dibatalkan."
                                data-confirm="Hapus Permanen">Permanent Delete</button></li>
                  </ul>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada permintaan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $permintaan->links() }}</div>
</div>

@include('persediaan_admin._delete_modal')
@endsection

@push('scripts')
<script>
(function () {
  var checkAll = document.getElementById('checkAllPermintaan');
  var counter = document.getElementById('permintaanSelectedCount');
  var bulkBar = document.getElementById('permintaanBulkBar');
  var bulkLabel = document.getElementById('permintaanBulkLabel');

  function rows() {
    return document.querySelectorAll('.persediaan-permintaan-checkbox');
  }

  function update() {
    var checked = document.querySelectorAll('.persediaan-permintaan-checkbox:checked');
    var total = rows().length;
    if (counter) counter.textContent = String(checked.length);
    if (bulkLabel) bulkLabel.textContent = checked.length + ' permintaan dipilih';
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
})();
</script>
@endpush

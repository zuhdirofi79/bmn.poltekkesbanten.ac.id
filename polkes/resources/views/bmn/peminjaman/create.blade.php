@extends('layouts.thema')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-semibold mb-0">
      <i class="ti ti-clipboard-plus"></i> Ajukan Peminjaman Aset BMN
    </h5>
    <a href="{{ route('bmn.peminjaman-aset.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left"></i> Kembali
    </a>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{--
    Form filter aset (GET) sengaja diletakkan TERPISAH dari form POST utama,
    sehingga tidak terjadi nested form (HTML5 melarangnya). Inputs filter di
    bawah me-reference form ini lewat atribut HTML5 `form="filterAsetForm"`,
    sehingga visually tetap berada di dalam card "Pilih Aset", tapi secara
    semantik HANYA submit ke endpoint GET create — tidak akan men-trigger
    validasi `aset_ids` milik form pengajuan.
  --}}
  <form id="filterAsetForm" method="GET" action="{{ route('bmn.peminjaman-aset.create') }}" class="d-none" aria-hidden="true"></form>

  <form id="peminjamanForm" method="POST" action="{{ route('bmn.peminjaman-aset.store') }}" enctype="multipart/form-data">
    @csrf

    <div class="card mb-3">
      <div class="card-body">
        <h6 class="fw-semibold mb-3">1. Detail Pengajuan</h6>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Tanggal Pinjam <span class="text-danger">*</span></label>
            <input type="date" name="tanggal_pinjam" required
                   value="{{ old('tanggal_pinjam', now()->toDateString()) }}"
                   class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label">Rencana Kembali <span class="text-danger">*</span></label>
            <input type="date" name="rencana_kembali" required
                   value="{{ old('rencana_kembali') }}"
                   class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label">Tujuan Peminjaman <span class="text-danger">*</span></label>
            <input type="text" name="tujuan_peminjaman" required maxlength="255"
                   value="{{ old('tujuan_peminjaman') }}"
                   class="form-control form-control-sm"
                   placeholder="Mis. Praktikum Mata Kuliah A">
          </div>
          <div class="col-12">
            <label class="form-label">Catatan</label>
            <textarea name="catatan" rows="2" maxlength="2000"
                      class="form-control form-control-sm"
                      placeholder="Catatan tambahan (opsional)">{{ old('catatan') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <h6 class="fw-semibold mb-0">2. Pilih Aset <span class="text-danger">*</span></h6>
          <div class="small text-muted">
            <span id="selectedCount">0</span> aset dipilih
            &middot; <strong>{{ $asetList->total() }}</strong> aset cocok filter
          </div>
        </div>

        {{--
          Inputs filter di bawah TIDAK termasuk dalam form POST utama,
          karena masing-masing memakai atribut `form="filterAsetForm"`.
          Tombol Filter pun di-attach ke form GET filterAsetForm, jadi
          klik Filter HANYA reload halaman create dengan query string —
          tidak ikut submit form pengajuan.
        --}}
        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <input type="text" name="q"
                   form="filterAsetForm"
                   class="form-control form-control-sm"
                   value="{{ $filters['q'] ?? '' }}"
                   placeholder="Cari: kode_barang, nama, NUP, lokasi, satker, merk, pengguna">
          </div>
          <div class="col-md-2">
            <select name="kategori_id" form="filterAsetForm" class="form-control form-control-sm">
              <option value="">— Kategori —</option>
              @foreach($kategoriList as $kat)
                <option value="{{ $kat->id }}" {{ (string)($filters['kategori_id'] ?? '') === (string)$kat->id ? 'selected' : '' }}>
                  {{ $kat->nama_kategori }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <select name="kondisi" form="filterAsetForm" class="form-control form-control-sm">
              <option value="">— Kondisi —</option>
              @foreach(['baik','rusak_ringan','rusak_berat'] as $k)
                <option value="{{ $k }}" {{ ($filters['kondisi'] ?? '') === $k ? 'selected' : '' }}>
                  {{ ucfirst(str_replace('_',' ',$k)) }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <select name="satker_id" form="filterAsetForm" class="form-control form-control-sm">
              <option value="">— Satker —</option>
              @foreach($satkers as $s)
                <option value="{{ $s->id }}" {{ (string)($filters['satker_id'] ?? '') === (string)$s->id ? 'selected' : '' }}>
                  {{ $s->nama }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 d-flex gap-1">
            <button type="submit" form="filterAsetForm" class="btn btn-outline-primary btn-sm">
              <i class="ti ti-search"></i> Filter
            </button>
            <a href="{{ route('bmn.peminjaman-aset.create') }}"
               class="btn btn-outline-secondary btn-sm"
               title="Reset filter">
              <i class="ti ti-refresh"></i>
            </a>
          </div>
        </div>

        <div class="alert alert-info py-2 small mb-2">
          <i class="ti ti-info-circle"></i>
          Hanya aset berstatus <strong>aktif</strong> dan <strong>tidak sedang dipinjam</strong> yang ditampilkan.
        </div>

        <div class="table-responsive" style="max-height: 480px;">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light" style="position: sticky; top:0; z-index:5;">
              <tr>
                <th width="40" class="text-center">
                  <input type="checkbox" id="checkAll" aria-label="Pilih semua">
                </th>
                <th width="70">Foto</th>
                <th>Kode Barang</th>
                <th>NUP</th>
                <th>Nama Barang</th>
                <th>Merk</th>
                <th>Kategori</th>
                <th>Kondisi</th>
                <th>Lokasi</th>
                <th>Satker</th>
                <th>Pengguna</th>
              </tr>
            </thead>
            <tbody>
              @forelse($asetList as $a)
                @php
                  $fotoList = [];
                  if (is_array($a->foto) && count($a->foto) > 0) { $fotoList = $a->foto; }
                  $thumb = '';
                  if (!empty($fotoList[0])) {
                    $tb = $fotoList[0]['thumb'] ?? $fotoList[0]['full'] ?? '';
                    if ($tb !== '') {
                      $thumb = preg_match('/^https?:\/\//i', $tb) ? $tb : url($tb);
                    }
                  }
                @endphp
                <tr>
                  <td class="text-center">
                    <input type="checkbox" class="aset-check"
                           name="aset_ids[]"
                           value="{{ $a->id }}"
                           aria-label="Pilih {{ $a->kode_barang }}">
                  </td>
                  <td>
                    @if($thumb !== '')
                      <a href="{{ $thumb }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ $thumb }}" alt="Foto" width="48" height="48"
                             style="object-fit:cover;border-radius:4px;border:1px solid #e9ecef;" loading="lazy">
                      </a>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td>{{ $a->kode_barang }}</td>
                  <td>{{ $a->nup ?? '-' }}</td>
                  <td>{{ $a->nama_barang }}</td>
                  <td>{{ $a->merk ?? '-' }}</td>
                  <td>{{ optional($a->kategori)->nama_kategori ?? '-' }}</td>
                  <td>{{ $a->kondisi ? ucfirst(str_replace('_',' ',$a->kondisi)) : '-' }}</td>
                  <td>{{ $a->labelLokasi() }}</td>
                  <td>{{ $a->satker ?? '-' }}</td>
                  <td>{{ $a->pengguna_ruangan ?? '-' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="11" class="text-center text-muted">Tidak ada aset tersedia untuk dipinjam.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mt-3 bmn-pagination">
          {{ $asetList->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h6 class="fw-semibold mb-3">3. Dokumen Pendukung</h6>
        <div class="mb-3">
          <label for="nota_dinas" class="form-label">Nota Dinas</label>
          <input type="file"
                 name="nota_dinas"
                 id="nota_dinas"
                 accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png"
                 class="form-control form-control-sm @error('nota_dinas') is-invalid @enderror">
          <small class="text-muted d-block mt-1">
            Format PDF / DOC / DOCX / JPG / PNG, maksimal 20 MB.
            Opsional &mdash; bisa dilengkapi nanti oleh admin.
          </small>
          @error('nota_dinas')
            <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mb-4">
      <button type="submit" class="btn btn-primary"
              id="submitBtn" disabled
              onclick="return confirm('Ajukan peminjaman aset terpilih?');">
        <i class="ti ti-send"></i> Ajukan Peminjaman
      </button>
      <a href="{{ route('bmn.peminjaman-aset.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
  </form>
</div>

<script>
(function () {
  function getChecks() { return document.querySelectorAll('.aset-check'); }
  function update() {
    var checked = document.querySelectorAll('.aset-check:checked');
    var counter = document.getElementById('selectedCount');
    var btn = document.getElementById('submitBtn');
    var all = document.getElementById('checkAll');
    if (counter) counter.textContent = String(checked.length);
    if (btn) btn.disabled = checked.length === 0;
    if (all) {
      var total = getChecks().length;
      all.checked = total > 0 && checked.length === total;
      all.indeterminate = checked.length > 0 && checked.length < total;
    }
  }
  var all = document.getElementById('checkAll');
  if (all) {
    all.addEventListener('change', function () {
      var st = this.checked;
      getChecks().forEach(function (c) { c.checked = st; });
      update();
    });
  }
  getChecks().forEach(function (c) { c.addEventListener('change', update); });
  update();
})();
</script>
@endsection

@extends('layouts.thema')

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      <h5 class="card-title fw-semibold mb-3">Mutasi Lokasi Aset</h5>
      <hr>

      @if($asetList->isEmpty())
        <p class="text-muted">Tidak ada aset aktif. <a href="{{ route('bmn.aset.create') }}">Tambah aset</a> terlebih dahulu.</p>
      @else
      <form method="post" action="{{ route('bmn.mutasi.store') }}" class="row">
        @csrf

        <div class="col-md-12 mb-3">
          <label class="form-label">Aset</label>
          <select name="aset_id" class="form-control" required>
            <option value="">— Pilih aset —</option>
            @foreach($asetList as $a)
              <option value="{{ $a->id }}" {{ (string)old('aset_id', $selectedAsetId) === (string)$a->id ? 'selected' : '' }}>
                {{ $a->kode_barang }} — {{ $a->nama_barang }} (lokasi: {{ $a->labelLokasi() }})
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">Lokasi Tujuan</label>
          <select name="ke_lokasi_id" class="form-control" required>
            <option value="">— Pilih —</option>
            @foreach($lokasi as $l)
              <option value="{{ $l->id }}" {{ (string)old('ke_lokasi_id') === (string)$l->id ? 'selected' : '' }}>{{ $l->nama_lokasi }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Tanggal Mutasi</label>
          <input type="date" name="tanggal_mutasi" class="form-control" value="{{ old('tanggal_mutasi', date('Y-m-d')) }}" required>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label">Keterangan</label>
          <textarea name="keterangan" class="form-control" rows="2">{{ old('keterangan') }}</textarea>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary">Simpan Mutasi</button>
          <a href="{{ route('bmn.mutasi.index') }}" class="btn btn-secondary">Batal</a>
        </div>
      </form>
      @endif
    </div>
  </div>
</div>
@endsection

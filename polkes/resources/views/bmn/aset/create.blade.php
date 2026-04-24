@extends('layouts.thema')

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      <h5 class="card-title fw-semibold mb-3">Tambah Aset BMN</h5>
      <hr>

      <form method="post" action="{{ route('bmn.aset.store') }}" class="row g-3">
        @csrf

        <div class="col-12">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Kode Barang</label>
              <input type="text" name="kode_barang" class="form-control @error('kode_barang') is-invalid @enderror" value="{{ old('kode_barang') }}" required>
              @error('kode_barang') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">NUP</label>
              <input type="text" name="nup" class="form-control @error('nup') is-invalid @enderror" value="{{ old('nup') }}" placeholder="Opsional">
              @error('nup') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama Barang</label>
              <input type="text" name="nama_barang" class="form-control @error('nama_barang') is-invalid @enderror" value="{{ old('nama_barang') }}" required>
              @error('nama_barang') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Kategori</label>
              <select name="kategori_id" class="form-select @error('kategori_id') is-invalid @enderror" required>
                <option value="">-- Pilih Kategori --</option>
                @foreach($kategori as $k)
                  <option value="{{ $k->id }}" {{ (string) old('kategori_id') === (string) $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }}</option>
                @endforeach
              </select>
              @error('kategori_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Nilai Perolehan</label>
              <input type="number" step="0.01" min="0" name="nilai_perolehan" class="form-control @error('nilai_perolehan') is-invalid @enderror" value="{{ old('nilai_perolehan') }}" required>
              @error('nilai_perolehan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Perolehan</label>
              <input type="date" name="tanggal_perolehan" class="form-control @error('tanggal_perolehan') is-invalid @enderror" value="{{ old('tanggal_perolehan') }}">
              @error('tanggal_perolehan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Kondisi</label>
              <select name="kondisi" class="form-select @error('kondisi') is-invalid @enderror" required>
                <option value="">-- Pilih Kondisi --</option>
                @foreach(['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat'] as $value => $label)
                  <option value="{{ $value }}" {{ old('kondisi') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
              @error('kondisi') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Pengguna</label>
              <input type="text" name="pengguna_ruangan" class="form-control @error('pengguna_ruangan') is-invalid @enderror" value="{{ old('pengguna_ruangan') }}" placeholder="Contoh: Lab IT">
              @error('pengguna_ruangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label">Satker</label>
              <select name="satker_id" class="form-select @error('satker_id') is-invalid @enderror" required>
                <option value="">-- Pilih Satker --</option>
                @foreach($satkerList as $satker)
                  <option value="{{ $satker->id }}" {{ (string) old('satker_id') === (string) $satker->id ? 'selected' : '' }}>
                    {{ $satker->kode }} - {{ $satker->nama }}
                  </option>
                @endforeach
              </select>
              @error('satker_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-12">
              <label class="form-label">Lokasi</label>
              <input type="text" name="lokasi" class="form-control @error('lokasi') is-invalid @enderror" value="{{ old('lokasi') }}" placeholder="Masukkan alamat lengkap">
              @error('lokasi') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
          </div>
        </div>

        <div class="col-12 mt-2">
          <button type="submit" class="btn btn-primary">Simpan</button>
          <a href="{{ route('bmn.aset.index') }}" class="btn btn-secondary">Batal</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

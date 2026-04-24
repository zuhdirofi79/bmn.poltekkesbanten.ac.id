@extends('layouts.thema')

@section('content')
<div class="container-fluid">
  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title fw-semibold mb-3">Edit Aset BMN — {{ $aset->kode_barang }}</h5>
      <p class="small text-muted mb-2">Lokasi tampil sebagai teks bebas. Untuk mutasi ke lokasi master (referensi), gunakan menu <strong>Mutasi Lokasi</strong>.</p>
      <hr>

      <form method="post" action="{{ route('bmn.aset.update', $aset) }}" class="row g-3">
        @csrf
        @method('PUT')

        <div class="col-md-6">
          <label class="form-label">Kode Barang</label>
          <input type="text" name="kode_barang" class="form-control @error('kode_barang') is-invalid @enderror" value="{{ old('kode_barang', $aset->kode_barang) }}" required>
          @error('kode_barang')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">NUP</label>
          <input type="text" name="nup" class="form-control @error('nup') is-invalid @enderror" value="{{ old('nup', $aset->nup) }}">
          @error('nup')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Nama Barang</label>
          <input type="text" name="nama_barang" class="form-control @error('nama_barang') is-invalid @enderror" value="{{ old('nama_barang', $aset->nama_barang) }}" required>
          @error('nama_barang')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Kategori</label>
          <select name="kategori_id" class="form-select @error('kategori_id') is-invalid @enderror" required>
            @foreach($kategori as $k)
              <option value="{{ $k->id }}" {{ (string) old('kategori_id', $aset->kategori_id) === (string) $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }}</option>
            @endforeach
          </select>
          @error('kategori_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Nilai Perolehan</label>
          <input type="number" step="0.01" min="0" name="nilai_perolehan" class="form-control @error('nilai_perolehan') is-invalid @enderror" value="{{ old('nilai_perolehan', $aset->nilai_perolehan) }}" required>
          @error('nilai_perolehan')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Tanggal Perolehan</label>
          <input type="date" name="tanggal_perolehan" class="form-control @error('tanggal_perolehan') is-invalid @enderror" value="{{ old('tanggal_perolehan', optional($aset->tanggal_perolehan)->format('Y-m-d')) }}">
          @error('tanggal_perolehan')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Kondisi</label>
          <select name="kondisi" class="form-select @error('kondisi') is-invalid @enderror" required>
            @foreach(['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat'] as $value => $label)
              <option value="{{ $value }}" {{ old('kondisi', $aset->kondisi) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          @error('kondisi')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Pengguna</label>
          <input type="text" name="pengguna_ruangan" class="form-control @error('pengguna_ruangan') is-invalid @enderror" value="{{ old('pengguna_ruangan', $aset->pengguna_ruangan) }}">
          @error('pengguna_ruangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Satker</label>
          <select name="satker_id" class="form-select @error('satker_id') is-invalid @enderror" required>
            <option value="">-- Pilih Satker --</option>
            @foreach($satkerList as $satker)
              <option value="{{ $satker->id }}" {{ (string) old('satker_id', $aset->satker_id) === (string) $satker->id ? 'selected' : '' }}>
                {{ $satker->kode }} - {{ $satker->nama }}
              </option>
            @endforeach
          </select>
          @error('satker_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-12">
          <label class="form-label">Lokasi</label>
          <input type="text" name="lokasi" class="form-control @error('lokasi') is-invalid @enderror" value="{{ old('lokasi', $aset->lokasi) }}" placeholder="Masukkan alamat lengkap">
          @error('lokasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Status</label>
          <select name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach(['aktif' => 'Aktif', 'dipindahtangankan' => 'Dipindahtangankan', 'dihapus' => 'Dihapus'] as $value => $label)
              <option value="{{ $value }}" {{ old('status', $aset->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12"><hr class="my-1"><h6 class="text-muted small mt-1">Lanjutan (opsional)</h6></div>
        <div class="col-md-6">
          <label class="form-label">Tipe Asset Tag</label>
          <select name="asset_tag_type" class="form-control">
            <option value="">— Pilih —</option>
            @foreach(['qr' => 'QR Code', 'barcode' => 'Barcode', 'rfid' => 'RFID', 'manual' => 'Manual'] as $value => $label)
              <option value="{{ $value }}" {{ old('asset_tag_type', $aset->asset_tag_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Asset Tag Hash</label>
          <input type="text" name="asset_tag_hash" class="form-control" value="{{ old('asset_tag_hash', $aset->asset_tag_hash) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Provider Foto</label>
          <select name="foto_provider" class="form-control">
            <option value="">— Pilih —</option>
            @foreach(['local' => 'Local Storage', 's3' => 'Amazon S3', 'gdrive' => 'Google Drive', 'other' => 'Lainnya'] as $value => $label)
              <option value="{{ $value }}" {{ old('foto_provider', $aset->foto_provider) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">URL Foto</label>
          <input type="url" name="foto_url" class="form-control" value="{{ old('foto_url', $aset->foto_url) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">URL Preview Foto</label>
          <input type="url" name="foto_preview_url" class="form-control" value="{{ old('foto_preview_url', $aset->foto_preview_url) }}">
        </div>

        <div class="col-12 mt-2">
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
          <a href="{{ route('bmn.aset.index') }}" class="btn btn-secondary">Kembali</a>
          @if($aset->status === 'aktif')
            <a href="{{ route('bmn.mutasi.create', ['aset_id' => $aset->id]) }}" class="btn btn-outline-primary">Mutasi Lokasi</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h6 class="fw-semibold mb-3">Riwayat (50 terakhir)</h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>Waktu</th>
              <th>Aksi</th>
              <th>User</th>
              <th>Deskripsi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($aset->histori as $h)
              <tr>
                <td>{{ $h->created_at ? $h->created_at->format('Y-m-d H:i') : '-' }}</td>
                <td>{{ $h->aksi }}</td>
                <td>{{ optional($h->user)->name ?? '-' }}</td>
                <td>{{ $h->deskripsi }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-muted text-center">Belum ada riwayat.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@extends('layouts.sewa_admin')

@section('page_title', 'Foto Layanan Sewa')
@section('breadcrumbs')
  <a href="{{ route('sewa-admin.dashboard') }}">Dashboard</a> /
  <a href="{{ route('sewa-admin.tarif.index') }}">Layanan Sewa</a> /
  {{ $tarif->nama_layanan }}
@endsection

@section('content')
<div class="d-flex justify-content-between mb-3">
  <div>
    <h3 class="mb-1">{{ $tarif->nama_layanan }}</h3>
    <div class="text-muted small">
      {{ $tarif->kategori }} · {{ $tarif->satuan }} ·
      Umum Rp {{ number_format((float) $tarif->tarif_umum, 0, ',', '.') }}
      @if($tarif->tarif_pegawai !== null)
        · Pegawai Rp {{ number_format((float) $tarif->tarif_pegawai, 0, ',', '.') }}
      @endif
    </div>
  </div>
  <a href="{{ route('sewa-admin.tarif.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
  <div class="col-lg-8">
    <div class="bmn-card mb-3">
      <div class="bmn-card-header"><h2>Galeri Foto</h2></div>
      <div class="bmn-card-body">
        @if($tarif->photos->isEmpty())
          <div class="text-center text-muted py-4">Belum ada foto. Unggah foto produk/aset sewa di panel kanan.</div>
        @else
          <div class="row g-3">
            @foreach($tarif->photos as $foto)
              <div class="col-md-4">
                <div class="border rounded p-2 h-100">
                  <a href="{{ $foto->fotoUrl() }}" target="_blank" rel="noopener">
                    <img src="{{ $foto->fotoUrl() }}" alt="" class="w-100 rounded mb-2" style="height:160px;object-fit:cover;">
                  </a>
                  <div class="small text-truncate mb-1" title="{{ $foto->foto_original_name }}">
                    {{ $foto->foto_original_name ?? basename((string) $foto->foto_path) }}
                  </div>
                  @if($foto->is_primary)
                    <span class="badge bg-primary mb-2">Foto Utama</span>
                  @endif
                  <div class="d-flex flex-wrap gap-1">
                    @if(! $foto->is_primary)
                      <form method="post" action="{{ route('sewa-admin.tarif.fotos.primary', [$tarif->id, $foto->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">Jadikan Utama</button>
                      </form>
                    @endif
                    <form method="post" action="{{ route('sewa-admin.tarif.fotos.move', [$tarif->id, $foto->id]) }}">
                      @csrf
                      <input type="hidden" name="direction" value="up">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" title="Naik">↑</button>
                    </form>
                    <form method="post" action="{{ route('sewa-admin.tarif.fotos.move', [$tarif->id, $foto->id]) }}">
                      @csrf
                      <input type="hidden" name="direction" value="down">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" title="Turun">↓</button>
                    </form>
                    <form method="post" action="{{ route('sewa-admin.tarif.fotos.delete', [$tarif->id, $foto->id]) }}"
                          onsubmit="return confirm('Hapus foto ini?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="bmn-card">
      <div class="bmn-card-header"><h2>Upload Foto</h2></div>
      <div class="bmn-card-body">
        <form method="post"
              action="{{ route('sewa-admin.tarif.fotos.upload', $tarif->id) }}"
              enctype="multipart/form-data">
          @csrf
          <div class="mb-3">
            <label class="form-label">Pilih Foto <span class="text-danger">*</span></label>
            <input type="file" name="fotos[]" class="form-control" accept="image/jpeg,image/png,image/jpg,image/webp" multiple required>
            <small class="text-muted">JPG, PNG, WEBP. Maks. 5 MB per foto. Boleh lebih dari satu.</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Caption (opsional)</label>
            <input type="text" name="caption" class="form-control" maxlength="255" placeholder="Keterangan foto">
          </div>
          <button type="submit" class="btn btn-bmn-primary w-100">
            <i class="ti ti-upload"></i> Unggah Foto
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

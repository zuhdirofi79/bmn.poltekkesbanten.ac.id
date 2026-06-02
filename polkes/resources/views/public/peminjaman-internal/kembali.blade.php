@extends('layouts.public_bmn')

@section('title', 'Ajukan Pengembalian Internal')

@section('content')
<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Ajukan Pengembalian Internal</h2>
      <div class="subtitle">Masukkan kode peminjaman — data aset internal tidak ditampilkan</div>
    </div>
  </div>
  <div class="bmn-card-body">

    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="get" action="{{ route('public.peminjaman-internal.kembali') }}" class="card border mb-3">
      <div class="card-body py-3">
        <label class="form-label">Kode Peminjaman <span class="text-danger">*</span></label>
        <div class="input-group">
          <input type="text" name="kode_peminjaman" class="form-control" value="{{ old('kode_peminjaman', $kode) }}" placeholder="PJM-BMN-YYYYMMDD-XXXXX" required>
          <button type="submit" class="btn btn-outline-primary">Cari</button>
        </div>
      </div>
    </form>

    @if($peminjaman)
      @if($peminjaman->status !== \App\Models\BMN\PeminjamanAsetBmn::STATUS_DIPINJAM)
        <div class="alert alert-danger">
          Peminjaman <code>{{ $peminjaman->kode_peminjaman }}</code> berstatus <strong>{{ $peminjaman->status }}</strong>.
          Pengembalian hanya untuk status <strong>dipinjam</strong>.
        </div>
      @else
        <div class="alert alert-info small">
          Kode ditemukan: <code>{{ $peminjaman->kode_peminjaman }}</code>.
          Tujuan: {{ $peminjaman->tujuan_peminjaman }}.
        </div>

        <form method="post" action="{{ route('public.peminjaman-internal.kembali.store') }}" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="kode_peminjaman" value="{{ $peminjaman->kode_peminjaman }}">

          <div class="card border mb-3">
            <div class="card-body">
              <h6 class="fw-semibold mb-3">Biodata</h6>
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                  <input type="text" name="public_nama" class="form-control" required maxlength="255" value="{{ old('public_nama') }}">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Nomor WA Aktif <span class="text-danger">*</span></label>
                  <input type="text" name="public_no_wa" class="form-control" required maxlength="30" value="{{ old('public_no_wa') }}">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Email <span class="text-danger">*</span></label>
                  <input type="email" name="public_email" class="form-control" required maxlength="255" value="{{ old('public_email') }}">
                </div>
              </div>
            </div>
          </div>

          <div class="card border mb-3">
            <div class="card-body">
              <h6 class="fw-semibold mb-3">Detail Pengembalian</h6>
              <div class="mb-3">
                <label class="form-label">Tanggal Kembali <span class="text-danger">*</span></label>
                <input type="date" name="tanggal_kembali" class="form-control" required value="{{ old('tanggal_kembali', now()->toDateString()) }}">
              </div>
              <div class="mb-3">
                <label class="form-label">Upload Foto Bukti Kondisi Aset</label>
                <input type="file" name="bukti_foto" class="form-control" accept="image/jpeg,image/png,image/jpg,image/webp">
              </div>
              <div class="mb-0">
                <label class="form-label">Catatan Pengembalian</label>
                <textarea name="catatan_pengembalian" rows="3" class="form-control" maxlength="2000">{{ old('catatan_pengembalian') }}</textarea>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Ajukan pengembalian?');">
            <i class="ti ti-send"></i> Ajukan Pengembalian
          </button>
        </form>
      @endif
    @elseif($kode !== '')
      <div class="alert alert-danger">Kode peminjaman tidak ditemukan.</div>
    @endif
  </div>
</div>
@endsection

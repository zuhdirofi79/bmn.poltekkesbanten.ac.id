@extends('layouts.guest_bmn')

@section('title', 'Pengembalian Aset BMN')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-semibold mb-0">
      <i class="ti ti-rotate-2"></i> Ajukan Pengembalian Aset BMN
    </h5>
    <a href="{{ url('login') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left"></i> Login
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

  <div class="alert alert-warning small">
    Pengajuan tanpa akun wajib verifikasi Email dan WhatsApp.
    Pengajuan tetap menunggu persetujuan admin BMN.
  </div>

  <form method="get" action="{{ route('bmn.pengembalian-aset.create-public') }}" class="card border mb-3">
    <div class="card-body py-3">
      <label class="form-label">Kode Peminjaman <span class="text-danger">*</span></label>
      <div class="input-group input-group-sm">
        <input type="text" name="kode_peminjaman" class="form-control"
               value="{{ old('kode_peminjaman', $kode) }}"
               placeholder="Contoh: PJM-BMN-20260525-ABCDE" required>
        <button type="submit" class="btn btn-outline-primary">Cari</button>
      </div>
      <small class="text-muted">Masukkan kode yang Anda terima saat peminjaman disetujui/dipinjam.</small>
    </div>
  </form>

  @if($peminjaman)
    @if($peminjaman->status !== \App\Models\BMN\PeminjamanAsetBmn::STATUS_DIPINJAM)
      <div class="alert alert-danger">
        Peminjaman <code>{{ $peminjaman->kode_peminjaman }}</code> berstatus
        <strong>{{ $peminjaman->status }}</strong>. Pengembalian hanya untuk status <strong>dipinjam</strong>.
      </div>
    @elseif($peminjaman->pengembalian)
      <div class="alert alert-danger">
        Peminjaman ini sudah memiliki pengajuan pengembalian.
      </div>
    @else
      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-2">Ringkasan Peminjaman</h6>
          <div class="small mb-2">
            <div><span class="text-muted">Kode:</span> <code>{{ $peminjaman->kode_peminjaman }}</code></div>
            <div><span class="text-muted">Tujuan:</span> {{ $peminjaman->tujuan_peminjaman }}</div>
            <div><span class="text-muted">Tgl Pinjam:</span> {{ optional($peminjaman->tanggal_pinjam)->format('Y-m-d') }}</div>
          </div>
          <ul class="small mb-0">
            @foreach($peminjaman->details as $det)
              <li>{{ optional($det->aset)->nama_barang ?? '-' }} ({{ optional($det->aset)->kode_barang ?? '-' }})</li>
            @endforeach
          </ul>
        </div>
      </div>

      <form method="post" action="{{ route('bmn.pengembalian-aset.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="kode_peminjaman" value="{{ $peminjaman->kode_peminjaman }}">

        <div class="card mb-3">
          <div class="card-body">
            <h6 class="fw-semibold mb-3">Data Pengaju</h6>
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" name="public_nama" required maxlength="255"
                       value="{{ old('public_nama') }}" class="form-control form-control-sm">
              </div>
              <div class="col-md-4">
                <label class="form-label">Nomor WhatsApp <span class="text-danger">*</span></label>
                <input type="text" name="public_no_wa" required maxlength="30"
                       value="{{ old('public_no_wa') }}" class="form-control form-control-sm"
                       placeholder="08xxxxxxxxxx">
              </div>
              <div class="col-md-4">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" name="public_email" required maxlength="255"
                       value="{{ old('public_email') }}" class="form-control form-control-sm">
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-body">
            <h6 class="fw-semibold mb-3">Detail Pengembalian</h6>
            <div class="mb-3">
              <label class="form-label">Tanggal Kembali <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_kembali" required
                     value="{{ old('tanggal_kembali', now()->toDateString()) }}"
                     min="{{ optional($peminjaman->tanggal_pinjam)->format('Y-m-d') }}"
                     class="form-control form-control-sm">
            </div>
            <div class="mb-3">
              <label class="form-label">Catatan Pengembalian</label>
              <textarea name="catatan_pengembalian" rows="3" maxlength="2000"
                        class="form-control form-control-sm">{{ old('catatan_pengembalian') }}</textarea>
            </div>
            <div class="mb-0">
              <label class="form-label">Foto Bukti Pengembalian</label>
              <input type="file" name="bukti_foto" accept="image/jpeg,image/png,image/jpg,image/webp"
                     class="form-control form-control-sm @error('bukti_foto') is-invalid @enderror">
              <small class="text-muted">Opsional. JPG/PNG/WEBP, maks. 4 MB.</small>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-success w-100"
                onclick="return confirm('Ajukan pengembalian aset ini?');">
          <i class="ti ti-send"></i> Ajukan Pengembalian
        </button>
      </form>
    @endif
  @elseif($kode !== '')
    <div class="alert alert-danger">Kode peminjaman tidak ditemukan.</div>
  @endif
@endsection

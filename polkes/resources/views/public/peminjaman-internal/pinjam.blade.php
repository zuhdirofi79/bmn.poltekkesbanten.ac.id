@extends('layouts.public_bmn')

@section('title', 'Ajukan Peminjaman Internal')

@section('content')
<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Ajukan Peminjaman Internal</h2>
      <div class="subtitle">Isi barang yang ingin dipinjam — tim Rumah Tangga akan mencocokkan aset internal</div>
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

    <div class="alert alert-info small mb-3">
      <i class="ti ti-info-circle"></i>
      <strong>Email wajib diverifikasi</strong> untuk memastikan pengajuan berasal dari pemohon yang valid.
      Setelah submit, Anda akan menerima kode OTP via email.
      <br>
      <i class="ti ti-brand-whatsapp"></i>
      Nomor WhatsApp wajib diisi untuk memudahkan petugas menghubungi Anda.
      <em>Verifikasi WhatsApp sementara dinonaktifkan.</em>
    </div>

    <form method="post" action="{{ route('public.peminjaman-internal.pinjam.store') }}" enctype="multipart/form-data">
      @csrf
      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">Biodata</h6>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="public_nama" class="form-control" required maxlength="255" value="{{ old('public_nama') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Nomor WhatsApp Aktif <span class="text-danger">*</span></label>
              <input type="text" name="public_no_wa" class="form-control" required maxlength="30" value="{{ old('public_no_wa') }}" placeholder="08xxxxxxxxxx">
              <small class="text-muted">Format Indonesia: 08xxxxxxx / 62xxxxxxx / +62xxxxxxx.</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="public_email" class="form-control" required maxlength="255" value="{{ old('public_email') }}">
              <small class="text-muted">Email wajib diverifikasi setelah submit (OTP 6 digit).</small>
            </div>
          </div>
        </div>
      </div>

      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">Detail Peminjaman</h6>
          <div class="mb-3">
            <label class="form-label">Barang yang Diajukan <span class="text-danger">*</span></label>
            <textarea name="requested_barang" rows="3" class="form-control" required maxlength="5000"
                      placeholder="Contoh: laptop, proyektor, sound system, kendaraan...">{{ old('requested_barang') }}</textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label">Tanggal Pinjam <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_pinjam" class="form-control" required value="{{ old('tanggal_pinjam') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">Rencana Kembali <span class="text-danger">*</span></label>
              <input type="date" name="rencana_kembali" class="form-control" required value="{{ old('rencana_kembali') }}">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Tujuan Peminjaman <span class="text-danger">*</span></label>
            <input type="text" name="tujuan_peminjaman" class="form-control" required maxlength="255" value="{{ old('tujuan_peminjaman') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Upload Nota Dinas / Surat Permohonan (PDF) <span class="text-danger">*</span></label>
            <input type="file" name="nota_dinas" class="form-control" accept="application/pdf" required>
            <small class="text-muted">Maks. 5 MB, format PDF.</small>
          </div>
          <div class="mb-0">
            <label class="form-label">Catatan</label>
            <textarea name="catatan" rows="2" class="form-control" maxlength="2000">{{ old('catatan') }}</textarea>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Kirim pengajuan peminjaman internal?');">
        <i class="ti ti-send"></i> Ajukan Peminjaman
      </button>
    </form>
  </div>
</div>
@endsection

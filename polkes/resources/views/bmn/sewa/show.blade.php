@extends('layouts.bmn_admin')

@section('page_title', 'Detail Sewa')
@section('breadcrumbs')
  <a href="{{ route('bmn.dashboard.index') }}">Dashboard</a> / <a href="{{ route('bmn.sewa.index') }}">Sewa BMN</a> / {{ $pengajuan->kode_sewa }}
@endsection

@section('content')
<div class="d-flex justify-content-end mb-3">
  <a href="{{ route('bmn.sewa.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
</div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="bmn-card">
        <div class="bmn-card-header"><h2>Biodata Penyewa</h2></div>
        <div class="bmn-card-body small">
          <h6 class="fw-semibold">Biodata Penyewa</h6>
          <div>Nama: {{ $pengajuan->nama_lengkap }}</div>
          <div>WA: <a href="https://wa.me/{{ $pengajuan->nomor_wa }}" target="_blank">{{ $pengajuan->nomor_wa }}</a></div>
          <div>Email: {{ $pengajuan->email }}</div>
          <div>Jenis: {{ $pengajuan->jenisPenyewaLabel() }}</div>
          <hr>
          <div>Layanan: {{ optional($pengajuan->tarif)->nama_layanan }} ({{ optional($pengajuan->tarif)->kategori }})</div>
          <div>Mulai: {{ optional($pengajuan->tanggal_mulai_sewa)->format('Y-m-d') }}</div>
          <div>Kembali: {{ optional($pengajuan->tanggal_rencana_kembali)->format('Y-m-d') }}</div>
          <div>Tujuan: {{ $pengajuan->tujuan_peminjaman }}</div>
          @if($pengajuan->catatan)<div>Catatan: {{ $pengajuan->catatan }}</div>@endif
        </div>
      </div>
      @include('sewa_admin.sewa._dokumen', ['pengajuan' => $pengajuan])
    </div>
    <div class="col-lg-5">
      <div class="bmn-card mb-3">
        <div class="bmn-card-header"><h2>Konfirmasi Pembayaran</h2></div>
        <div class="bmn-card-body">
          <h6 class="fw-semibold mb-2">Konfirmasi Pembayaran</h6>
          <p class="small text-muted">Status: <strong>{{ $pengajuan->payment_status }}</strong></p>
          @if($pengajuan->payment_status === 'pending')
            <form method="post" action="{{ route('bmn.sewa.confirm-payment', $pengajuan->id) }}" class="mb-2">
              @csrf
              <textarea name="admin_payment_note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan (opsional)"></textarea>
              <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Konfirmasi pembayaran?');">Konfirmasi Pembayaran</button>
            </form>
            <form method="post" action="{{ route('bmn.sewa.reject-payment', $pengajuan->id) }}">
              @csrf
              <textarea name="admin_payment_note" class="form-control form-control-sm mb-2" rows="2" placeholder="Alasan penolakan (wajib)" required></textarea>
              <button type="submit" class="btn btn-danger btn-sm w-100">Tolak Pembayaran</button>
            </form>
          @elseif($pengajuan->admin_payment_at)
            <div class="small text-muted">Diproses: {{ $pengajuan->admin_payment_at->format('Y-m-d H:i') }}</div>
            @if($pengajuan->admin_payment_note)<div class="small">{{ $pengajuan->admin_payment_note }}</div>@endif
          @endif
        </div>
      </div>

      @if($pengajuan->payment_status === 'confirmed')
        <div class="bmn-card">
          <div class="bmn-card-header"><h2>Koordinasi ke Pak Singgih &amp; Bu Retno</h2></div>
          <div class="bmn-card-body">
            <h6 class="fw-semibold mb-2">Koordinasi ke Pak Singgih &amp; Bu Retno</h6>
            <textarea id="coordText" class="form-control form-control-sm mb-2" rows="8" readonly>{{ $coordinationText }}</textarea>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mb-2" onclick="navigator.clipboard.writeText(document.getElementById('coordText').value); alert('Teks disalin');">
              Salin Teks Koordinasi
            </button>
            <a href="https://wa.me/?text={{ urlencode($coordinationText) }}" target="_blank" class="btn btn-success btn-sm w-100 mb-2">
              Buka WhatsApp (forward manual)
            </a>
            @if($pengajuan->coordination_status === 'pending')
              <form method="post" action="{{ route('bmn.sewa.mark-coordinated', $pengajuan->id) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm w-100">Tandai Koordinasi Terkirim</button>
              </form>
            @else
              <span class="badge bg-success">Koordinasi: {{ $pengajuan->coordination_status }}</span>
            @endif
          </div>
        </div>
      @endif
    </div>
  </div>
@endsection

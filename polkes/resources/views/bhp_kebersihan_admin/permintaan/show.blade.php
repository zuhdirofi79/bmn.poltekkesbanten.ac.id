@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Detail Permintaan BHP')

@section('content')
<div class="pa-card">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h2 class="mb-1"><code>{{ $permintaan->nomor_permintaan }}</code></h2>
      <span class="prs-badge {{ $permintaan->status }}">{{ $permintaan->statusLabel() }}</span>
    </div>
    <a href="{{ route('bhp.admin.permintaan.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <table class="table table-sm">
        <tr><th>Barang</th><td>{{ $permintaan->barang?->nama_barang }} ({{ $permintaan->barang?->kode_barang }})</td></tr>
        <tr><th>Pengambil</th><td>{{ $permintaan->nama_pengambil }}</td></tr>
        <tr><th>WhatsApp</th><td>{{ $permintaan->no_wa_pengambil }}</td></tr>
        <tr><th>Tanggal Pengambilan</th><td>{{ optional($permintaan->tanggal_pengambilan)->format('d-m-Y') }}</td></tr>
        <tr><th>Jumlah Diminta</th><td>{{ $permintaan->jumlah_diminta }} {{ $permintaan->barang?->satuan }}</td></tr>
        @if($permintaan->catatan)
        <tr><th>Catatan Pemohon</th><td>{{ $permintaan->catatan }}</td></tr>
        @endif
      </table>
    </div>
    <div class="col-md-6">
      @if($permintaan->verified_at)
      <p class="mb-1"><strong>Verifikasi:</strong> {{ optional($permintaan->verifier)->name ?? '-' }} — {{ $permintaan->verified_at->format('d-m-Y H:i') }}</p>
      @if($permintaan->verification_note)<p class="text-muted">{{ $permintaan->verification_note }}</p>@endif
      @endif
      @if($permintaan->processed_at)
      <p class="mb-1"><strong>Pengambilan:</strong> {{ optional($permintaan->processor)->name ?? '-' }} — {{ $permintaan->processed_at->format('d-m-Y H:i') }}</p>
      @if($permintaan->process_note)<p class="text-muted">{{ $permintaan->process_note }}</p>@endif
      @endif
    </div>
  </div>

  @if($canVerify)
  <hr>
  <div class="row g-3">
    <div class="col-md-6">
      <form method="POST" action="{{ route('bhp.admin.permintaan.setujui', $permintaan->id) }}">
        @csrf
        <div class="mb-2">
          <label class="form-label">Catatan (opsional)</label>
          <textarea name="verification_note" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-success"><i class="ti ti-check"></i> Setujui</button>
      </form>
    </div>
    <div class="col-md-6">
      <form method="POST" action="{{ route('bhp.admin.permintaan.tolak', $permintaan->id) }}">
        @csrf
        <div class="mb-2">
          <label class="form-label">Alasan Penolakan</label>
          <textarea name="verification_note" class="form-control" rows="2" required></textarea>
        </div>
        <button type="submit" class="btn btn-danger"><i class="ti ti-x"></i> Tolak</button>
      </form>
    </div>
  </div>
  @endif
</div>
@endsection

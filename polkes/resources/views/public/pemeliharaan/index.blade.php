@extends('layouts.public_bmn')

@section('title', 'Pemeliharaan Aset')

@section('content')
<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Layanan Pemeliharaan Aset BMN</h2>
      <div class="subtitle">Informasi alur permintaan pemeliharaan aset</div>
    </div>
  </div>
  <div class="bmn-card-body">
    <p class="text-muted">
      Pemeliharaan dan perbaikan aset BMN Poltekkes Kemenkes Banten dilakukan oleh tim teknis internal
      berdasarkan permintaan unit kerja dan jadwal rutin.
    </p>
    <div class="alert alert-light border">
      <h6 class="fw-semibold">Alur permintaan pemeliharaan</h6>
      <ol class="mb-0 ps-3 small">
        <li>Unit kerja mengajukan permintaan melalui nota dinas / surat resmi.</li>
        <li>Tim BMN melakukan identifikasi kerusakan dan ketersediaan suku cadang.</li>
        <li>Pemeliharaan dijadwalkan dan dilaporkan setelah selesai.</li>
      </ol>
    </div>
    <p class="mb-0 small text-muted">
      Untuk pengajuan pemeliharaan resmi, silakan koordinasi langsung dengan unit BMN kampus
      atau login admin melalui menu <strong>Aset</strong>.
    </p>
  </div>
</div>
@endsection

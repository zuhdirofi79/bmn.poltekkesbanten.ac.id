@extends('layouts.persediaan_qr')

@section('page_title', 'Konfirmasi Pengambilan')

@section('content')
<div class="prs-public-card">
  <h1 class="h5 mb-3">Konfirmasi Pengambilan</h1>
  <div class="prs-id-row"><span>Pemohon</span><strong>{{ $pemohon->nama }}</strong></div>
  <div class="prs-id-row"><span>Unit</span><strong>{{ $pemohon->unit_kerja }}</strong></div>
  <div class="prs-id-row"><span>Rak</span><strong>{{ $rak->kode_rak }} – {{ $rak->nama_rak }}</strong></div>
  <hr>
  @foreach($chosen as $row)
    <div class="prs-id-row">
      <span>{{ $row['barang']->nama_barang }}</span>
      <strong>{{ $row['jumlah'] }} {{ $row['barang']->satuan }}</strong>
    </div>
  @endforeach
</div>

<form method="POST" action="{{ route('public.persediaan.rak.store', $rak->qr_token) }}" id="submitRak">
  @csrf
  <input type="hidden" name="idempotency_key" value="{{ $idempotency_key }}">
  @foreach($chosen as $row)
    <input type="hidden" name="qty[{{ $row['barang']->id }}]" value="{{ $row['jumlah'] }}">
  @endforeach
  <div class="d-flex gap-2 mt-3">
    <a href="{{ route('public.persediaan.rak.show', $rak->qr_token) }}" class="btn btn-outline-secondary flex-grow-1 prs-touch">Kembali</a>
    <button type="submit" class="btn btn-prs-green flex-grow-1 prs-touch" id="ajukanBtn">Ajukan Permintaan</button>
  </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  var form = document.getElementById('submitRak');
  var btn = document.getElementById('ajukanBtn');
  var sent = false;
  form.addEventListener('submit', function (e) {
    if (sent) { e.preventDefault(); return; }
    sent = true;
    btn.disabled = true;
    btn.textContent = 'Mengirim...';
  });
})();
</script>
@endpush

@php
  $suratUrl = $pengajuan->suratResmiUrl();
  $buktiUrl = $pengajuan->buktiBayarUrl();
@endphp

<div class="bmn-card mb-3">
  <div class="bmn-card-header"><h2>Dokumen Pengajuan</h2></div>
  <div class="bmn-card-body small">
    <div class="mb-3">
      <strong>Surat Resmi / Nota Dinas</strong>
      @if($suratUrl)
        <div class="mt-1 text-muted">{{ $pengajuan->suratResmiOriginalName() }}</div>
        <div class="d-flex gap-2 flex-wrap mt-2">
          <a href="{{ $suratUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-eye"></i> Lihat Dokumen
          </a>
          <a href="{{ $suratUrl }}" download="{{ $pengajuan->suratResmiOriginalName() }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-download"></i> Download
          </a>
        </div>
      @else
        <div class="text-muted mt-1">Belum ada dokumen surat resmi.</div>
      @endif
    </div>

    <hr>

    <div>
      <strong>Bukti Pembayaran</strong>
      @if($buktiUrl)
        <div class="mt-1 text-muted">{{ $pengajuan->buktiBayarOriginalName() }}</div>
        <div class="d-flex gap-2 flex-wrap mt-2">
          <a href="{{ $buktiUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-eye"></i> Lihat Bukti Bayar
          </a>
          <a href="{{ $buktiUrl }}" download="{{ $pengajuan->buktiBayarOriginalName() }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-download"></i> Download
          </a>
        </div>
        @php $ext = strtolower(pathinfo((string) $pengajuan->bukti_bayar_path, PATHINFO_EXTENSION)); @endphp
        @if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true))
          <div class="mt-2">
            <img src="{{ $buktiUrl }}" alt="Bukti Bayar" class="img-fluid rounded border" style="max-height:220px;">
          </div>
        @endif
      @else
        <div class="text-muted mt-1">Belum ada bukti pembayaran.</div>
      @endif
    </div>
  </div>
</div>

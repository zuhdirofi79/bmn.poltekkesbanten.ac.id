@extends('layouts.persediaan_admin')

@section('page_title', 'Detail Permintaan ' . $permintaan->nomor_permintaan)

@php use App\Support\Persediaan\UnitKerja; use App\Models\Persediaan\PersediaanPermintaan as P; @endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=1">
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="{{ route('persediaan.admin.permintaan.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="ti ti-arrow-left"></i> Kembali
  </a>
  <a href="{{ route('persediaan.admin.permintaan.pdf', $permintaan->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
    <i class="ti ti-printer"></i> Cetak PDF
  </a>
</div>

@if($permintaan->trashed())
  <div class="alert alert-warning">Permintaan ini berada di sampah. Histori stok tidak dihapus. Pulihkan dari daftar jika masih diperlukan.</div>
@endif

@php $editable = $canApprove ?? false; @endphp

<div class="pa-card mt-0">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h2>{{ $permintaan->nomor_permintaan }}</h2>
      <div class="text-muted">{{ UnitKerja::label($permintaan->unit_kerja) }} &middot;
        {{ $permintaan->tanggal_permintaan ? $permintaan->tanggal_permintaan->copy()->locale('id')->translatedFormat('d F Y') : '-' }}</div>
      @if($permintaan->approver_user_id)
        <div class="text-muted mt-1" style="font-size:.85rem;">
          Approver: <strong>{{ $permintaan->approverDisplayName() }}</strong>
        </div>
      @endif
    </div>
    <span class="prs-badge {{ $permintaan->status }}">{{ $permintaan->statusLabel() }}</span>
  </div>
  <hr>
  <div class="row">
    <div class="col-md-4"><span class="text-muted">Pemohon:</span> <strong>{{ optional($permintaan->pemohon)->nama }}</strong></div>
    <div class="col-md-4"><span class="text-muted">NIP:</span> <strong>{{ optional($permintaan->pemohon)->nip }}</strong></div>
    <div class="col-md-4"><span class="text-muted">Jabatan:</span> <strong>{{ optional($permintaan->pemohon)->jabatan ?: '-' }}</strong></div>
  </div>
      <p class="mt-2 mb-0"><span class="text-muted">Keterangan:</span> {{ $permintaan->keterangan }}</p>
      @if($permintaan->source === 'rack_qr')
        <p class="mt-2 mb-0"><span class="text-muted">Sumber:</span> QR Rak
          @if($permintaan->rak)
            <strong>{{ $permintaan->rak->kode_rak }} – {{ $permintaan->rak->nama_rak }}</strong>
          @endif
        </p>
      @endif
  @if($permintaan->status === P::STATUS_DITOLAK && $permintaan->catatan_admin)
    <div class="alert alert-danger mt-3 mb-0"><strong>Alasan Penolakan:</strong> {{ $permintaan->catatan_admin }}</div>
  @endif
</div>

<form method="POST" action="{{ route('persediaan.admin.permintaan.setujui', $permintaan->id) }}" id="approveForm">
  @csrf
  <input type="hidden" name="tanda_tangan_admin" id="adminTtdInput">

  <div class="pa-card">
    <h2>Daftar Barang</h2>
    <div class="table-responsive mt-2">
      <table class="pa-table">
        <thead>
          <tr><th>No</th><th>Kode</th><th>Nama Barang</th><th>Satuan</th><th class="text-end">Stok Tersedia</th><th class="text-end">Diminta</th><th style="width:140px;">Disetujui</th></tr>
        </thead>
        <tbody>
          @foreach($permintaan->items as $i => $item)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td><code>{{ $item->kode_barang }}</code></td>
              <td>{{ $item->nama_barang }}</td>
              <td>{{ $item->satuan }}</td>
              <td class="text-end">{{ number_format($item->stok_tersedia, 0, ',', '.') }}</td>
              <td class="text-end">{{ number_format($item->jumlah_diminta, 0, ',', '.') }}</td>
              <td>
                @if($editable)
                  <input type="number" min="0" name="jumlah_disetujui[{{ $item->id }}]"
                         value="{{ $item->jumlah_disetujui ?? $item->jumlah_diminta }}" class="form-control form-control-sm">
                @else
                  {{ is_null($item->jumlah_disetujui) ? '-' : number_format($item->jumlah_disetujui, 0, ',', '.') }}
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @if($editable)
    <div class="pa-card">
      <h2>Persetujuan</h2>
      <div class="prs-sign-grid">
        <div class="prs-sign-box">
          <div class="ttl">Tanda Tangan Admin Persediaan</div>
          <canvas id="adminSignPad" class="prs-sign-pad"></canvas>
          <div class="prs-sign-actions">
            <button type="button" class="btn btn-sm btn-outline-danger" id="adminSignClear"><i class="ti ti-eraser"></i> Hapus TTD</button>
          </div>
        </div>
        <div class="prs-sign-box">
          <div class="ttl">Tanda Tangan Pemohon</div>
          @if($permintaan->tanda_tangan_pemohon_path)
            <div class="prs-sign-static"><img src="{{ asset($permintaan->tanda_tangan_pemohon_path) }}" alt="ttd pemohon"></div>
          @else
            <p class="text-muted" style="margin-top:40px;">Belum ada tanda tangan pemohon.</p>
          @endif
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn btn-prs-green"><i class="ti ti-check"></i> Setujui Permintaan</button>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#tolakModal">
          <i class="ti ti-x"></i> Tolak Permintaan
        </button>
      </div>
    </div>
  @endif
</form>

@if($canIssue ?? false)
  <div class="pa-card">
    <h2>Pengeluaran Barang</h2>
    <p class="text-muted">Setujui sudah dilakukan. Klik untuk mengeluarkan barang; stok akan berkurang otomatis dan permintaan menjadi <strong>selesai</strong>.</p>
    <form method="POST" action="{{ route('persediaan.admin.permintaan.keluarkan', $permintaan->id) }}"
          onsubmit="return confirm('Keluarkan barang dan kurangi stok otomatis?');">
      @csrf
      <button class="btn btn-prs-blue"><i class="ti ti-package-export"></i> Keluarkan Barang</button>
    </form>
  </div>
@endif

@if($editable)
{{-- Modal Tolak --}}
<div class="modal fade" id="tolakModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('persediaan.admin.permintaan.tolak', $permintaan->id) }}" class="modal-content">
      @csrf
      <div class="modal-header"><h5 class="modal-title">Tolak Permintaan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
        <textarea name="catatan_admin" rows="3" class="form-control" required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Tolak Permintaan</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@push('scripts')
@if($editable)
<script>
(function () {
  var canvas = document.getElementById('adminSignPad');
  var input  = document.getElementById('adminTtdInput');
  var form   = document.getElementById('approveForm');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var drawing = false, dirty = false;
  function resize() {
    var ratio = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio; canvas.height = rect.height * ratio;
    ctx.scale(ratio, ratio); ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#15315c';
  }
  resize();
  function pos(e){ var r=canvas.getBoundingClientRect(); var t=e.touches?e.touches[0]:e; return {x:t.clientX-r.left,y:t.clientY-r.top}; }
  function start(e){ drawing=true; dirty=true; var p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); e.preventDefault(); }
  function move(e){ if(!drawing)return; var p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); e.preventDefault(); }
  function end(){ drawing=false; }
  canvas.addEventListener('mousedown',start); canvas.addEventListener('mousemove',move); window.addEventListener('mouseup',end);
  canvas.addEventListener('touchstart',start,{passive:false}); canvas.addEventListener('touchmove',move,{passive:false}); canvas.addEventListener('touchend',end);
  document.getElementById('adminSignClear').addEventListener('click', function(){ ctx.clearRect(0,0,canvas.width,canvas.height); dirty=false; });
  form.addEventListener('submit', function (e) {
    if (!dirty) { e.preventDefault(); alert('Tanda tangan admin wajib diisi untuk menyetujui.'); return; }
    input.value = canvas.toDataURL('image/png');
  });
})();
</script>
@endif
@endpush

@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Form Permintaan Barang')

@php use App\Support\Persediaan\UnitKerja; @endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=3">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="prs-section-title mb-0">Form Permintaan Barang</span>
    <div>
      <a href="{{ route('public.persediaan.permintaan.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-list"></i> Permintaan Saya
      </a>
      <form action="{{ route('public.persediaan.logout') }}" method="POST" class="d-inline">
        @csrf
        <button class="btn btn-outline-danger btn-sm"><i class="ti ti-logout"></i> Keluar</button>
      </form>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="prs-public-card persediaan-request-card">
    <p class="prs-unit-badge">{{ UnitKerja::label($pemohon->unit_kerja) }}</p>

    <div class="prs-meta">
      <div class="row">
        <div class="col-md-6">
          <label>No. Permintaan</label>
          <strong class="text-muted">PRM-{{ UnitKerja::kode($pemohon->unit_kerja) }}-{{ date('Y') }}-XXX
            <small>(otomatis saat disimpan)</small></strong>
        </div>
        <div class="col-md-6">
          <label>Tanggal</label>
          <strong>{{ $tanggal->format('d M Y') }}</strong>
        </div>
        <div class="col-md-6">
          <label>Pemohon</label>
          <strong>{{ $pemohon->nama }}</strong>
        </div>
        <div class="col-md-6">
          <label>NIP</label>
          <strong>{{ $pemohon->nip }}</strong>
        </div>
        <div class="col-md-6">
          <label>Jabatan</label>
          <strong>{{ $pemohon->jabatan ?: '-' }}</strong>
        </div>
        <div class="col-md-6">
          <label>Unit Kerja</label>
          <strong>{{ UnitKerja::label($pemohon->unit_kerja) }}</strong>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('public.persediaan.permintaan.store') }}" id="prsForm">
      @csrf
      <input type="hidden" name="action" id="prsAction" value="draft">
      <input type="hidden" name="tanda_tangan_pemohon" id="prsTtdInput">

      <div class="table-responsive">
        <table class="prs-table">
          <thead>
            <tr>
              <th style="width:48px;">No</th>
              <th style="width:140px;">Kode Barang</th>
              <th>Nama Barang</th>
              <th style="width:90px;">Satuan</th>
              <th style="width:120px;" class="text-end">Stok Tersedia</th>
              <th style="width:150px;">Jumlah Diminta</th>
            </tr>
          </thead>
          <tbody>
            @forelse($barangs as $i => $barang)
              @php $akhir = $ringkasan[$barang->id]['akhir'] ?? $barang->stok_awal; @endphp
              <tr>
                <td>{{ $i + 1 }}</td>
                <td><code>{{ $barang->kode_barang }}</code></td>
                <td>{{ $barang->nama_barang }}
                  <input type="hidden" name="items[{{ $i }}][barang_id]" value="{{ $barang->id }}">
                </td>
                <td>{{ $barang->satuan }}</td>
                <td class="num">{{ number_format($akhir, 0, ',', '.') }}</td>
                <td>
                  <input type="number" min="0" max="{{ $akhir }}"
                         name="items[{{ $i }}][jumlah_diminta]"
                         value="{{ old('items.'.$i.'.jumlah_diminta') }}"
                         class="form-control form-control-sm" placeholder="0">
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-3">
                Belum ada master barang persediaan untuk unit ini.
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        <label class="form-label">Keterangan <span class="text-danger">*</span></label>
        <textarea name="keterangan" rows="2" class="form-control" required
                  placeholder="Contoh: Untuk kebutuhan praktikum dan administrasi.">{{ old('keterangan') }}</textarea>
      </div>

      <div class="prs-sign-grid">
        <div class="prs-sign-box">
          <div class="ttl">Tanda Tangan Pemohon</div>
          <canvas id="prsSignPad" class="prs-sign-pad"></canvas>
          <div class="prs-sign-actions">
            <button type="button" class="btn btn-sm btn-outline-danger" id="prsSignClear">
              <i class="ti ti-eraser"></i> Hapus TTD
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="prsSignRedo">
              <i class="ti ti-refresh"></i> TTD Ulang
            </button>
          </div>
        </div>
        <div class="prs-sign-box">
          <div class="ttl">Mengetahui / Menyetujui</div>
          <p class="text-muted" style="margin-top:48px;">
            <i class="ti ti-clock"></i><br>
            Tanda tangan admin Persediaan akan diisi setelah permintaan disetujui.
          </p>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-prs-green" data-action="draft">
          <i class="ti ti-device-floppy"></i> Simpan Draft
        </button>
        <button type="submit" class="btn btn-prs-blue" data-action="ajukan">
          <i class="ti ti-send"></i> Ajukan Permintaan
        </button>
        <button type="button" class="btn btn-outline-secondary" disabled title="Tersedia setelah tersimpan">
          <i class="ti ti-printer"></i> Cetak PDF
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var canvas = document.getElementById('prsSignPad');
  var input  = document.getElementById('prsTtdInput');
  var form   = document.getElementById('prsForm');
  var actionField = document.getElementById('prsAction');
  if (!canvas) return;

  var ctx = canvas.getContext('2d');
  var drawing = false, dirty = false;

  function resize() {
    var ratio = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    ctx.scale(ratio, ratio);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#15315c';
  }
  resize();

  function pos(e) {
    var rect = canvas.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - rect.left, y: t.clientY - rect.top };
  }
  function start(e) { drawing = true; dirty = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
  function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
  function end() { drawing = false; }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('touchmove', move, { passive: false });
  canvas.addEventListener('touchend', end);

  function clearPad() { ctx.clearRect(0, 0, canvas.width, canvas.height); dirty = false; }
  document.getElementById('prsSignClear').addEventListener('click', clearPad);
  document.getElementById('prsSignRedo').addEventListener('click', clearPad);

  form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
    btn.addEventListener('click', function () { actionField.value = btn.getAttribute('data-action'); });
  });

  form.addEventListener('submit', function () {
    input.value = dirty ? canvas.toDataURL('image/png') : '';
  });
})();
</script>
@endpush

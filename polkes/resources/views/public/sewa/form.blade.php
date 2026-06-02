@extends('layouts.public_bmn')

@section('title', $jenis === 'pegawai' ? 'Sewa Pegawai' : 'Sewa Umum')

@push('styles')
<style>
  .sewa-summary-card {
    border: 1px solid #cfe2ff;
    background: #f5faff;
    border-radius: 12px;
    padding: 1rem 1.1rem;
  }
  .sewa-summary-card h6 {
    margin: 0 0 .65rem;
    font-weight: 600;
    color: #0f172a;
  }
  .sewa-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: .25rem 0;
    font-size: .9rem;
    color: #334155;
  }
  .sewa-summary-row .label { color: #64748b; }
  .sewa-summary-total {
    margin-top: .75rem;
    padding-top: .75rem;
    border-top: 1px dashed #94a3b8;
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
  }
  .sewa-summary-total .label {
    font-weight: 600;
    color: #0f172a;
  }
  .sewa-summary-total .value {
    font-size: 1.4rem;
    font-weight: 700;
    color: #0d6efd;
  }
  .sewa-summary-empty {
    font-size: .85rem;
    color: #64748b;
    text-align: center;
    padding: .5rem 0;
  }
  .sewa-summary-warning {
    margin-top: .65rem;
    padding: .55rem .75rem;
    background: #fff4e5;
    border: 1px solid #ffd591;
    color: #ad6800;
    border-radius: 8px;
    font-size: .82rem;
  }
</style>
@endpush

@section('content')
@php $isPegawai = $jenis === 'pegawai'; @endphp
<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Form Sewa {{ $isPegawai ? 'Pegawai' : 'Umum' }}</h2>
      <div class="subtitle">Lengkapi biodata, pilih layanan, lalu tentukan tanggal sewa</div>
    </div>
    @if($isPegawai)
      <span class="badge badge-bmn-success rounded-pill px-3 py-2">Diskon 50% khusus pegawai</span>
    @endif
  </div>
  <div class="bmn-card-body">

    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul></div>
    @endif

    <form method="post"
          action="{{ $isPegawai ? route('public.sewa.pegawai.store') : route('public.sewa.umum.store') }}"
          enctype="multipart/form-data"
          id="formSewa">
      @csrf

      {{-- A. Biodata Penyewa --}}
      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">A. Biodata Penyewa</h6>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
              <input type="text" name="nama_lengkap" class="form-control" required maxlength="255" value="{{ old('nama_lengkap') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Nomor WhatsApp Aktif <span class="text-danger">*</span></label>
              <input type="text" name="nomor_wa" class="form-control" required maxlength="30" value="{{ old('nomor_wa') }}" placeholder="08xxxxxxxxxx">
            </div>
            <div class="col-md-4">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required maxlength="255" value="{{ old('email') }}">
            </div>
          </div>
        </div>
      </div>

      {{-- B. Pilih Layanan Sewa --}}
      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">B. Pilih Layanan Sewa</h6>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label">Kategori Layanan</label>
              <select id="kategoriSelect" class="form-select">
                <option value="">— Pilih kategori —</option>
                @foreach($kategoriList as $kategori => $items)
                  <option value="{{ $kategori }}" @selected(old('kategori', $selectedKategori) === $kategori)>{{ $kategori }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Layanan / Jenis Sewa <span class="text-danger">*</span></label>
              <select name="tarif_id" id="tarifSelect" class="form-select" required>
                <option value="">— Pilih layanan —</option>
                @foreach($kategoriList as $kategori => $items)
                  @foreach($items as $tarif)
                    <option value="{{ $tarif->id }}"
                            data-kategori="{{ $kategori }}"
                            data-satuan="{{ $tarif->satuan }}"
                            data-nama="{{ $tarif->nama_layanan }}"
                            data-tarif-umum="{{ (int) round((float) $tarif->tarif_umum) }}"
                            @if($isPegawai && $tarif->tarif_pegawai !== null)
                              data-tarif-pegawai="{{ (int) round((float) $tarif->tarif_pegawai) }}"
                            @endif
                            @selected(old('tarif_id', $selectedTarifId) == $tarif->id)>
                      {{ $tarif->nama_layanan }} — {{ $tarif->satuan }}
                    </option>
                  @endforeach
                @endforeach
              </select>
            </div>
          </div>

          <div id="tarifInfo" class="alert alert-light border small mb-0" style="display:none;">
            <div><strong>Satuan:</strong> <span id="infoSatuan">-</span></div>
            <div><strong>Tarif per satuan:</strong> Rp <span id="infoTarif">-</span></div>
            @if($isPegawai)
              <div class="text-muted"><s>Rp <span id="infoTarifUmum">-</span></s> → tarif pegawai</div>
            @endif
          </div>
        </div>
      </div>

      {{-- C. Detail Waktu Sewa --}}
      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">C. Detail Waktu Sewa</h6>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Tanggal Mulai Sewa <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_mulai_sewa" id="tglMulai" class="form-control" required value="{{ old('tanggal_mulai_sewa') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Rencana Kembali <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_rencana_kembali" id="tglKembali" class="form-control" required value="{{ old('tanggal_rencana_kembali') }}">
            </div>
          </div>
          <div class="form-text mt-2">
            Hitungan inklusif: 1 Juni s/d 1 Juni = <strong>1 hari</strong>, 1 Juni s/d 2 Juni = <strong>2 hari</strong>.
          </div>
        </div>
      </div>

      {{-- D. Ringkasan Biaya --}}
      <div class="card border mb-3">
        <div class="card-body">
          <div class="sewa-summary-card" id="ringkasanCard">
            <h6>Ringkasan Biaya Sewa</h6>
            <div id="ringkasanEmpty" class="sewa-summary-empty">
              Lengkapi layanan dan tanggal sewa untuk melihat total biaya.
            </div>
            <div id="ringkasanIsi" style="display:none;">
              <div class="sewa-summary-row">
                <span class="label">Layanan</span>
                <span class="value" id="sumLayanan">-</span>
              </div>
              <div class="sewa-summary-row">
                <span class="label">Tarif</span>
                <span class="value">Rp <span id="sumTarif">0</span> <span class="text-muted">/ <span id="sumSatuan">-</span></span></span>
              </div>
              <div class="sewa-summary-row">
                <span class="label">Lama Sewa</span>
                <span class="value"><span id="sumHari">0</span> hari</span>
              </div>
              <div class="sewa-summary-total">
                <span class="label">Total Biaya</span>
                <span class="value">Rp <span id="sumTotal">0</span></span>
              </div>
            </div>
            <div id="ringkasanWarning" class="sewa-summary-warning" style="display:none;">
              Tanggal rencana kembali tidak boleh lebih kecil daripada tanggal mulai sewa.
            </div>
          </div>

          <input type="hidden" name="jumlah_hari_sewa" id="jumlah_hari_sewa" value="0">
          <input type="hidden" name="total_biaya" id="total_biaya" value="0">
        </div>
      </div>

      {{-- E. Detail Pengajuan --}}
      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">E. Detail Pengajuan</h6>
          <div class="mb-3">
            <label class="form-label">Tujuan Peminjaman / Sewa <span class="text-danger">*</span></label>
            <textarea name="tujuan_peminjaman" rows="2" class="form-control" required maxlength="2000">{{ old('tujuan_peminjaman') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Upload Surat Resmi Permohonan (PDF) <span class="text-danger">*</span></label>
            <input type="file" name="surat_resmi" class="form-control" accept="application/pdf" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Upload Bukti Bayar <span class="text-danger">*</span></label>
            <input type="file" name="bukti_bayar" class="form-control" accept="image/jpeg,image/png,image/jpg,image/webp,application/pdf" required>
          </div>
          <div class="mb-0">
            <label class="form-label">Catatan</label>
            <textarea name="catatan" rows="2" class="form-control" maxlength="2000">{{ old('catatan') }}</textarea>
          </div>
        </div>
      </div>

      <button type="submit" id="btnSubmitSewa" class="btn btn-bmn-primary w-100"
              onclick="return confirm('Kirim pengajuan sewa?');">
        <i class="ti ti-send"></i> Ajukan Sewa
      </button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var kategoriSelect = document.getElementById('kategoriSelect');
  var tarifSelect    = document.getElementById('tarifSelect');
  var tarifInfo      = document.getElementById('tarifInfo');
  var tglMulai       = document.getElementById('tglMulai');
  var tglKembali     = document.getElementById('tglKembali');

  var ringkasanEmpty   = document.getElementById('ringkasanEmpty');
  var ringkasanIsi     = document.getElementById('ringkasanIsi');
  var ringkasanWarning = document.getElementById('ringkasanWarning');

  var hiddenHari  = document.getElementById('jumlah_hari_sewa');
  var hiddenTotal = document.getElementById('total_biaya');
  var btnSubmit   = document.getElementById('btnSubmitSewa');

  var isPegawai = @json($isPegawai);

  function formatRp(num) {
    var n = Math.max(0, Math.round(Number(num) || 0));
    return n.toLocaleString('id-ID');
  }

  function parseDate(value) {
    if (!value) return null;
    var parts = value.split('-');
    if (parts.length !== 3) return null;
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    var d = parseInt(parts[2], 10);
    if (!y || !m || !d) return null;
    return new Date(Date.UTC(y, m - 1, d));
  }

  function diffInclusiveDays(start, end) {
    if (!start || !end) return 0;
    var ms = end.getTime() - start.getTime();
    var days = Math.floor(ms / 86400000);
    return days + 1;
  }

  function filterTarif() {
    var kat = kategoriSelect.value;
    Array.prototype.forEach.call(tarifSelect.options, function (opt, idx) {
      if (idx === 0) return;
      opt.hidden = kat !== '' && opt.getAttribute('data-kategori') !== kat;
    });
    if (tarifSelect.selectedIndex > 0 && tarifSelect.options[tarifSelect.selectedIndex].hidden) {
      tarifSelect.selectedIndex = 0;
    }
    refreshAll();
  }

  function selectedOption() {
    var opt = tarifSelect.options[tarifSelect.selectedIndex];
    return (opt && opt.value) ? opt : null;
  }

  function selectedTarif() {
    var opt = selectedOption();
    if (!opt) return 0;
    if (isPegawai) {
      var p = opt.getAttribute('data-tarif-pegawai');
      if (p !== null && p !== '') return parseInt(p, 10) || 0;
    }
    return parseInt(opt.getAttribute('data-tarif-umum') || '0', 10) || 0;
  }

  function updateTarifInfo() {
    var opt = selectedOption();
    if (!opt) {
      tarifInfo.style.display = 'none';
      return;
    }
    var satuanEl = document.getElementById('infoSatuan');
    var tarifEl  = document.getElementById('infoTarif');
    if (satuanEl) satuanEl.textContent = opt.getAttribute('data-satuan') || '-';
    if (tarifEl)  tarifEl.textContent  = formatRp(selectedTarif());
    if (isPegawai) {
      var umumEl = document.getElementById('infoTarifUmum');
      if (umumEl) umumEl.textContent = formatRp(parseInt(opt.getAttribute('data-tarif-umum') || '0', 10));
    }
    tarifInfo.style.display = 'block';
  }

  function updateRingkasan() {
    var opt = selectedOption();
    var mulai = parseDate(tglMulai.value);
    var kembali = parseDate(tglKembali.value);

    ringkasanWarning.style.display = 'none';

    if (mulai && kembali && kembali.getTime() < mulai.getTime()) {
      ringkasanEmpty.style.display = 'none';
      ringkasanIsi.style.display = 'none';
      ringkasanWarning.style.display = 'block';
      hiddenHari.value = '0';
      hiddenTotal.value = '0';
      if (btnSubmit) btnSubmit.disabled = true;
      return;
    }

    if (!opt || !mulai || !kembali) {
      ringkasanEmpty.style.display = 'block';
      ringkasanIsi.style.display = 'none';
      hiddenHari.value = '0';
      hiddenTotal.value = '0';
      if (btnSubmit) btnSubmit.disabled = false;
      return;
    }

    var hari = diffInclusiveDays(mulai, kembali);
    var tarif = selectedTarif();
    var total = tarif * hari;

    document.getElementById('sumLayanan').textContent = opt.getAttribute('data-nama') || '-';
    document.getElementById('sumSatuan').textContent  = opt.getAttribute('data-satuan') || '-';
    document.getElementById('sumTarif').textContent   = formatRp(tarif);
    document.getElementById('sumHari').textContent    = String(hari);
    document.getElementById('sumTotal').textContent   = formatRp(total);

    ringkasanEmpty.style.display = 'none';
    ringkasanIsi.style.display = 'block';

    hiddenHari.value  = String(hari);
    hiddenTotal.value = String(total);
    if (btnSubmit) btnSubmit.disabled = false;
  }

  function refreshAll() {
    updateTarifInfo();
    updateRingkasan();
  }

  kategoriSelect.addEventListener('change', filterTarif);
  tarifSelect.addEventListener('change', refreshAll);
  tglMulai.addEventListener('change', updateRingkasan);
  tglKembali.addEventListener('change', updateRingkasan);

  filterTarif();
})();
</script>
@endpush

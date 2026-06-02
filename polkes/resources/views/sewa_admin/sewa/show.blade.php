@extends('layouts.sewa_admin')

@section('page_title', 'Detail Pengajuan Sewa')
@section('breadcrumbs')
  <a href="{{ route('sewa-admin.dashboard') }}">Dashboard</a> /
  <a href="{{ route('sewa-admin.sewa.index') }}">Sewa</a> /
  {{ $pengajuan->kode_sewa }}
@endsection

@php
  $user = auth()->user();
  $canVerify = $user && method_exists($user, 'canVerifySewaPayment') && $user->canVerifySewaPayment();
  $canCoord = $user && method_exists($user, 'canCoordinateSewa') && $user->canCoordinateSewa();
  $isRetno = $user && method_exists($user, 'isRetnoSewaCoordinator') && $user->isRetnoSewaCoordinator();
@endphp

@section('content')
<div class="d-flex justify-content-between mb-3">
  <div>
    <h3 class="mb-1">Kode: <code>{{ $pengajuan->kode_sewa }}</code></h3>
    <div class="d-flex gap-2 flex-wrap">
      @include('sewa_admin.sewa._status_badge', ['row' => $pengajuan])
      @include('sewa_admin.sewa._payment_badge', ['row' => $pengajuan])
      @include('sewa_admin.sewa._coord_badge', ['row' => $pengajuan])
    </div>
  </div>
  <a href="{{ route('sewa-admin.sewa.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="bmn-card mb-3">
      <div class="bmn-card-header"><h2>Biodata Penyewa</h2></div>
      <div class="bmn-card-body small">
        <div class="row g-2">
          <div class="col-md-6"><strong>Nama:</strong> {{ $pengajuan->nama_lengkap }}</div>
          <div class="col-md-6"><strong>Jenis:</strong> {{ $pengajuan->jenisPenyewaLabel() }}</div>
          <div class="col-md-6">
            <strong>No WA:</strong>
            @php $no = preg_replace('/\D+/', '', (string) $pengajuan->nomor_wa); if (str_starts_with($no, '0')) { $no = '62'.substr($no,1); } @endphp
            <a href="https://wa.me/{{ $no }}" target="_blank" rel="noopener">{{ $pengajuan->nomor_wa }}</a>
          </div>
          <div class="col-md-6">
            <strong>Email:</strong>
            <a href="mailto:{{ $pengajuan->email }}">{{ $pengajuan->email }}</a>
          </div>
        </div>
        <hr>
        <div><strong>Layanan:</strong> {{ optional($pengajuan->tarif)->nama_layanan ?? '-' }} ({{ optional($pengajuan->tarif)->kategori ?? '-' }})</div>
        <div><strong>Satuan:</strong> {{ optional($pengajuan->tarif)->satuan ?? '-' }}</div>
        @php
          $tarifDipakai = $pengajuan->tarifDipakai();
          $jumlahHari   = $pengajuan->jumlahHariDihitung();
          $totalBiaya   = $pengajuan->totalBiayaDihitung();
        @endphp
        <div><strong>Tarif per satuan:</strong>
          {{ $tarifDipakai !== null ? 'Rp '.number_format($tarifDipakai, 0, ',', '.') : '-' }}
        </div>
        <div><strong>Mulai:</strong> {{ optional($pengajuan->tanggal_mulai_sewa)->format('d-m-Y') }}</div>
        <div><strong>Rencana Kembali:</strong> {{ optional($pengajuan->tanggal_rencana_kembali)->format('d-m-Y') }}</div>
        <div><strong>Jumlah Hari Sewa:</strong> {{ $jumlahHari > 0 ? $jumlahHari.' hari' : '-' }}</div>
        <div class="mt-1">
          <strong>Total Biaya:</strong>
          <span class="fw-bold text-primary" style="font-size: 1.05rem;">
            {{ $totalBiaya !== null ? 'Rp '.number_format($totalBiaya, 0, ',', '.') : '-' }}
          </span>
        </div>
        <div><strong>Tujuan:</strong> {{ $pengajuan->tujuan_peminjaman }}</div>
        @if($pengajuan->catatan)<div><strong>Catatan Penyewa:</strong> {{ $pengajuan->catatan }}</div>@endif
        <hr>
        <div class="d-flex gap-2 flex-wrap">
          @if($pengajuan->suratResmiUrl())
            <a href="{{ $pengajuan->suratResmiUrl() }}" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="ti ti-file-text"></i> Lihat Surat Resmi
            </a>
          @endif
          @if($pengajuan->buktiBayarUrl())
            <a href="{{ $pengajuan->buktiBayarUrl() }}" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="ti ti-receipt"></i> Lihat Bukti Bayar
            </a>
          @endif
        </div>
      </div>
    </div>

    <div class="bmn-card mb-3">
      <div class="bmn-card-header"><h2>Hubungi Penyewa</h2></div>
      <div class="bmn-card-body small">
        @if($waUrl !== '')
          <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="btn btn-success btn-sm">
            <i class="ti ti-brand-whatsapp"></i> Hubungi via WhatsApp
          </a>
        @endif
        <button type="button" class="btn btn-outline-secondary btn-sm"
                onclick="navigator.clipboard.writeText(document.getElementById('waMsg').value).then(()=>alert('Pesan disalin'));">
          <i class="ti ti-copy"></i> Copy Pesan WA
        </button>
        <textarea id="waMsg" class="form-control form-control-sm mt-2" rows="6" readonly>{{ $waMessage }}</textarea>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="bmn-card mb-3">
      <div class="bmn-card-header"><h2>Status Pembayaran</h2></div>
      <div class="bmn-card-body small">
        <div class="mb-2">@include('sewa_admin.sewa._payment_badge', ['row' => $pengajuan])</div>

        @if($pengajuan->payment_forwarded_at)
          <div class="alert alert-info py-2 px-3 small">
            <i class="ti ti-arrow-forward"></i>
            Diteruskan ke Bu Indah oleh
            <strong>{{ optional($pengajuan->paymentForwardedBy)->name ?? '-' }}</strong>
            pada {{ $pengajuan->payment_forwarded_at->format('d-m-Y H:i') }}
          </div>
        @endif

        @if($pengajuan->admin_payment_at)
          <div class="small text-muted mb-2">
            Diproses oleh
            <strong>{{ optional($pengajuan->adminPaymentBy)->name ?? '-' }}</strong>
            pada {{ $pengajuan->admin_payment_at->format('d-m-Y H:i') }}
          </div>
        @endif
        @if($pengajuan->admin_payment_note)
          <div class="alert alert-light border small">
            <strong>Catatan Verifikasi:</strong><br>
            {{ $pengajuan->admin_payment_note }}
          </div>
        @endif

        {{-- Tombol Teruskan ke Bu Indah: HANYA Bu Retno + saat pembayaran masih pending --}}
        @if($isRetno && $pengajuan->payment_status === 'pending')
          <form method="post" action="{{ route('sewa-admin.sewa.forward-to-verifier', $pengajuan->id) }}" class="mb-3">
            @csrf
            <button type="submit" class="btn btn-info btn-sm w-100"
                    onclick="return confirm('Teruskan pengajuan ini ke Bu Indah untuk verifikasi pembayaran?');">
              <i class="ti ti-arrow-forward"></i> Teruskan ke Bu Indah
            </button>
          </form>
        @endif

        {{-- Konfirmasi / Tolak Pembayaran: HANYA Bu Indah --}}
        @if($canVerify && $pengajuan->payment_status === 'pending')
          <form method="post" action="{{ route('sewa-admin.sewa.confirm-payment', $pengajuan->id) }}" class="mb-2">
            @csrf
            <label class="form-label small mb-1">Catatan Verifikasi (opsional)</label>
            <textarea name="admin_payment_note" class="form-control form-control-sm mb-2" rows="2"
                      placeholder="Misal: dana masuk Rp 500.000 pada 02-06-2026"></textarea>
            <button type="submit" class="btn btn-success btn-sm w-100"
                    onclick="return confirm('Konfirmasi pembayaran sudah masuk rekening BLU?');">
              <i class="ti ti-check"></i> Konfirmasi Pembayaran
            </button>
          </form>
          <form method="post" action="{{ route('sewa-admin.sewa.reject-payment', $pengajuan->id) }}">
            @csrf
            <label class="form-label small mb-1 text-danger">Alasan Penolakan <span>*</span></label>
            <textarea name="admin_payment_note" class="form-control form-control-sm mb-2" rows="2"
                      placeholder="Wajib diisi" required></textarea>
            <button type="submit" class="btn btn-danger btn-sm w-100">
              <i class="ti ti-x"></i> Tolak Pembayaran
            </button>
          </form>
        @endif
      </div>
    </div>

    @if($pengajuan->payment_status === 'confirmed')
      <div class="bmn-card mb-3">
        <div class="bmn-card-header"><h2>Status Koordinasi</h2></div>
        <div class="bmn-card-body small">
          <div class="mb-2">@include('sewa_admin.sewa._coord_badge', ['row' => $pengajuan])</div>

          @if($pengajuan->coordination_at)
            <div class="small text-muted mb-2">
              Diproses oleh
              <strong>{{ optional($pengajuan->coordinationBy)->name ?? '-' }}</strong>
              pada {{ $pengajuan->coordination_at->format('d-m-Y H:i') }}
            </div>
          @endif
          @if($pengajuan->coordination_note)
            <div class="alert alert-light border small">
              <strong>Catatan Koordinasi:</strong><br>
              {{ $pengajuan->coordination_note }}
            </div>
          @endif

          @if($canCoord)
            @if($pengajuan->coordination_status === 'pending')
              <form method="post" action="{{ route('sewa-admin.sewa.mark-sent-to-team', $pengajuan->id) }}" class="mb-2">
                @csrf
                <textarea name="coordination_note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan koordinasi (opsional)"></textarea>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                  <i class="ti ti-send"></i> Tandai Sudah Diteruskan ke Tim
                </button>
              </form>
            @endif

            @if($pengajuan->coordination_status !== 'done')
              <form method="post" action="{{ route('sewa-admin.sewa.mark-coordination-done', $pengajuan->id) }}">
                @csrf
                <textarea name="coordination_note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan akhir (opsional)"></textarea>
                <button type="submit" class="btn btn-bmn-primary btn-sm w-100"
                        onclick="return confirm('Tandai koordinasi sudah selesai (hari H sukses)?');">
                  <i class="ti ti-circle-check"></i> Tandai Koordinasi Selesai
                </button>
              </form>
            @endif
          @endif
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

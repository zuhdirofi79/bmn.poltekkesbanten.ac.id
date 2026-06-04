@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Status Permintaan ' . $permintaan->nomor_permintaan)

@php
  use App\Support\Persediaan\UnitKerja;
  use App\Models\Persediaan\PersediaanPermintaan as P;

  $status = $permintaan->status;
  $isRejected = $status === P::STATUS_DITOLAK;

  // progress: 0..5 (indeks tahap terakhir yang "done")
  $progress = match ($status) {
      P::STATUS_DRAFT       => 0,
      P::STATUS_DIAJUKAN, P::STATUS_MENUNGGU => 1,
      P::STATUS_DISETUJUI   => 2,
      P::STATUS_DITOLAK     => 2,
      P::STATUS_DIKELUARKAN => 3,
      P::STATUS_SELESAI     => 5,
      default               => 0,
  };

  $steps = [
      ['t' => 'Permintaan Dibuat', 's' => 'Permintaan tersimpan di sistem', 'i' => 'ti-clipboard-text'],
      ['t' => 'Menunggu Persetujuan Admin Persediaan', 's' => 'Diteruskan ke admin Persediaan', 'i' => 'ti-user-check'],
      ['t' => $isRejected ? 'Ditolak' : 'Disetujui', 's' => $isRejected ? 'Permintaan ditolak admin' : 'Permintaan disetujui admin', 'i' => $isRejected ? 'ti-x' : 'ti-circle-check'],
      ['t' => 'Pengeluaran Barang', 's' => 'Barang dikeluarkan dari gudang', 'i' => 'ti-package'],
      ['t' => 'Stok Berkurang Otomatis', 's' => 'Stok unit kerja diperbarui', 'i' => 'ti-chart-bar'],
      ['t' => 'Selesai', 's' => 'Permintaan selesai diproses', 'i' => 'ti-flag-check'],
  ];
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=1">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="prs-section-title mb-0">Status Permintaan</span>
    <a href="{{ route('public.persediaan.permintaan.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left"></i> Permintaan Saya
    </a>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="prs-public-card h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted" style="font-size:.8rem;">Nomor Permintaan</div>
            <h4 class="fw-bold">{{ $permintaan->nomor_permintaan }}</h4>
          </div>
          <span class="prs-badge {{ $status }}">{{ $permintaan->statusLabel() }}</span>
        </div>
        <hr>
        <p class="mb-1"><span class="text-muted">Unit Kerja:</span> <strong>{{ UnitKerja::label($permintaan->unit_kerja) }}</strong></p>
        <p class="mb-1"><span class="text-muted">Tanggal:</span> <strong>{{ optional($permintaan->tanggal_permintaan)->format('d M Y') }}</strong></p>
        <p class="mb-1"><span class="text-muted">Pemohon:</span> <strong>{{ $pemohon->nama }}</strong></p>
        <p class="mb-1"><span class="text-muted">Keterangan:</span> {{ $permintaan->keterangan }}</p>

        @if($isRejected && $permintaan->catatan_admin)
          <div class="alert alert-danger mt-3 mb-0">
            <strong>Alasan Penolakan:</strong><br>{{ $permintaan->catatan_admin }}
          </div>
        @endif

        <div class="d-flex flex-wrap gap-2 mt-3">
          @if($status === \App\Models\Persediaan\PersediaanPermintaan::STATUS_DRAFT)
            <form method="POST" action="{{ route('public.persediaan.permintaan.ajukan', $permintaan->id) }}"
                  onsubmit="return confirm('Ajukan permintaan ini ke admin Persediaan?');">
              @csrf
              <button class="btn btn-prs-blue btn-sm"><i class="ti ti-send"></i> Ajukan Permintaan</button>
            </form>
          @endif
          <a href="{{ route('public.persediaan.permintaan.pdf', $permintaan->id) }}" target="_blank"
             class="btn btn-outline-secondary btn-sm"><i class="ti ti-printer"></i> Cetak PDF</a>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="prs-public-card h-100">
        <h6 class="fw-bold mb-3">Alur Permintaan</h6>
        <ul class="prs-timeline">
          @foreach($steps as $idx => $step)
            @php
              $cls = '';
              if ($isRejected && $idx === 2) { $cls = 'reject'; }
              elseif ($idx < $progress) { $cls = 'done'; }
              elseif ($idx === $progress) { $cls = 'active'; }
            @endphp
            <li class="{{ $cls }}">
              <span class="prs-step-ico"><i class="ti {{ $step['i'] }}"></i></span>
              <div class="prs-step-title">{{ $step['t'] }}</div>
              <div class="prs-step-sub">{{ $step['s'] }}</div>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>

  <div class="prs-public-card mt-4">
    <h6 class="fw-bold mb-3">Daftar Barang Diminta</h6>
    <div class="table-responsive">
      <table class="prs-table">
        <thead>
          <tr>
            <th style="width:48px;">No</th>
            <th>Kode</th>
            <th>Nama Barang</th>
            <th>Satuan</th>
            <th class="text-end">Diminta</th>
            <th class="text-end">Disetujui</th>
          </tr>
        </thead>
        <tbody>
          @foreach($permintaan->items as $i => $item)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td><code>{{ $item->kode_barang }}</code></td>
              <td>{{ $item->nama_barang }}</td>
              <td>{{ $item->satuan }}</td>
              <td class="num">{{ number_format($item->jumlah_diminta, 0, ',', '.') }}</td>
              <td class="num">{{ is_null($item->jumlah_disetujui) ? '-' : number_format($item->jumlah_disetujui, 0, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

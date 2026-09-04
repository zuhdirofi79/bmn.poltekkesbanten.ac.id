@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Detail Barang BHP')

@section('content')
<div class="pa-card bhp-detail-admin">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h2 class="mb-1">{{ $barang->nama_barang }}</h2>
      <div class="text-muted">{{ $barang->kode_barang }}</div>
    </div>
    <a href="{{ route('bhp.admin.master-barang.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
  </div>

  <div class="row g-3">
    <div class="col-md-4">
      @if($fotoUrl)
        <img src="{{ $fotoUrl }}" alt="Foto {{ $barang->nama_barang }}" class="bhp-foto-detail">
      @else
        <div class="bhp-foto-placeholder bhp-foto-placeholder-lg">Belum ada foto</div>
      @endif
    </div>
    <div class="col-md-8">
      <div class="row g-2 bhp-detail-meta">
        <div class="col-6">
          <label class="text-muted small">Nama Barang</label>
          <div class="fw-semibold">{{ $barang->nama_barang }}</div>
        </div>
        <div class="col-6">
          <label class="text-muted small">Kode Barang</label>
          <div class="fw-semibold">{{ $barang->kode_barang }}</div>
        </div>
        <div class="col-6">
          <label class="text-muted small">Merk</label>
          <div class="fw-semibold">{{ $barang->merk ?: '—' }}</div>
        </div>
        <div class="col-6">
          <label class="text-muted small">Satuan</label>
          <div class="fw-semibold">{{ $barang->satuan }}</div>
        </div>
        <div class="col-6">
          <label class="text-muted small">Stok Saat Ini</label>
          <div class="bhp-stok-inline">{{ $stokSaatIni }} {{ $barang->satuan }}</div>
          <a href="#riwayat-stok" class="small">Riwayat Stok</a>
        </div>
        <div class="col-6">
          <label class="text-muted small">Status</label>
          <div>
            @if($barang->isAktif())
              <span class="badge bg-success">Aktif</span>
            @else
              <span class="badge bg-secondary">Nonaktif</span>
            @endif
          </div>
        </div>
      </div>

      <div class="mt-3">
        <label class="text-muted small d-block">Barcode</label>
        @if($barcodeUrl)
          <div class="barcode-preview barcode-preview-lg">
            <img src="{{ $barcodeUrl }}" alt="Barcode {{ $barang->kode_barang }}">
          </div>
          <div class="bhp-barcode-actions mt-2">
            <a href="{{ $barcodeUrl }}" class="btn btn-outline-secondary btn-sm" target="_blank">Lihat</a>
            <a href="{{ route('bhp.admin.master-barang.download-barcode', $barang->id) }}" class="btn btn-outline-secondary btn-sm">Download</a>
            <a href="{{ route('bhp.admin.master-barang.print-barcode', $barang->id) }}" class="btn btn-outline-primary btn-sm" target="_blank">Print</a>
          </div>
        @else
          <div class="text-muted mb-2">Barcode belum tersedia</div>
          <form method="POST" action="{{ route('bhp.admin.master-barang.generate-barcode', $barang->id) }}">
            @csrf
            <button type="submit" class="btn btn-prs-green btn-sm">Generate Barcode</button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="pa-card mt-3" id="riwayat-stok">
  <h2 class="mb-3">Riwayat Stok</h2>
  <div class="table-responsive">
    <table class="pa-table bhp-riwayat-table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Jenis</th>
          <th>Perubahan</th>
          <th>Stok Sebelum</th>
          <th>Stok Sesudah</th>
          <th>Keterangan</th>
          <th>Admin</th>
        </tr>
      </thead>
      <tbody>
        @forelse($riwayat as $m)
          @php $delta = $m->signedDelta(); @endphp
          <tr>
            <td>{{ optional($m->created_at)->format('d-m-Y H:i') }}</td>
            <td>{{ $m->jenisLabel() }}</td>
            <td class="{{ $delta >= 0 ? 'bhp-delta-plus' : 'bhp-delta-minus' }}">{{ $delta > 0 ? '+' . $delta : $delta }}</td>
            <td>{{ $m->stok_sebelum }}</td>
            <td>{{ $m->stok_sesudah }}</td>
            <td>{{ $m->catatan ?: '—' }}</td>
            <td>{{ optional($m->creator)->name ?: '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-3">Belum ada pergerakan stok.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

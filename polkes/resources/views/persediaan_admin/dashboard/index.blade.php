@extends('layouts.persediaan_admin')

@section('page_title', 'Dashboard Persediaan')

@section('content')
<div class="pa-stat-row">
  <div class="pa-stat blue">
    <div class="lbl">Total Persediaan</div>
    <div class="val">{{ number_format($totalPersediaan, 0, ',', '.') }}</div>
    <div class="sub">Jenis Barang</div>
  </div>
  <div class="pa-stat green">
    <div class="lbl">Barang Masuk</div>
    <div class="val">{{ number_format($barangMasukBulanIni, 0, ',', '.') }}</div>
    <div class="sub">Bulan Ini</div>
  </div>
  <div class="pa-stat amber">
    <div class="lbl">Barang Keluar</div>
    <div class="val">{{ number_format($barangKeluarBulanIni, 0, ',', '.') }}</div>
    <div class="sub">Bulan Ini</div>
  </div>
  <div class="pa-stat purple">
    <div class="lbl">Nilai Persediaan</div>
    <div class="val" style="font-size:1.4rem;">Rp {{ number_format($nilaiPersediaan, 0, ',', '.') }}</div>
    <div class="sub">Total Nilai</div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-4">
    <div class="d-flex flex-column gap-3">
      <div class="pa-mini">
        <span class="ic red"><i class="ti ti-alert-triangle"></i></span>
        <div>
          <div class="big">{{ number_format($stokMenipis, 0, ',', '.') }}</div>
          <div class="cap">Stok Menipis (Jenis Barang)</div>
        </div>
      </div>
      <div class="pa-mini">
        <span class="ic amber"><i class="ti ti-bell"></i></span>
        <div>
          <div class="big">{{ number_format($permintaanPending, 0, ',', '.') }}</div>
          <div class="cap">Permintaan Pending</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="pa-card mt-0">
      <h2>Grafik Persediaan (6 Bulan Terakhir)</h2>
      <div id="paChart" style="min-height:280px;"></div>
    </div>
  </div>
</div>

<div class="pa-card">
  <div class="d-flex justify-content-between align-items-center">
    <h2>Permintaan Terbaru</h2>
    <a href="{{ route('persediaan.admin.permintaan.index') }}" class="btn btn-prs-blue btn-sm">Lihat Semua</a>
  </div>
  <div class="table-responsive mt-2">
    <table class="pa-table">
      <thead>
        <tr><th>Nomor</th><th>Unit Kerja</th><th>Tanggal</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($recent as $row)
          <tr>
            <td><code>{{ $row->nomor_permintaan }}</code></td>
            <td>{{ $row->unit_kerja }}</td>
            <td>{{ optional($row->tanggal_permintaan)->format('d-m-Y') }}</td>
            <td><span class="prs-badge {{ $row->status }}">{{ $row->statusLabel() }}</span></td>
            <td class="text-end"><a href="{{ route('persediaan.admin.permintaan.show', $row->id) }}" class="btn btn-outline-primary btn-sm">Detail</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-3">Belum ada permintaan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('thema/assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
(function () {
  var el = document.querySelector('#paChart');
  if (!el || typeof ApexCharts === 'undefined') return;
  var options = {
    chart: { type: 'line', height: 280, toolbar: { show: false } },
    colors: ['#2f6fdb', '#e0a52b'],
    stroke: { curve: 'smooth', width: 3 },
    series: [
      { name: 'Barang Masuk', data: @json($chartMasuk) },
      { name: 'Barang Keluar', data: @json($chartKeluar) }
    ],
    xaxis: { categories: @json($chartLabels) },
    legend: { position: 'top' },
    grid: { borderColor: '#eef1f6' },
    dataLabels: { enabled: false }
  };
  new ApexCharts(el, options).render();
})();
</script>
@endpush

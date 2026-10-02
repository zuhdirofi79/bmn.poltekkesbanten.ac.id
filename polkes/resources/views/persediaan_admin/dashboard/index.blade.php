@extends('layouts.persediaan_admin')

@section('page_title', 'Dashboard Persediaan')

@section('content')
<div class="pa-stat-row">
  <div class="pa-stat blue">
    <div class="lbl">Total Stok Saat Ini</div>
    <div class="val">{{ number_format($totalStokSaatIni, 0, ',', '.') }}</div>
    <div class="sub">{{ number_format($totalJenisBarang, 0, ',', '.') }} jenis barang aktif</div>
  </div>
  <div class="pa-stat green">
    <div class="lbl">Nilai Barang Masuk</div>
    <div class="val pa-money">Rp {{ number_format($nilaiBarangMasuk, 0, ',', '.') }}</div>
    <div class="sub">{{ number_format($jumlahBarangMasuk, 0, ',', '.') }} unit masuk bulan ini</div>
  </div>
  <div class="pa-stat amber">
    <div class="lbl">Nilai Barang Keluar</div>
    <div class="val pa-money">Rp {{ number_format($nilaiBarangKeluar, 0, ',', '.') }}</div>
    <div class="sub">{{ number_format($jumlahBarangKeluar, 0, ',', '.') }} unit keluar bulan ini</div>
  </div>
  <div class="pa-stat purple">
    <div class="lbl">Nilai Persediaan Saat Ini</div>
    <div class="val pa-money">Rp {{ number_format($nilaiPersediaan, 0, ',', '.') }}</div>
    <div class="sub">Nilai stok realtime</div>
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
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="mb-0">Grafik Persediaan (6 Bulan Terakhir)</h2>
        <div class="btn-group btn-group-sm" role="group" aria-label="Mode grafik">
          <button type="button" class="btn btn-prs-blue" id="paChartQty">Kuantitas</button>
          <button type="button" class="btn btn-outline-secondary" id="paChartRp">Nilai Rupiah</button>
        </div>
      </div>
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

@push('styles')
<style>
  .pa-stat .val.pa-money { font-size: 1.25rem; line-height: 1.25; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('thema/assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
(function () {
  var el = document.querySelector('#paChart');
  if (!el || typeof ApexCharts === 'undefined') return;
  var qty = {
    series: [
      { name: 'Barang Masuk', data: @json($chartMasuk) },
      { name: 'Barang Keluar', data: @json($chartKeluar) }
    ],
    yTitle: 'Kuantitas'
  };
  var rp = {
    series: [
      { name: 'Nilai Masuk', data: @json($chartNilaiMasuk) },
      { name: 'Nilai Keluar', data: @json($chartNilaiKeluar) }
    ],
    yTitle: 'Rupiah'
  };
  var chart = new ApexCharts(el, {
    chart: { type: 'line', height: 280, toolbar: { show: false } },
    colors: ['#2f6fdb', '#e0a52b'],
    stroke: { curve: 'smooth', width: 3 },
    series: qty.series,
    xaxis: { categories: @json($chartLabels) },
    yaxis: { title: { text: qty.yTitle }, labels: { formatter: function (v) { return Math.round(v).toLocaleString('id-ID'); } } },
    legend: { position: 'top' },
    grid: { borderColor: '#eef1f6' },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: function (v) { return Number(v).toLocaleString('id-ID'); } } }
  });
  chart.render();
  var btnQty = document.getElementById('paChartQty');
  var btnRp = document.getElementById('paChartRp');
  function activate(mode) {
    var src = mode === 'rp' ? rp : qty;
    chart.updateOptions({
      yaxis: { title: { text: src.yTitle }, labels: { formatter: function (v) { return Math.round(v).toLocaleString('id-ID'); } } }
    });
    chart.updateSeries(src.series);
    btnQty.className = mode === 'qty' ? 'btn btn-prs-blue' : 'btn btn-outline-secondary';
    btnRp.className = mode === 'rp' ? 'btn btn-prs-blue' : 'btn btn-outline-secondary';
  }
  btnQty.addEventListener('click', function () { activate('qty'); });
  btnRp.addEventListener('click', function () { activate('rp'); });
})();
</script>
@endpush

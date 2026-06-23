@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Dashboard BHP Kebersihan')

@section('content')
<div class="pa-stat-row">
  <div class="pa-stat blue">
    <div class="lbl">Total Barang BHP</div>
    <div class="val">{{ number_format($totalBarang, 0, ',', '.') }}</div>
    <div class="sub">Jenis Barang</div>
  </div>
  <div class="pa-stat amber">
    <div class="lbl">Permintaan Pending</div>
    <div class="val">{{ number_format($permintaanPending, 0, ',', '.') }}</div>
    <div class="sub">Menunggu Verifikasi</div>
  </div>
  <div class="pa-stat green">
    <div class="lbl">Permintaan Disetujui</div>
    <div class="val">{{ number_format($permintaanDisetujui, 0, ',', '.') }}</div>
    <div class="sub">Siap Diambil</div>
  </div>
  <div class="pa-stat purple">
    <div class="lbl">Barang Keluar</div>
    <div class="val">{{ number_format($barangKeluarBulanIni, 0, ',', '.') }}</div>
    <div class="sub">Bulan Ini</div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-4">
    <div class="pa-mini">
      <span class="ic blue"><i class="ti ti-stack-2"></i></span>
      <div>
        <div class="big">{{ number_format($stokAkhirTotal, 0, ',', '.') }}</div>
        <div class="cap">Stok Akhir Total</div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="pa-card mt-0">
      <h2>Grafik Barang Keluar (6 Bulan Terakhir)</h2>
      <div id="bhpChart" style="min-height:280px;"></div>
    </div>
  </div>
</div>

<div class="pa-card">
  <div class="d-flex justify-content-between align-items-center">
    <h2>Permintaan Terbaru</h2>
    @if(\App\Support\BhpKebersihan\BhpAccess::canAccessMenu($role, 'permintaan'))
    <a href="{{ route('bhp.admin.permintaan.index') }}" class="btn btn-prs-blue btn-sm">Lihat Semua</a>
    @endif
  </div>
  <div class="table-responsive mt-2">
    <table class="pa-table">
      <thead>
        <tr><th>Nomor</th><th>Barang</th><th>Pengambil</th><th>Tanggal</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($recent as $row)
          <tr>
            <td><code>{{ $row->nomor_permintaan }}</code></td>
            <td>{{ $row->barang?->nama_barang }}</td>
            <td>{{ $row->nama_pengambil }}</td>
            <td>{{ optional($row->tanggal_pengambilan)->format('d-m-Y') }}</td>
            <td><span class="prs-badge {{ $row->status }}">{{ $row->statusLabel() }}</span></td>
            <td class="text-end">
              @if(\App\Support\BhpKebersihan\BhpAccess::canAccessMenu($role, 'permintaan'))
              <a href="{{ route('bhp.admin.permintaan.show', $row->id) }}" class="btn btn-outline-primary btn-sm">Detail</a>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-3">Belum ada permintaan.</td></tr>
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
  var el = document.querySelector('#bhpChart');
  if (!el || typeof ApexCharts === 'undefined') return;
  new ApexCharts(el, {
    chart: { type: 'bar', height: 280, toolbar: { show: false } },
    colors: ['#e0a52b'],
    series: [{ name: 'Barang Keluar', data: @json($chartKeluar) }],
    xaxis: { categories: @json($chartLabels) },
    dataLabels: { enabled: false }
  }).render();
})();
</script>
@endpush

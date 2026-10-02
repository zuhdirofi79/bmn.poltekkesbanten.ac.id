@extends('layouts.persediaan_admin')

@section('page_title', 'Laporan Persediaan')

@php use App\Support\Persediaan\UnitKerja; @endphp

@section('content')
<div class="pa-card mt-0 no-print">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Unit Kerja</label>
      <select name="unit" class="form-select form-select-sm">
        <option value="">Semua Unit</option>
        @foreach($units as $u)
          <option value="{{ $u }}" @selected(($filters['unit'] ?? '') === $u)>{{ UnitKerja::label($u) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Dari Tanggal</label>
      <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-2">
      <label class="form-label">Sampai Tanggal</label>
      <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-3">
      <label class="form-label">Kode / Nama Barang</label>
      <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Cari barang">
    </div>
    <div class="col-12 d-flex flex-wrap gap-2">
      <button class="btn btn-prs-blue btn-sm" type="submit"><i class="ti ti-filter"></i> Terapkan Filter</button>
      <a href="{{ route('persediaan.admin.laporan.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
      <a href="{{ route('persediaan.admin.laporan.export', request()->query()) }}" class="btn btn-prs-green btn-sm">
        <i class="ti ti-file-spreadsheet"></i> Export Excel
      </a>
      <a href="{{ route('persediaan.admin.laporan.pdf', request()->query()) }}" class="btn btn-outline-primary btn-sm">
        <i class="ti ti-file-type-pdf"></i> Cetak PDF
      </a>
    </div>
  </form>
</div>

<div class="pa-stat-row">
  <div class="pa-stat blue">
    <div class="lbl">Total Stok Saat Ini</div>
    <div class="val">{{ number_format($summary['total_stok_saat_ini'], 0, ',', '.') }}</div>
    <div class="sub">Total kuantitas seluruh barang</div>
  </div>
  <div class="pa-stat green">
    <div class="lbl">Nilai Barang Masuk</div>
    <div class="val pa-money">Rp {{ number_format($summary['nilai_barang_masuk'], 0, ',', '.') }}</div>
    <div class="sub">Barang masuk pada periode terpilih</div>
  </div>
  <div class="pa-stat amber">
    <div class="lbl">Nilai Barang Keluar</div>
    <div class="val pa-money">Rp {{ number_format($summary['nilai_barang_keluar'], 0, ',', '.') }}</div>
    <div class="sub">Barang keluar pada periode terpilih</div>
  </div>
  <div class="pa-stat purple">
    <div class="lbl">Nilai Persediaan Saat Ini</div>
    <div class="val pa-money">Rp {{ number_format($summary['nilai_persediaan_saat_ini'], 0, ',', '.') }}</div>
    <div class="sub">Nilai stok hingga akhir periode</div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-6 col-lg">
    <div class="pa-mini">
      <div>
        <div class="big">{{ number_format($summary['total_jenis_barang'], 0, ',', '.') }}</div>
        <div class="cap">Total Jenis Barang</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="pa-mini">
      <div>
        <div class="big">{{ number_format($summary['jumlah_barang_masuk'], 0, ',', '.') }}</div>
        <div class="cap">Jumlah Barang Masuk</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="pa-mini">
      <div>
        <div class="big">{{ number_format($summary['jumlah_barang_keluar'], 0, ',', '.') }}</div>
        <div class="cap">Jumlah Barang Keluar</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="pa-mini">
      <div>
        <div class="big">{{ number_format($summary['jumlah_koreksi'], 0, ',', '.') }}</div>
        <div class="cap">Koreksi Opname</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="pa-mini">
      <div>
        <div class="big">{{ number_format($permintaanPending, 0, ',', '.') }}</div>
        <div class="cap">Permintaan Pending</div>
      </div>
    </div>
  </div>
</div>

@if((int) $summary['barang_tanpa_harga'] > 0)
  <div class="alert alert-warning mt-3 mb-0">{{ number_format($summary['barang_tanpa_harga'], 0, ',', '.') }} barang aktif tidak memiliki harga satuan. Nilainya dihitung 0.</div>
@endif

<div class="pa-card">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h2 class="mb-0">Grafik Pergerakan</h2>
    <div class="btn-group btn-group-sm no-print" role="group" aria-label="Mode grafik">
      <button type="button" class="btn btn-prs-blue" id="lapChartQty">Kuantitas</button>
      <button type="button" class="btn btn-outline-secondary" id="lapChartRp">Nilai Rupiah</button>
    </div>
  </div>
  <div id="lapChart" style="min-height:280px;"></div>
</div>

<div class="pa-card">
  <h2>Rekonsiliasi Persediaan</h2>
  <p class="text-muted mb-2" style="font-size:.85rem;">
    Stok saat ini = stok pembuka + masuk − keluar + koreksi.
    Nilai memakai harga satuan master.
    Total di footer mencakup seluruh hasil filter, bukan hanya halaman ini.
  </p>
  <div class="table-responsive">
    <table class="pa-table laporan-rekon">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Barang</th>
          <th>Unit</th>
          <th>Satuan</th>
          <th class="text-end">Stok Pembuka</th>
          <th class="text-end">Masuk</th>
          <th class="text-end">Keluar</th>
          <th class="text-end">Koreksi</th>
          <th class="text-end">Stok Saat Ini</th>
          <th class="text-end">Harga</th>
          <th class="text-end">Nilai Pembuka</th>
          <th class="text-end">Nilai Masuk</th>
          <th class="text-end">Nilai Keluar</th>
          <th class="text-end">Nilai Koreksi</th>
          <th class="text-end">Nilai Saat Ini</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          <tr>
            <td>{{ $row['kode'] }}</td>
            <td>{{ $row['nama'] }}</td>
            <td>{{ $row['unit'] }}</td>
            <td>{{ $row['satuan'] }}</td>
            <td class="text-end">{{ number_format($row['stok_pembuka'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['masuk'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['keluar'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['koreksi'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['stok_saat_ini'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['harga'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['nilai_pembuka'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['nilai_masuk'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['nilai_keluar'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['nilai_koreksi'], 0, ',', '.') }}</td>
            <td class="text-end">{{ number_format($row['nilai_saat_ini'], 0, ',', '.') }}</td>
          </tr>
        @empty
          <tr><td colspan="15" class="text-center text-muted py-3">Tidak ada barang pada filter ini.</td></tr>
        @endforelse
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4">Total</th>
          <th class="text-end">{{ number_format($summary['total_stok_pembuka'], 0, ',', '.') }}</th>
          <th class="text-end">{{ number_format($summary['jumlah_barang_masuk'], 0, ',', '.') }}</th>
          <th class="text-end">{{ number_format($summary['jumlah_barang_keluar'], 0, ',', '.') }}</th>
          <th class="text-end">{{ number_format($summary['jumlah_koreksi'], 0, ',', '.') }}</th>
          <th class="text-end">{{ number_format($summary['total_stok_saat_ini'], 0, ',', '.') }}</th>
          <th></th>
          <th class="text-end">{{ number_format($summary['nilai_persediaan_awal'], 0, ',', '.') }}</th>
          <th class="text-end">{{ number_format($summary['nilai_barang_masuk'], 0, ',', '.') }}</th>
          <th class="text-end">{{ number_format($summary['nilai_barang_keluar'], 0, ',', '.') }}</th>
          <th class="text-end">{{ number_format($summary['nilai_koreksi_opname'], 0, ',', '.') }}</th>
          <th class="text-end">{{ number_format($summary['nilai_persediaan_saat_ini'], 0, ',', '.') }}</th>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="mt-3 no-print">{{ $rows->links() }}</div>
</div>
@endsection

@push('styles')
<style>
  .pa-stat .val.pa-money { font-size: 1.25rem; line-height: 1.25; }
  .laporan-rekon { min-width: 1400px; }
  .laporan-rekon th, .laporan-rekon td { white-space: nowrap; font-size: .78rem; }
  .laporan-rekon tfoot th { border-top: 2px solid #d7deea; background: #f7faff; }
  @media print {
    .pa-sidebar, .pa-topbar, .no-print { display: none !important; }
    .pa-main, .pa-content { margin: 0 !important; padding: 0 !important; }
  }
</style>
@endpush

@push('scripts')
<script src="{{ asset('thema/assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<script>
(function () {
  var el = document.querySelector('#lapChart');
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
    yaxis: { title: { text: 'Kuantitas' }, labels: { formatter: function (v) { return Math.round(v).toLocaleString('id-ID'); } } },
    legend: { position: 'top' },
    grid: { borderColor: '#eef1f6' },
    dataLabels: { enabled: false }
  });
  chart.render();
  var btnQty = document.getElementById('lapChartQty');
  var btnRp = document.getElementById('lapChartRp');
  function activate(mode) {
    var src = mode === 'rp' ? rp : qty;
    chart.updateOptions({ yaxis: { title: { text: src.yTitle } } });
    chart.updateSeries(src.series);
    btnQty.className = mode === 'qty' ? 'btn btn-prs-blue' : 'btn btn-outline-secondary';
    btnRp.className = mode === 'rp' ? 'btn btn-prs-blue' : 'btn btn-outline-secondary';
  }
  if (btnQty) btnQty.addEventListener('click', function () { activate('qty'); });
  if (btnRp) btnRp.addEventListener('click', function () { activate('rp'); });
})();
</script>
@endpush

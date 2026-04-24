@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Barang Dimusnahkan <span class="text-muted fw-normal">(di luar stok aktif)</span></h5>
            <p class="text-muted small">Data di bawah ini tidak masuk ke daftar bahan medis aktif, notifikasi expired, atau laporan stok utama.</p>

            <table id="lapBahanDimusnahkanTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Kode Barang</th>
                        <th>Merk</th>
                        <th>Stok</th>
                        <th>tgl Expired</th>
                        <th>Tanggal Pemusnahan</th>
                        <th>Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $a->nama }}</td>
                    <td>{{ $a->kode_barang }}</td>
                    <td>{{ $a->kimia }}</td>
                    <td>{{ $a->stok }} {{ $a->satuan }}</td>
                    <td>{{ $a->tgl_expired }}</td>
                    <td>{{ optional($a->tanggal_pemusnahan)->format('Y-m-d') ?? '-' }}</td>
                    <td>{{ $a->tindak_lanjut ?? '-' }}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>

            @push('scripts')
            <script>
              $(document).ready(function () {
                if (!document.getElementById('lapBahanDimusnahkanTable')) return;
                $('#lapBahanDimusnahkanTable').DataTable({
                  dom: 'Bfrtip',
                  order: [[1, 'asc']],
                  pageLength: 25,
                  buttons: [
                    { extend: 'excel', exportOptions: { columns: ':visible' } },
                    { extend: 'pdf', exportOptions: { columns: ':visible' } },
                    { extend: 'print', exportOptions: { columns: ':visible' } }
                  ]
                });
              });
            </script>
            @endpush
          </div>
        </div>
      </div>
    </div>

@endsection

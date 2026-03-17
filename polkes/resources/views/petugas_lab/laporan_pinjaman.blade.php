@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Pinjaman</h5>

                <p><b>Lihat Laporan</b></p>

                <form action="{{ url('petugas/laporan_pinjaman') }}" method="get">
                
                <div style="" class="d-flex justify-content-center">
                <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" style="width:300px;" required value="{{$tgl_awal}}">
                 &nbsp; &nbsp; Sampai Dengan &nbsp; &nbsp;
                <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" style="width:300px;" required value="{{$tgl_akhir}}">
                 &nbsp;
                <button type="submit" class="btn btn-outline-primary">Submit</button>
                </div>
                </form>
                <br>

                @if($laporan_pinjaman)

                <div style="float: left;">
                  <a href="{{url('petugas/print_lap_pinjaman?tgl_awal='.$tgl_awal.'&tgl_akhir='.$tgl_akhir)}}" class="btn btn-outline-primary btn-sm" target="_blank"><i class="ti ti-printer"></i> Print</a>
                </div>
                    <table id="example1" class="table table-bordered table-striped table-sm">
                      <thead>
                      <tr>
                        <th width="30">No</th>
                        <th>Tgl Pinjam</th>
                        <th>Nama</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>stok</th>
                        <th>Status</th>
                        <th>Petugas</th>
                      </tr>
                      </thead>
                      <tbody>
                      @foreach($laporan_pinjaman as $a)
                      <tr>
                        <td>{{$loop->iteration}}</td>
                        <td>{{substr($a->tgl_pinjaman,0,10)}}</td>
                        <td>{{$a->name}}</td>
                        <td>{{$a->kode_barang}}</td>
                        <td>{{$a->nama_barang}}</td>
                        <td>{{$a->stok_pinjaman}}</td>
                        <td>{{$a->status}}</td>
                        <td>{{$a->petugas}}</td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                @endif

          </div>
        </div>
      </div>
@endsection
@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Aset Laboratorium</h5>

            <!-- <form action="{{ url('petugas/laporan_barang_masuk') }}" method="get">  
              <div style="" class="d-flex justify-content-center">
                <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" style="width:250px;" required>
                 &nbsp; &nbsp; Sampai Dengan &nbsp; &nbsp;
                <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" style="width:250px;" required>
                 &nbsp;
                <button type="submit" class="btn btn-outline-primary">Submit</button>
              </div>
            </form>
            <br> -->

            <table id="exampleButton" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama Alat</th>
                        <th>Tgl Masuk</th>
                        <th>Merk</th>
                        <th>Type</th>
                        <th>No Seri</th>
                        <th>Kode Barang</th>
                        <th>Ruang</th>
                        <th>Stok</th>
                        <th>Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->tgl_masuk}}</td>
                    <td>{{$a->merk}}</td>
                    <td>{{$a->type}}</td>
                    <td>{{$a->no_seri}}</td>
                    <td>{{$a->kode_barang}}</td>
                    <td>{{$a->ruang}}</td>
                    <td>{{$a->stok}} {{$a->satuan}}</td>
                    <td>{{$a->kondisi}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

@endsection
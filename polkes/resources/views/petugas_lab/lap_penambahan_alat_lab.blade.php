@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Penambahan Peralatan Laboratorium</h5>

            <form action="{{ url('petugas/laporan/penambahan_alat_lab') }}" method="get">  
              <div style="" class="d-flex justify-content-center">
                <input type="hidden" name="jurusan" value="{{$jurusan}}">
                <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" style="width:250px;" required value="{{$tgl_awal}}">
                 &nbsp; &nbsp; Sampai Dengan &nbsp; &nbsp;
                <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" style="width:250px;" required value="{{$tgl_akhir}}">
                 &nbsp;
                <button type="submit" class="btn btn-outline-primary">Submit</button>
              </div>
            </form>
            <br>

            @if($data)
            <table id="exampleButton" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Tgl Input</th>
                        <th>Nama Barang</th>
                        <th>Kode Barang</th>
                        <th>Stok Penambahan</th>
                        <th>Petugas</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{substr($a->created_at,0,10)}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->kode_barang}}</td>
                    <td>{{$a->stok}} {{$a->satuan}}</td>
                    <td>{{$a->petugas}}</td>
                    <td>{{$a->keterangan}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
            @endif

          </div>
        </div>
      </div>
    </div>

@endsection
@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="{{url('user/daftar_barang?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Peralatan Laboratorium</a>&nbsp;
              <a href="{{url('user/daftar_barangmedis?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Peralatan Medis</a>&nbsp;
              <a href="{{url('user/daftar_barangbhp?jurusan='.$jurusan)}}" class="btn btn-primary btn-sm">Bahan BHP</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">BAHAN BHP - JURUSAN {{strtoupper($jurusan)}}</h5><hr>

            <table id="myTable2" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Kimia</th>
                        <th>Stok</th>
                        <th>tgl Expired</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($bhp_bahan as $b)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$b->nama}}</td>
                    <td>{{$b->kimia}}</td>
                    <td>{{$b->stok}}</td>
                    <td>{{$b->tgl_expired}}</td>
                  </tr>

                  @endforeach
                </tbody>
            </table>

          </div>
        </div>
      </div>

@endsection
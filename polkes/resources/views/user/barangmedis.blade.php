@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="{{url('user/daftar_barang?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Aset Laboratorium</a>&nbsp;
              <a href="{{url('user/daftar_barangmedis?jurusan='.$jurusan)}}" class="btn btn-primary btn-sm">Alat Medis</a>&nbsp;
              <a href="{{url('user/daftar_barangbhp?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Bahan Medis</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">ALAT MEDIS - JURUSAN {{strtoupper($jurusan)}}</h5><hr>

            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Stok</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($bhp_alat as $a)

                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->stok}}</td>
                  </tr>

                  @endforeach
                </tbody>
            </table>

          </div>
        </div>
      </div>

@endsection
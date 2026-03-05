@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Peralatan Medis</h5>
            <table id="exampleButton" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Stok</th>
                        <th>Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->nama}}</td>
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
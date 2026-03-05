@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Detail Pinjaman Kode Barang {{$kode}}</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIM/NID</th>
                        <th>Nama</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $a)
                    <tr>
                        <td>{{$loop->iteration}}</td>
                        <td>{{$a->username}}</td>
                        <td>{{$a->name}}</td>
                        <td>{{$a->kode_barang}}</td>
                        <td>{{$a->nama_barang}}</td>
                        <td>{{$a->stok_pinjaman}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>
@endsection
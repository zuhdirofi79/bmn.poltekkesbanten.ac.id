@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Jenis Peralatan Laboratorium</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Ruangan</th>
                        <th>Tingkat</th>
                        <th>Nama Barang</th>
                        <th>Stok Pinjaman</th>
                        <th>Tgl Pinjaman</th>
                        <th>Petugas Peminjaman</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($alat_lab as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->name}}</td>
                    <td>{{$a->ruangan}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->stok_pinjam}} {{$a->satuan}}</td>
                    <td>{{$a->tgl_pinjaman}}</td>
                    <td>{{$a->petugas_peminjaman}}</td>
                    <td>{{$a->status}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Jenis Peralatan Medis</h5>
            <table id="myTable2" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Ruangan</th>
                        <th>Tingkat</th>
                        <th>Nama Barang</th>
                        <th>Stok Pinjaman</th>
                        <th>Tgl Pinjaman</th>
                        <th>Petugas Peminjaman</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                 @foreach($alat_medis as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->name}}</td>
                    <td>{{$a->ruangan}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->stok_pinjam}}</td>
                    <td>{{$a->tgl_pinjaman}} {{$a->satuan}}</td>
                    <td>{{$a->petugas_peminjaman}}</td>
                    <td>{{$a->status}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>

@endsection
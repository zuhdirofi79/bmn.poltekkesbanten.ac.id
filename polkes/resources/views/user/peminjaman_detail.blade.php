@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Peminjaman Peralatan Detail</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="30">No</th>
                        <th>Jenis</th>
                        <th>Nama Barang</th>
                        <th>Stok</th>
                        @if($jenis=='Bahan Medis')
                        <th>Tgl Pemakaian</th>
                        @else
                        <th>Tgl Pinjaman</th>
                        <th>Tgl Pengembalian</th>
                        @endif
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->jenis}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->stok_pinjam}} {{$a->satuan}}</td>
                    @if($jenis=='Bahan Medis')
                    <td>{{$a->tgl_pinjaman}}</td>
                    @else
                    <td>{{$a->tgl_pinjaman}}</td>
                    <td>{{$a->tgl_pengembalian}}</td>
                    @endif
                    <td>{{$a->status}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>

@endsection
@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Peminjaman Bahan Medis</h5>

            <form action="{{ url('petugas/laporan/pinjaman_bahan_bhp') }}" method="get">  
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
                        <th>Nama</th>
                        <th>Ruangan</th>
                        <th>Tingkat</th>
                        <th>Jenis Barang</th>
                        <th>Nama Barang</th>
                        <th>Stok Pinjaman</th>
                        <th>Tgl Pinjaman</th>
                        <th>Petugas Peminjaman</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->name}}</td>
                    <td>{{$a->ruangan}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->jenis}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->stok_pinjam}} {{$a->satuan}}</td>
                    <td>{{$a->tgl_pinjaman}}</td>
                    <td>{{$a->petugas_peminjaman}}</td>
                    <td>{{$a->status}}</td>
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
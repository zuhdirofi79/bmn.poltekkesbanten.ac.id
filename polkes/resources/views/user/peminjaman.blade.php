@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#ModalForm">Formulir Peminjaman Alat</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">Peminjaman Peralatan</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="30">No</th>
                        <th>Jenis Alat</th>
                        <th>Ruang Lab</th>
                        <th>Tingkat</th>
                        <th>Tgl Permintaan</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->jenis}}</td>
                    <td>{{$a->ruangan}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->tgl_pinjaman}}</td>
                    <td>
                        @if($a->status=='Selesai')
                        <span class="badge bg-success rounded-3 fw-semibold">{{$a->status}}</span>
                        @elseif($a->status=='Menunggu Konfirmasi')
                        <span class="badge bg-dark rounded-3 fw-semibold">{{$a->status}}</span>
                        @else
                        <span class="badge bg-danger rounded-3 fw-semibold">{{$a->status}}</span>
                        @endif
                    </td>
                    <td><a href="{{url('user/peminjaman_detail/'.$a->id)}}" target="_blank" class="btn btn-outline-primary btn-sm">Detail</a></td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>


    <!-- Modal add barang-->
    <div class="modal fade" id="ModalForm" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('user/req_peminjaman')}}" method="get">
          <input type="hidden" name="jurusan" value="{{$jurusan}}">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Formulir Peminjaman Alat</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="jenis" class="col-sm-3 col-form-label">Jenis Alat</label>
              <div class="col-sm-9">
                <select id="jenis" name="jenis" class="form-control" required>
                    <option value="">--Pilih--</option>
                    <option value="Aset Laboratorium">Aset Laboratorium</option>
                    <option value="Alat Medis">Alat Medis</option>
                    <option value="Bahan Medis">Bahan Medis</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="ruang" class="col-sm-3 col-form-label">Ruang</label>
              <div class="col-sm-9">
                <select id="ruang" name="ruang" class="form-control" required>
                    <option value="">--Pilih--</option>
                  @foreach($ruangan as $b)
                    <option value="{{$b->nama_ruang}}">{{$b->kampus}} - {{$b->nama_ruang}}</option>
                  @endforeach
                </select>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Next</button>
          </div>
        </form>
        </div>
      </div>
    </div>

@endsection
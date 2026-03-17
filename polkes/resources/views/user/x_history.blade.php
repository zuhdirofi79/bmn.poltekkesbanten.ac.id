@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Riwayat Peminjaman Barang</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="30">No</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Tgl Permintaan</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->kode_barang}}</td>
                    <td>{{$a->nama_barang}}</td>
                    <td>{{$a->kategori}}</td>
                    <td>{{substr($a->tgl_request,0,10) }}</td>
                    <td>
                      @if($a->status=='Menunggu Konfirmasi') 
                      <span class="badge bg-dark rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                      @elseif($a->status=='Ditolak') 
                      <span class="badge bg-danger rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                      @elseif($a->status=='Dipinjam') 
                      <span class="badge bg-primary rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                      @else
                      <span class="badge bg-success rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                      @endif
                    </td>
                    <td>
                      <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" 
                      data-target="#exampleModal{{$a->id}}">Detail</a>
                    </td>
                  </tr>

                  <!-- Modal add request-->
                  <div class="modal fade" id="exampleModal{{$a->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Detail Request Pinjaman Barang</h5>
                          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                        </div>
                        <div class="modal-body">

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>NIM/NID</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->username}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Nama</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{auth()->user()->name}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Kode Barang</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->kode_barang}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Nama Barang</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->nama_barang}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Sestok</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->stok_pinjaman}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Keperluan</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->keperluan}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Status</p>
                            </div>
                            <div class="col-sm-9">
                              @if($a->status=='Menunggu Konfirmasi') 
                              <span class="badge bg-dark rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                              @elseif($a->status=='Ditolak') 
                              <span class="badge bg-danger rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                              @elseif($a->status=='Dipinjam') 
                              <span class="badge bg-primary rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                              @else
                              <span class="badge bg-success rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                              @endif
                            </div>
                          </div>

                          @if($a->status=='Menunggu Konfirmasi')
                          @elseif($a->status=='Ditolak')
                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Keterangan</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->keterangan}}</p>
                            </div>
                          </div>
                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Petugas Lab</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->petugas}}</p>
                            </div>
                          </div>
                          @else
                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Tgl Pinjam</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->tgl_pinjaman}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Tgl Pengembalian</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->tgl_pengembalian}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Keterangan</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->keterangan}}</p>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <div class="col-sm-3">
                              <p>Petugas Lab</p>
                            </div>
                            <div class="col-sm-9">
                              <p>{{$a->petugas}}</p>
                            </div>
                          </div>
                          @endif

                          
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>
@endsection
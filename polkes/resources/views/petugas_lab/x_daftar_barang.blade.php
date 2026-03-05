@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModal">Tambah Data</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">Daftar Barang</h5>

            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="30">No</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Baik</th>
                        <th>Rusak</th>
                        <th>Status</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($barang as $a)
                    <tr>
                        <td>{{$loop->iteration}}</td>
                        <td>{{$a->kode_barang}}</td>
                        <td>{{$a->nama_barang}}</td>
                        <td>{{$a->kategori}}</td>
                        <td>{{$a->stok}}</td>
                        <td>{{$a->baik}}</td>
                        <td>{{$a->rusak}}</td>
                        <td>
                            @if($a->status=='active') 
                            <span class="badge bg-success rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                            @else
                            <span class="badge bg-danger rounded-3 fw-semibold"><small>{{$a->status}}</small></span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#ModalEdit{{$a->id}}"><span><i class="ti ti-edit fs-4"></i></span></button>
                            <!-- <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#ModalDelete{{$a->id}}"><span><i class="ti ti-trash fs-4"></i></span></button> -->
                        </td>
                    </tr>


                    <!-- Modal edit barang-->
                    <div class="modal fade" id="ModalEdit{{$a->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                        <form action="{{url('petugas/edit_barang')}}" method="post">
                        @csrf
                        <input type="hidden" name="id" value="{{$a->id}}">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                          </div>
                          <div class="modal-body">

                            <div class="form-group mb-3 row">
                              <label for="kode_barang" class="col-sm-3 col-form-label">Kode Barang</label>
                              <div class="col-sm-9">
                                <input type="text" class="form-control" id="kode_barang" name="kode_barang" value="{{$a->kode_barang}}" disabled>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="nama_barang" class="col-sm-3 col-form-label">Nama Barang</label>
                              <div class="col-sm-9">
                                <input type="text" class="form-control" id="nama_barang" name="nama_barang" value="{{$a->nama_barang}}" required>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="kategori" class="col-sm-3 col-form-label">Kategori</label>
                              <div class="col-sm-9">
                                <select id="kategori" name="kategori" class="form-control" required>
                                  @foreach($kategori as $b)
                                      @if($a->kategori==$b->kategori)
                                      <option value="{{$b->kategori}}" selected>{{$b->kode}} - {{$b->kategori}}</option>
                                      @else
                                      <option value="{{$b->kategori}}">{{$b->kode}} - {{$b->kategori}}</option>
                                      @endif
                                  @endforeach
                                </select>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="stok" class="col-sm-3 col-form-label">Stok</label>
                              <div class="col-sm-9">
                                <input type="number" class="form-control" id="stok" name="stok" value="{{$a->stok}}" required>
                              </div>
                            </div>

                            
                            <div class="form-group mb-3 row">
                              <label for="baik" class="col-sm-3 col-form-label">Baik</label>
                              <div class="col-sm-9">
                                <input type="number" class="form-control" id="baik" name="baik" value="{{$a->baik}}" required>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="rusak" class="col-sm-3 col-form-label">Rusak</label>
                              <div class="col-sm-9">
                                <input type="number" class="form-control" id="rusak" name="rusak" value="{{$a->rusak}}" required>
                              </div>
                            </div>
                            

                            <div class="form-group mb-3 row">
                              <label for="status" class="col-sm-3 col-form-label">Status</label>
                              <div class="col-sm-9">
                                <select id="status" name="status" class="form-control" required>
                                  <option value="active" {{($a->status=='active')?'selected':''}}>active</option>
                                  <option value="disable" {{($a->status=='disable')?'selected':''}}>disable</option>
                                </select>
                              </div>
                            </div>

                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Edit</button>
                          </div>
                        </form>
                        </div>
                      </div>
                    </div>

                    <!-- Modal edit barang-->
                    <div class="modal fade" id="ModalDelete{{$a->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                        <form action="{{url('petugas/delete_barang')}}" method="post">
                        @csrf
                        <input type="hidden" name="id" value="{{$a->id}}">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                          </div>
                          <div class="modal-body">

                            <p>Apakah anda yakin ingin menghapus data <b>{{$a->kode_barang}} - {{$a->nama_barang}}</b> ?</p>

                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Delete</button>
                          </div>
                        </form>
                        </div>
                      </div>
                    </div>

                    @endforeach
                </tbody>
            </table>

          </div>
        </div>
      </div>


    <!-- Modal add barang-->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('petugas/add_barang')}}" method="post">
        @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="kode_barang" class="col-sm-3 col-form-label">Kode Barang</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="kode_barang" name="kode_barang" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="nama_barang" class="col-sm-3 col-form-label">Nama Barang</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="kategori" class="col-sm-3 col-form-label">Kategori</label>
              <div class="col-sm-9">
                <select id="kategori" name="kategori" class="form-control" required>
                  <option>--Pilih--</option>
                  @foreach($kategori as $b)
                  <option value="{{$b->kategori}}">{{$b->kode}} - {{$b->kategori}}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="stok" class="col-sm-3 col-form-label">Stok</label>
              <div class="col-sm-9">
                <input type="number" class="form-control" id="stok" name="stok"required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="status" class="col-sm-3 col-form-label">Status</label>
              <div class="col-sm-9">
                <select id="status" name="status" class="form-control" required>
                  <option value="active" selected>active</option>
                  <option value="disable">disable</option>
                </select>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Add</button>
          </div>
        </form>
        </div>
      </div>
    </div>
@endsection
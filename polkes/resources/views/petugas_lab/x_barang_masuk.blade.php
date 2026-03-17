@extends('layouts.thema')

@section('content')
      <div class="container-fluid mt-2">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModal">+ Tambah Barang Masuk</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">Barang Masuk</h5>

            @if($data)
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tgl Masuk</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>stok</th>
                        <th>Petugas</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $a)
                    <tr>
                        <td>{{$loop->iteration}}</td>
                        <td>{{$a->created_at}}</td>
                        <td>{{$a->kode_barang}}</td>
                        <td>{{$a->nama_barang}}</td>
                        <td>{{$a->stok_masuk}}</td>
                        <td>{{$a->petugas}}</td>
                        <td>
                            <a href="" class="btn btn-outline-success btn-sm" data-toggle="modal" data-target="#editModal{{$loop->iteration}}"><i class="ti ti-edit"></i></a>
                            <a href="" class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#deleteModal{{$loop->iteration}}"><i class="ti ti-trash"></i></a>
                        </td>
                    </tr>

                    <!-- Modal edit-->
                    <div class="modal fade" id="editModal{{$loop->iteration}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form action="{{url('petugas/edit_barang_masuk')}}" method="post">
                            @csrf
                            <input type="hidden" name="id" value="{{$a->id}}">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Edit Data</h5>
                            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                          </div>
                          <div class="modal-body">

                            <div class="form-group mb-3 row">
                              <label for="kode_barang" class="col-sm-3 col-form-label">Barang</label>
                              <div class="col-sm-9">
                                <select class="form-control select2" id="kode_barang" name="kode_barang" required>
                                  @foreach($barang as $b)
                                  <option value="{{$b->kode_barang}}" <?=($a->kode_barang==$b->kode_barang)?'selected':'';?>>{{$b->kode_barang}} - {{$b->nama_barang}}</option>
                                  @endforeach
                                </select>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="stok_masuk" class="col-sm-3 col-form-label">stok</label>
                              <div class="col-sm-9">
                                <input type="number" class="form-control" id="stok_masuk" name="stok_masuk" value="{{$a->stok_masuk}}" required>
                              </div>
                            </div>

                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">update</button>
                          </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- Modal Delete-->
                    <div class="modal fade" id="deleteModal{{$loop->iteration}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form action="{{url('petugas/delete_barang_masuk')}}" method="post">
                            @csrf
                            <input type="hidden" name="id" value="{{$a->id}}">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Delete Data</h5>
                            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                          </div>
                          <div class="modal-body">
                            <p>Apakah anda yakin ingin menghapus data ini <b>{{$a->kode_barang}} - {{$a->nama_barang}}</b>?</p>
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

            <br>
            <div class="float-right">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#updatestokgudangModal">Update Stok Gudang</a>
            </div>
            <br>
            @endif

          </div>
        </div>
      </div>


    <!-- Modal add barang masuk-->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('petugas/add_barang_masuk')}}" method="post">
        @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data Barang Masuk</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="kode_barang" class="col-sm-3 col-form-label">Barang</label>
              <div class="col-sm-9">
                <select id="kode_barang" name="kode_barang" class="form-control" required>
                  <option>-- Pilih --</option>
                  @foreach($barang as $b)
                  <option value="{{$b->kode_barang}}">{{$b->kode_barang}} - {{$b->nama_barang}}</option>
                  @endforeach
                </select>
              </div>
            </div>


            <div class="form-group mb-3 row">
              <label for="stok_masuk" class="col-sm-3 col-form-label">stok</label>
              <div class="col-sm-9">
                <input type="number" class="form-control" id="stok_masuk" name="stok_masuk" required>
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


    <!-- Modal update stok gudang-->
  <div class="modal fade" id="updatestokgudangModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="{{url('petugas/update_barang_masuk')}}" method="post">
          @csrf
          @foreach($data as $a)
          <input type="hidden" name="id[]" value="{{$a->id}}">
          <input type="hidden" name="kode_barang[]" value="{{$a->kode_barang}}">
          <input type="hidden" name="stok_masuk[]" value="{{$a->stok_masuk}}">
          @endforeach
          <input type="hidden" name="stok_data" value="{{count($data)}}">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Update Stok Barang</h5>
          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
        </div>
        <div class="modal-body">

          <div class="alert alert-warning alert-dismissible">
            <h5><i class="icon fas fa-exclamation-triangle"></i> Perhatikan baik-baik!</h5>
            Periksa kembali stok barang masuk sebelum diupdate dengan stok barang saat ini, pastikan semua data sudah bener. <br><br>
            Proses update akan secara otomatis menambahkan data stok saat ini dengan stok barang masuk, apabila sudah diupdate maka tidak dapat dirubah kembali! <br><br> Apakah anda sudah yakin data diatas sudah benar?
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancle</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
        </form>
      </div>
    </div>
  </div>
@endsection
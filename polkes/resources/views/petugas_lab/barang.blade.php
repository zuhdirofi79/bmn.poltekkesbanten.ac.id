@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="{{url('petugas/barang?jurusan='.$jurusan)}}" class="btn btn-primary btn-sm">Peralatan Laboratorium</a>&nbsp;
              <a href="{{url('petugas/barangmedis?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Peralatan Medis</a>&nbsp;
              <a href="{{url('petugas/barangbhp?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Bahan BHP</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">PERALATAN LABORATORIUM - JURUSAN {{strtoupper($jurusan)}}</h5><hr>

            <p style="text-align:right">
              <a  href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModal"><span><i class="ti ti-plus"></i></span> Data Barang</a>
              <a  href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleStok"><span><i class="ti ti-plus"></i></span> Stok Barang</a>
            </p>

            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama Alat</th>
                        <th>Tgl Masuk</th>
                        <th>Merk</th>
                        <th>Type</th>
                        <th>No Seri</th>
                        <th>Kode Barang</th>
                        <th>Ruang</th>
                        <th>Stok</th>
                        <th>Kondisi</th>
                        <th width="58">Edit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($barang as $a)
                    <tr>
                        <td>{{$loop->iteration}}</td>
                        <td>{{$a->nama}}</td>
                        <td>{{$a->tgl_masuk}}</td>
                        <td>{{$a->merk}}</td>
                        <td>{{$a->type}}</td>
                        <td>{{$a->no_seri}}</td>
                        <td>{{$a->kode_barang}}</td>
                        <td>{{$a->ruang}}</td>
                        <td>{{$a->stok}}</td>
                        <td>{{$a->kondisi}}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#ModalEdit{{$a->id}}"><span><i class="ti ti-edit fs-4"></i></span></button>
                            <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#ModalDelete{{$a->id}}"><span><i class="ti ti-trash fs-4"></i></span></button>
                        </td>
                    </tr>

                    <!-- Modal edit barang-->
                    <div class="modal fade" id="ModalEdit{{$a->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                        <form action="{{url('petugas/edit_barang')}}" method="post">
                        @csrf
                        <input type="hidden" name="jurusan" value="{{$jurusan}}">
                        <input type="hidden" name="id" value="{{$a->id}}">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Edit Data Barang</h5>
                            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                          </div>
                          <div class="modal-body">

                            <div class="form-group mb-3 row">
                              <label for="nama_barang" class="col-sm-3 col-form-label">Nama Alat</label>
                              <div class="col-sm-9">
                                <input type="text" class="form-control" id="nama_barang" name="nama_barang" value="{{$a->nama}}" required>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="tgl_masuk" class="col-sm-3 col-form-label">Tahun Masuk</label>
                              <div class="col-sm-9">
                                <input type="date" class="form-control" id="tgl_masuk" name="tgl_masuk" value="{{$a->tgl_masuk}}" required>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="merk" class="col-sm-3 col-form-label">Merk</label>
                              <div class="col-sm-9">
                                <input type="text" class="form-control" id="merk" name="merk" value="{{$a->merk}}" required>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="type" class="col-sm-3 col-form-label">Type</label>
                              <div class="col-sm-9">
                                <input type="text" class="form-control" id="type" name="type" value="{{$a->type}}" required>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="no_seri" class="col-sm-3 col-form-label">No Seri</label>
                              <div class="col-sm-9">
                                <input type="text" class="form-control" id="no_seri" name="no_seri" value="{{$a->no_seri}}" required>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="kode_barang" class="col-sm-3 col-form-label">Kode Barang</label>
                              <div class="col-sm-9">
                                <input type="text" class="form-control" id="kode_barang" name="kode_barang" value="{{$a->kode_barang}}" required>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="ruang" class="col-sm-3 col-form-label">Ruang</label>
                              <div class="col-sm-9">
                                <select id="ruang" name="ruang" class="form-control" required>
                                  @foreach($ruangan as $b)
                                  @if($a->ruang==$b->nama_ruang)
                                    <option value="{{$b->nama_ruang}}" selected>{{$b->kampus}} - {{$b->jurusan}}, {{$b->nama_ruang}}</option>
                                  @else
                                  <option value="{{$b->nama_ruang}}">{{$b->kampus}} - {{$b->jurusan}}, {{$b->nama_ruang}}</option>
                                  @endif
                                  @endforeach
                                </select>
                              </div>
                            </div>
                            
                            <div class="form-group mb-3 row">
                              <label for="kondisi" class="col-sm-3 col-form-label">Kondisi</label>
                              <div class="col-sm-9">
                                <select id="kondisi" name="kondisi" class="form-control" required>
                                  <option value="{{$a->kondisi}}" selected>{{$a->kondisi}}</option>
                                  <option value="Baik">Baik</option>
                                  <option value="Rusak">Rusak</option>
                                  <option value="Pecah">Pecah</option>
                                  <option value="Perbaikan">Perbaikan</option>
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
                        <input type="hidden" name="jurusan" value="{{$jurusan}}">
                        <input type="hidden" name="id" value="{{$a->id}}">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Hapus data barang</h5>
                            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                          </div>
                          <div class="modal-body">

                            <div class="form-group">
                              <label for="keterangan">Kenapa anda ingin menghapus data ini?</label>
                              <textarea class="form-control" id="keterangan" name="delete" required></textarea>
                            </div>

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
    <div class="modal fade" id="exampleModal" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('petugas/add_barang')}}" method="post">
        @csrf
          <input type="hidden" name="jurusan" value="{{$jurusan}}">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data Barang</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="nama_barang" class="col-sm-3 col-form-label">Nama Alat</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
              </div>
            </div>
            
            <div class="form-group mb-3 row">
              <label for="tgl_masuk" class="col-sm-3 col-form-label">Tahun Masuk</label>
              <div class="col-sm-9">
                <input type="date" class="form-control" id="tgl_masuk" name="tgl_masuk" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="merk" class="col-sm-3 col-form-label">Merk</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="merk" name="merk"required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="type" class="col-sm-3 col-form-label">Type</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="type" name="type" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="no_seri" class="col-sm-3 col-form-label">No Seri</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="no_seri" name="no_seri" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="kode_barang" class="col-sm-3 col-form-label">Kode Barang</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="kode_barang" name="kode_barang" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="ruang" class="col-sm-3 col-form-label">Ruang</label>
              <div class="col-sm-9">
                <select id="ruang" name="ruang" class="form-control" required>
                    <option>--Pilih--</option>
                  @foreach($ruangan as $b)
                    <option value="{{$b->nama_ruang}}">{{$b->kampus}} - {{$b->jurusan}}, {{$b->nama_ruang}}</option>
                  @endforeach
                </select>
              </div>
            </div>
            
            <div class="form-group mb-3 row">
              <label for="kondisi" class="col-sm-3 col-form-label">Kondisi</label>
              <div class="col-sm-9">
                <select id="kondisi" name="kondisi" class="form-control" required>
                  <option value="Baik">Baik</option>
                  <option value="Rusak">Rusak</option>
                  <option value="Pecah">Pecah</option>
                  <option value="Perbaikan">Perbaikan</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="stok" class="col-sm-3 col-form-label">Stok Awal</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="stok" name="stok" required>
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

    <!-- Modal stok barang-->
    <div class="modal fade" id="exampleStok" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('petugas/penambahan_stok_barang')}}" method="post">
        @csrf
          <input type="hidden" name="jurusan" value="{{$jurusan}}">
          <input type="hidden" name="jenis_barang" value="Peralatan Laboratorium">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Stok Barang</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="barang_id" class="col-sm-3 col-form-label">Nama Barang</label>
              <div class="col-sm-9">
                <select id="barang_id" name="barang_id" class="form-control" required>
                    <option>--Pilih--</option>
                  @foreach($barang as $a)
                    <option value="{{$a->id}}">{{$a->kode_barang}} - {{$a->nama}}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="stok" class="col-sm-3 col-form-label">Stok Penambahan</label>
              <div class="col-sm-9">
                <input type="number" class="form-control" id="stok" name="stok" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="keterangan" class="col-sm-3 col-form-label">Keterangan</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="keterangan" name="keterangan" value="Penambahan Stok Barang">
                <small>Opsional.</small>
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
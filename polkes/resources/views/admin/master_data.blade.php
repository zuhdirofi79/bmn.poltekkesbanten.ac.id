@extends('layouts.thema')

@section('content')

  <div class="col-lg-12">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModalRuangan">Tambah Ruangan</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">Table Ruangan Laboratorium</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Jurusan</th>
                        <th>Kampus</th>
                        <th>Nama Ruang Lab</th>
                        <th width="56">Edit</th>
                    </tr> 
                </thead>
                <tbody>
                  @foreach($ruangan as $b)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$b->jurusan}}</td>
                    <td>{{$b->kampus}}</td>
                    <td>{{$b->nama_ruang}}</td>
                    <td>
                      <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#editRuangan{{$b->id}}"><span><i class="ti ti-edit fs-4"></i></span></button>
                      <a href="{{url('admin/delete_ruangan/'.Crypt::encrypt($b->id))}}" class="btn btn-outline-danger btn-sm" 
                        onclick="return confirm('Apakah anda yakin ingin menghapus RuanganLab {{$b->nama_ruang}} pada jurusan {{$b->jurusan}}?');">
                        <span><i class="ti ti-trash fs-4"></i></span>
                      </a>
                    </td>
                  </tr>

                  <!-- Modal edit ruangan-->
                  <div class="modal fade" id="editRuangan{{$b->id}}" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                      <form action="{{url('admin/edit_ruangan')}}" method="post">
                      @csrf
                      <input type="hidden" name="id" value="{{$b->id}}">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Edit Data Ruangan</h5>
                          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                        </div>
                        <div class="modal-body">

                          <div class="form-group mb-3 row">
                            <label for="jurusan" class="col-sm-3 col-form-label">Jurusan</label>
                            <div class="col-sm-9">
                              <select id="jurusan" name="jurusan" class="form-control" required>
                                  <option value="Keperawatan" {{($b->jurusan=='Keperawatan')?'selected':''}}>Keperawatan</option>
                                  <option value="Kebidanan" {{($b->jurusan=='Kebidanan')?'selected':''}}>Kebidanan</option>
                                  <option value="TLM" {{($b->jurusan=='TLM')?'selected':''}}>TLM</option>
                              </select>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <label for="kampus" class="col-sm-3 col-form-label">Kampus</label>
                            <div class="col-sm-9">
                              <select id="kampus" name="kampus" class="form-control" required>
                                  <option value="Tangerang" {{($b->kampus=='Tangerang')?'selected':''}}>Tangerang</option>
                                  <option value="Serang" {{($b->kampus=='Serang')?'selected':''}}>Serang</option>
                                  <option value="Rangkasbitung" {{($b->kampus=='Rangkasbitung')?'selected':''}}>Rangkasbitung</option>
                              </select>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <label for="nama_ruang" class="col-sm-3 col-form-label">Nama Ruangan</label>
                            <div class="col-sm-9">
                              <input type="text" class="form-control" id="nama_ruang" name="nama_ruang" value="{{$b->nama_ruang}}" required>
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
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModalKelas">Tambah Kelas</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">Table Matakuliah</h5>
            <table id="myTable2" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Jurusan</th>
                        <th>Matakuliah</th>
                        <th width="56">Edit</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($kelas as $b)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$b->jurusan}}</td>
                    <td>{{$b->matkul}}</td>
                    <td>
                      <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#editKelas{{$b->id}}"><span><i class="ti ti-edit fs-4"></i></span></button>
                      <a href="{{url('admin/delete_kelas/'.Crypt::encrypt($b->id))}}" class="btn btn-outline-danger btn-sm" 
                        onclick="return confirm('Apakah anda yakin ingin menghapus matkul {{$b->matkul}} pada jurusan {{$b->jurusan}}?');">
                        <span><i class="ti ti-trash fs-4"></i></span>
                      </a>
                    </td>
                  </tr>

                  <!-- Modal edit kelas-->
                  <div class="modal fade" id="editKelas{{$b->id}}" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                      <form action="{{url('admin/edit_kelas')}}" method="post">
                      @csrf
                      <input type="hidden" name="id" value="{{$b->id}}">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Edit Data Kelas</h5>
                          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                        </div>
                        <div class="modal-body">

                          <div class="form-group mb-3 row">
                            <label for="jurusan" class="col-sm-3 col-form-label">Jurusan</label>
                            <div class="col-sm-9">
                              <select id="jurusan" name="jurusan" class="form-control" required>
                                  <option value="Keperawatan" {{($b->jurusan=='Keperawatan')?'selected':''}}>Keperawatan</option>
                                  <option value="Kebidanan" {{($b->jurusan=='Kebidanan')?'selected':''}}>Kebidanan</option>
                                  <option value="TLM" {{($b->jurusan=='TLM')?'selected':''}}>TLM</option>
                              </select>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <label for="matkul" class="col-sm-3 col-form-label">Matakuliah</label>
                            <div class="col-sm-9">
                              <input type="text" class="form-control" id="matkul" name="matkul" value="{{$b->matkul}}" required>
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

                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModalTingkat">Tambah Kelas</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">Table Tingkat</h5>
            <table id="myTable3" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tingkat</th>
                        <th width="56">Edit</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($tingkat as $b)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$b->tingkat}}</td>
                    <td>
                      <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#editTingkat{{$b->id}}"><span><i class="ti ti-edit fs-4"></i></span></button>
                      <a href="{{url('admin/delete_tingkat/'.Crypt::encrypt($b->id))}}" class="btn btn-outline-danger btn-sm" 
                        onclick="return confirm('Apakah anda yakin ingin menghapus Tingkat {{$b->tingkat}}?');">
                        <span><i class="ti ti-trash fs-4"></i></span>
                      </a>
                     </td>
                  </tr>

                  <!-- Modal edit tingkat-->
                  <div class="modal fade" id="editTingkat{{$b->id}}" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                      <form action="{{url('admin/edit_tingkat')}}" method="post">
                      @csrf
                      <input type="hidden" name="id" value="{{$b->id}}">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Edit Data Tingkat</h5>
                          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                        </div>
                        <div class="modal-body">

                          <div class="form-group mb-3 row">
                            <label for="tingkat" class="col-sm-3 col-form-label">Tingkat</label>
                            <div class="col-sm-9">
                              <input type="text" class="form-control" id="tingkat" name="tingkat" value="{{$b->tingkat}}" required>
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

                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModalSemester">Tambah Semester</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">Table Semester</h5>
            <table id="myTable4" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Semester</th>
                        <th>Tahun Ajar</th>
                        <th>Status</th>
                        <th width="56">Edit</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($semester as $b)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$b->semester}}</td>
                    <td>{{$b->ta}}</td>
                    <td>{{$b->status}}</td>
                    <td>
                      <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#editSemester{{$b->id}}"><span><i class="ti ti-edit fs-4"></i></span></button>
                        <!-- <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#ModalDeleteBahan{{$b->id}}"><span><i class="ti ti-trash fs-4"></i></span></button> -->
                    </td>
                  </tr>

                  <!-- Modal add semester-->
                  <div class="modal fade" id="editSemester{{$b->id}}" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                      <form action="{{url('admin/edit_semester')}}" method="post">
                      @csrf
                      <input type="hidden" name="id" value="{{$b->id}}">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Edit Data Semester</h5>
                          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                        </div>
                        <div class="modal-body">


                          <div class="form-group mb-3 row">
                            <label for="semester" class="col-sm-3 col-form-label">Semester</label>
                            <div class="col-sm-9">
                              <select id="semester" name="semester" class="form-control" required>
                                  <option value="Ganjil" {{($b->semester=='Ganjil')?'selected':''}}>Ganjil</option>
                                  <option value="Genap" {{($b->semester=='Genap')?'selected':''}}>Genap</option>
                              </select>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <label for="ta" class="col-sm-3 col-form-label">Tahun Ajar</label>
                            <div class="col-sm-9">
                              <input type="text" class="form-control" id="ta" name="ta" value="{{$b->ta}}" required>
                            </div>
                          </div>

                          <div class="form-group mb-3 row">
                            <label for="status" class="col-sm-3 col-form-label">Status</label>
                            <div class="col-sm-9">
                              <select id="status" name="status" class="form-control" required>
                                  <option value="aktif" {{($b->status=='aktif')?'selected':''}}>aktif</option>
                                  <option value="tidak aktif" {{($b->status=='tidak aktif')?'selected':''}}>tidak aktif</option>
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
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>


    <!-- Modal add ruangan-->
    <div class="modal fade" id="exampleModalRuangan" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('admin/add_ruangan')}}" method="post">
        @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data Ruangan</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="jurusan" class="col-sm-3 col-form-label">Jurusan</label>
              <div class="col-sm-9">
                <select id="jurusan" name="jurusan" class="form-control" required>
                    <option value="">--Pilih--</option>
                    <option value="Keperawatan">Keperawatan</option>
                    <option value="Kebidanan">Kebidanan</option>
                    <option value="TLM">TLM</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="kampus" class="col-sm-3 col-form-label">Kampus</label>
              <div class="col-sm-9">
                <select id="kampus" name="kampus" class="form-control" required>
                    <option value="">--Pilih--</option>
                    <option value="Tangerang">Tangerang</option>
                    <option value="Serang">Serang</option>
                    <option value="Rangkasbitung">Rangkasbitung</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="nama_ruang" class="col-sm-3 col-form-label">Nama Ruangan</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="nama_ruang" name="nama_ruang" required>
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

    <!-- Modal add kelas-->
    <div class="modal fade" id="exampleModalKelas" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('admin/add_kelas')}}" method="post">
        @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data Kelas</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="jurusan" class="col-sm-3 col-form-label">Jurusan</label>
              <div class="col-sm-9">
                <select id="jurusan" name="jurusan" class="form-control" required>
                    <option value="">--Pilih--</option>
                    <option value="Keperawatan">Keperawatan</option>
                    <option value="Kebidanan">Kebidanan</option>
                    <option value="TLM">TLM</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="matkul" class="col-sm-3 col-form-label">Matakuliah</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="matkul" name="matkul" required>
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

    <!-- Modal add tingkat-->
    <div class="modal fade" id="exampleModalTingkat" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('admin/add_tingkat')}}" method="post">
        @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data Tingkat</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="tingkat" class="col-sm-3 col-form-label">Tingkat</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="tingkat" name="tingkat" required>
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

    <!-- Modal add semester-->
    <div class="modal fade" id="exampleModalSemester" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('admin/add_semester')}}" method="post">
        @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data Semester</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">


            <div class="form-group mb-3 row">
              <label for="semester" class="col-sm-3 col-form-label">Semester</label>
              <div class="col-sm-9">
                <select id="semester" name="semester" class="form-control" required>
                    <option value="">--Pilih--</option>
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="ta" class="col-sm-3 col-form-label">Tahun Ajar</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="ta" name="ta" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="status" class="col-sm-3 col-form-label">Status</label>
              <div class="col-sm-9">
                <select id="status" name="status" class="form-control" required>
                    <option value="aktif">aktif</option>
                    <option value="tidak aktif">tidak aktif</option>
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
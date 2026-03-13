@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="{{url('petugas/barang?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Aset Laboratorium</a>&nbsp;
              <a href="{{url('petugas/barangmedis?jurusan='.$jurusan)}}" class="btn btn-primary btn-sm">Alat Medis</a>&nbsp;
              <a href="{{url('petugas/barangbhp?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Bahan Medis</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">ALAT MEDIS - JURUSAN {{strtoupper($jurusan)}}</h5><hr>

            <p style="text-align:right">
              <a  href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#ModalAddAlat"><span><i class="ti ti-plus"></i></span> Data Alat</a>
              <a  href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleStok"><span><i class="ti ti-plus"></i></span> Stok Barang</a>
            </p>

            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Ruangan</th>
                        <th>Lokasi</th>
                        <th>Stok</th>
                        <th>Satuan</th>
                        <th>Kondisi</th>
                        <th width="70">Edit</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($bhp_alat as $a)

                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{ optional($a->ruangan)->nama_ruang ?? '-' }}</td>
                    <td>{{ optional(optional($a->ruangan)->location)->name ?? '-' }}</td>
                    <td>{{$a->stok}}</td>
                    <td>{{$a->satuan}}</td>
                    <td>{{$a->kondisi}}</td>
                    <td>
                      <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#ModalEditalat{{$a->id}}"><span><i class="ti ti-edit fs-4"></i></span></button>
                        <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#ModalDeleteAlat{{$a->id}}"><span><i class="ti ti-trash fs-4"></i></span></button>
                    </td>
                  </tr>

                  <!-- Modal edit bhp alat-->
                  <div class="modal fade" id="ModalEditalat{{$a->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                      <form action="{{url('petugas/edit_bhp_alat')}}" method="post">
                      @csrf
                      <input type="hidden" name="jurusan" value="{{$jurusan}}">
                      <input type="hidden" name="id" value="{{$a->id}}">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Edit Data Alat</h5>
                          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                        </div>
                        <div class="modal-body">

                          <div class="form-group mb-3 row">
                            <label for="nama" class="col-sm-3 col-form-label">Nama</label>
                            <div class="col-sm-9">
                              <input type="text" class="form-control" id="nama" name="nama" value="{{$a->nama}}" required>
                            </div>
                          </div>
                          
                          <div class="form-group mb-3 row">
                              <label for="satuan" class="col-sm-3 col-form-label">Satuan</label>
                              <div class="col-sm-9">
                                <select id="satuan" name="satuan" class="form-control" required>
                                    <option value="gram" {{($a->satuan=='gram')?'selected':''}}>gram</option>
                                    <option value="mg" {{($a->satuan=='mg')?'selected':''}}>mg</option>
                                    <option value="liter" {{($a->satuan=='liter')?'selected':''}}>liter</option>
                                    <option value="ml" {{($a->satuan=='ml')?'selected':''}}>ml</option>
                                    <option value="box" {{($a->satuan=='box')?'selected':''}}>box</option>
                                    <option value="pack" {{($a->satuan=='pack')?'selected':''}}>pack</option>
                                    <option value="botol" {{($a->satuan=='botol')?'selected':''}}>botol</option>
                                    <option value="pcs" {{($a->satuan=='pcs')?'selected':''}}>pcs</option>
                                    <option value="unit" {{($a->satuan=='unit')?'selected':''}}>unit</option>
                                </select>
                              </div>
                          </div>
                          
                          <div class="form-group mb-3 row">
                              <label for="kondisi" class="col-sm-3 col-form-label">Kondisi</label>
                              <div class="col-sm-9">
                                <select id="kondisi" name="kondisi" class="form-control" required>
                                  <option value="Baik" {{($a->kondisi=='Baik')?'selected':''}}>Baik</option>
                                  <option value="Rusak" {{($a->kondisi=='Rusak')?'selected':''}}>Rusak</option>
                                  <option value="Pecah" {{($a->kondisi=='Pecah')?'selected':''}}>Pecah</option>
                                  <option value="Perbaikan" {{($a->kondisi=='Perbaikan')?'selected':''}}>Perbaikan</option>
                                </select>
                              </div>
                            </div>

                          <div class="form-group mb-3 row">
                            <label for="ruangan_id" class="col-sm-3 col-form-label">Ruangan</label>
                            <div class="col-sm-9">
                              <select name="ruangan_id" class="form-control">
                                <option value="">-- Pilih Ruangan --</option>
                                @foreach($ruangan as $r)
                                <option value="{{$r->id}}" {{ $a->ruangan_id == $r->id ? 'selected' : '' }}>
                                  {{$r->kampus}} - {{$r->nama_ruang}}
                                </option>
                                @endforeach
                              </select>
                              @if($a->ruangan_id)
                              <small class="text-muted">
                                Lokasi: {{ optional($a->ruangan->location)->name ?? '-' }}
                              </small>
                              @endif
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

                  <!-- Modal delete bhp alat-->
                  <div class="modal fade" id="ModalDeleteAlat{{$a->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                      <form action="{{url('petugas/delete_bhp_alat')}}" method="post">
                      @csrf
                      <input type="hidden" name="jurusan" value="{{$jurusan}}">
                      <input type="hidden" name="id" value="{{$a->id}}">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Hapus data BHP Alat</h5>
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



    <!-- Modal add bhp alat-->
    <div class="modal fade" id="ModalAddAlat" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('petugas/add_bhp_alat')}}" method="post">
        @csrf
          <input type="hidden" name="jurusan" value="{{$jurusan}}">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data BHP Alat</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="nama" class="col-sm-3 col-form-label">Nama Alat</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="nama" name="nama" required>
              </div>
            </div>
            
            <div class="form-group mb-3 row">
              <label for="stok" class="col-sm-3 col-form-label">Stok Awal</label>
              <div class="col-sm-9">
                <input type="number" class="form-control" id="stok" name="stok" required>
              </div>
            </div>
            
            <div class="form-group mb-3 row">
              <label for="satuan" class="col-sm-3 col-form-label">Satuan</label>
              <div class="col-sm-9">
                <select id="satuan" name="satuan" class="form-control" required>
                    <option value="">--Pilih--</option>
                    <option value="gram">gram</option>
                    <option value="mg">mg</option>
                    <option value="liter">liter</option>
                    <option value="ml">ml</option>
                    <option value="box">box</option>
                    <option value="pack">pack</option>
                    <option value="botol">botol</option>
                    <option value="pcs">pcs</option>
                    <option value="unit">unit</option>
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
              <label for="ruangan_id" class="col-sm-3 col-form-label">Ruangan</label>
              <div class="col-sm-9">
                <select name="ruangan_id" class="form-control">
                  <option value="">-- Pilih Ruangan --</option>
                  @foreach($ruangan as $r)
                  <option value="{{$r->id}}">{{$r->kampus}} - {{$r->nama_ruang}}</option>
                  @endforeach
                </select>
                <small class="text-muted">Lokasi mengikuti ruangan yang dipilih.</small>
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
          <input type="hidden" name="jenis_barang" value="Peralatan Medis">
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
                  @foreach($bhp_alat as $a)
                    <option value="{{$a->id}}">{{$a->nama}}</option>
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
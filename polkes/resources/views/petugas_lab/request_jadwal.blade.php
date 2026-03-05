@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">JADWAL PRAKTEK LAB JURUSAN {{strtoupper($jurusan)}} SEMESTER {{strtoupper($semester->semester)}} {{$semester->ta}}</h5>

            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Matakuliah</th>
                        <th>Tingkat</th>
                        <th>Ruangan Lab</th>
                        <th>Hari</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th width="75">Detail</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->kelas}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->ruang}}</td>
                    <td>{{$a->hari}}</td>
                    <td>{{substr($a->waktu_mulai,0,5)}}</td>
                    <td>{{substr($a->waktu_selesai,0,5)}}</td>
                    <td><a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#ModalEdit{{$a->id}}">Detail</a>
                    </td>
                  </tr>

                  <!-- Modal edit barang-->
                    <div class="modal fade" id="ModalEdit{{$a->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                        <form action="{{url('petugas/submit_request_jadwal')}}" method="post">
                        @csrf
                        <input type="hidden" name="jurusan" value="{{$jurusan}}">
                        <input type="hidden" name="id" value="{{$a->id}}">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Konfirmasi Pengajuan Pinjaman Lab</h5>
                            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                          </div>
                          <div class="modal-body">
                              
                            <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Tgl</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                <p>{{$a->tgl}}</p>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Penanggung Jawab/Dosen</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                <p>{{$a->penanggung_jawab}}</p>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Matakuliah</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                <p>{{$a->kelas}}</p>
                              </div>
                            </div>
                            
                            <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Tingkat</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                <p>{{$a->tingkat}}</p>
                              </div>
                            </div>

                           <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Ruang</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                <p>{{$a->ruang}}</p>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Hari</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                <p>{{$a->hari}}</p>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Waktu Mulai</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                <p>{{substr($a->waktu_mulai,0,5)}}</p>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Waktu Selesai</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                <p>{{substr($a->waktu_selesai,0,5)}}</p>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <div class="col-sm-5">
                                <p>Tujuan</p>
                              </div>
                              <div class="col-sm-1">
                                <p>:</p>
                              </div>
                              <div class="col-sm-6">
                                {{$a->tujuan}}
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="status" class="col-sm-3 col-form-label">Status</label>
                              <div class="col-sm-9">
                                <select id="status" name="status" class="form-control" required>
                                    <option value="Menunggu Konfirmasi">Menunggu Konfirmasi</option>
                                    <option value="Diterima">Diterima</option>
                                    <option value="Ditolak">Ditolak</option>
                                </select>
                              </div>
                            </div>

                            <div class="form-group mb-3 row">
                              <label for="keterangan" class="col-sm-3 col-form-label">Keterangan</label>
                              <div class="col-sm-9">
                                <input type="text" class="form-control" id="keterangan" name="keterangan">
                                <small class="form-text text-muted">opsional.</small>
                              </div>
                            </div>

                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Submit</button>
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


@endsection
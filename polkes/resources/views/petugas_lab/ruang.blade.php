@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a  href="{{url('petugas/tambah_jadwal?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm"><span><i class="ti ti-plus"></i></span> Data Jadwal Praktek</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">JADWAL PRAKTEK LAB JURUSAN {{strtoupper($jurusan)}} SEMESTER {{strtoupper($semester->semester)}} {{$semester->ta}}</h5>

            <table id="exampleButton" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Tanggal</th>
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
                    <td>{{$a->tgl}}</td>
                    <td>{{$a->kelas}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->ruang}}</td>
                    <td>{{$a->hari}}</td>
                    <td>{{substr($a->waktu_mulai,0,5)}}</td>
                    <td>{{substr($a->waktu_selesai,0,5)}}</td>
                    <td><a href="{{url('petugas/detail_jadwal/'.$a->id)}}" class="btn btn-outline-primary btn-sm"><span><i class="ti ti-edit fs-4"></i></span></a>
                    <button class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#ModalDelete{{$a->id}}"><span><i class="ti ti-trash fs-4"></i></span></button></td>
                  </tr>

                  <!-- Modal edit barang-->
                    <div class="modal fade" id="ModalDelete{{$a->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                        <form action="{{url('petugas/delete_jadwal')}}" method="post">
                        @csrf
                        <input type="hidden" name="id" value="{{$a->id}}">
                          <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Hapus data Jadwal Praktukim</h5>
                            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                          </div>
                          <div class="modal-body">

                            <p>Apakah Anda yakin ingin menghapus jadwal ini?</p>

                            <!-- <div class="form-group">
                              <label for="keterangan">Apakah Anda yakin ingin menghapus jadwal ini?</label>
                              <textarea class="form-control" id="keterangan" name="delete" required></textarea>
                            </div> -->

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


@endsection
@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModal">Input Form Kunjungan Lab</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">History Kunjungan Lab</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="30">No</th>
                        <th>Tgl Kunjugan</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th>Ruang Lab</th>
                        <th>Kelas</th>
                        <th>Tujuan</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->tgl_kunjungan}}</td>
                    <td>{{substr($a->waktu_mulai,0,5)}}</td>
                    <td>{{substr($a->waktu_selesai,0,5)}}</td>
                    <td>{{$a->ruang_lab}}</td>
                    <td>{{$a->kelas}}</td>
                    <td>{{$a->tujuan}}</td>
                    <td>{{$a->keterangan}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>

    <?php $date = date('Y-m-d'); ?>
    <!-- Modal add barang-->
    <div class="modal fade" id="exampleModal" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('user/add_kunjungan')}}" method="post">
        @csrf
        <input type="hidden" name="jurusan" value="{{$jurusan}}">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Formulir Kunjugan Lab</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="tgl_kunjungan" class="col-sm-3 col-form-label">Tgl Berkunjung</label>
              <div class="col-sm-9">
                <input type="date" class="form-control" id="tgl_kunjungan" name="tgl_kunjungan" value="{{$date}}" required>
              </div>
            </div>
            
            @if($jurusan=='Keperawatan')
            <div class="form-group mb-3 row">
              <label for="prodi" class="col-sm-3 col-form-label">Prodi</label>
              <div class="col-sm-9">
                <select id="prodi" name="prodi" class="form-control" required>
                    <option value="">--Pilih--</option>
                    <option value="D3 Keperawatan">D3 Keperawatan</option>
                    <option value="STR Keperawatan">STR Keperawatan</option>
                    <option value="Profesi Ners">Profesi Ners</option>
                </select>
              </div>
            </div>
            @endif

            <div class="form-group mb-3 row">
              <label for="waktu_mulai" class="col-sm-3 col-form-label">Waktu Mulai</label>
              <div class="col-sm-9">
                <input type="time" class="form-control" id="waktu_mulai" name="waktu_mulai" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="waktu_selesai" class="col-sm-3 col-form-label">Waktu Selesai</label>
              <div class="col-sm-9">
                <input type="time" class="form-control" id="waktu_selesai" name="waktu_selesai" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="ruang" class="col-sm-3 col-form-label">Ruang</label>
              <div class="col-sm-9">
                <select id="ruang" name="ruang" class="form-control" required>
                    <option value="">--Pilih--</option>
                  @foreach($ruangan as $b)
                    <option value="{{$b->nama_ruang}}">{{$b->kampus}} - {{$b->jurusan}}, {{$b->nama_ruang}}</option>
                  @endforeach
                </select>
              </div>
            </div>
            
            <div class="form-group mb-3 row">
              <label for="kelas" class="col-sm-3 col-form-label">Kelas</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="kelas" name="kelas" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="tujuan" class="col-sm-3 col-form-label">Tujuan Berkunjung</label>
              <div class="col-sm-9">
                <select id="tujuan" name="tujuan" class="form-control" required>
                    <option value="">--Pilih--</option>
                    @foreach($tujuan as $f)
                        <option value="{{$f->tujuan}}">{{$f->tujuan}}</option>
                    @endforeach
                </select>
              </div>
            </div>
            
            <div class="form-group mb-3 row">
              <label for="keterangan" class="col-sm-3 col-form-label">Keterangan / saran</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="keterangan" name="keterangan">
                <small>Opsional</small>
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
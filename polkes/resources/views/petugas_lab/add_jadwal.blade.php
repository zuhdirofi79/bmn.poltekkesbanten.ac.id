@extends('layouts.thema_select2')

@section('content')
      <div class="container-fluid col-lg-9">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">TAMBAH JADWAL PRAKTEK LAB JURUSAN {{strtoupper($jurusan)}}</h5>

            <form action="{{url('petugas/add_jadwal')}}" method="post">
              @csrf
                <input type="hidden" name="jurusan" value="{{$jurusan}}">
                
                <div class="form-group mb-3 row">
                    <label for="tgl" class="col-sm-3 col-form-label">Tanggal</label>
                    <div class="col-sm-9">
                      <input type="date" class="form-control" id="tgl" name="tgl" required>
                    </div>
                  </div>

                  <div class="form-group mb-3 row">
                    <label for="penanggung_jawab" class="col-sm-3 col-form-label">Penanggung Jawab/Dosen</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="penanggung_jawab" name="penanggung_jawab" required>
                    </div>
                  </div>

                  <div class="form-group mb-3 row">
                    <label for="kelas" class="col-sm-3 col-form-label">Matakuliah</label>
                    <div class="col-sm-9">
                      <select id="kelas" name="kelas" class="form-control" required>
                        <option value=""></option>
                        @foreach($kelas as $e)
                          <option value="{{$e->matkul}}">{{$e->matkul}}</option>
                        @endforeach
                          <option value="Other">Other</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-group mb-3 row" id="otherKelas" style="display:none;">
                    <label for="kelas_other" class="col-sm-3 col-form-label"></label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="kelas_other" name="kelas_other">
                    </div>
                  </div>
                  
                  <div class="form-group mb-3 row">
                    <label for="tingkat" class="col-sm-3 col-form-label">Tingkat</label>
                    <div class="col-sm-9">
                      <select id="tingkat" name="tingkat" class="form-control" required>
                        <option value=""></option>
                        @foreach($tingkat as $d)
                          <option value="{{$d->tingkat}}">{{$d->tingkat}}</option>
                        @endforeach
                        <option value="Other">Other</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-group mb-3 row" id="otherTingkat" style="display:none;">
                    <label for="tingkat_other" class="col-sm-3 col-form-label"></label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="tingkat_other" name="tingkat_other">
                    </div>
                  </div>

                 <div class="form-group mb-3 row">
                    <label for="ruang" class="col-sm-3 col-form-label">Ruang</label>
                    <div class="col-sm-9">
                      <select id="ruang" name="ruang" class="form-control" required>
                        <option value=""></option>
                        @foreach($ruangan as $b)
                          <option value="{{$b->nama_ruang}}">{{$b->kampus}} - {{$b->nama_ruang}}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>

                  <div class="form-group mb-3 row">
                    <label for="hari" class="col-sm-3 col-form-label">Hari</label>
                    <div class="col-sm-9">
                      <select id="hari" name="hari" class="form-control" required>
                        <option value=""></option>
                        @foreach($hari as $c)
                          <option value="{{$c->hari}}">{{$c->hari}}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>

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
                    <label for="tujuan" class="col-sm-3 col-form-label">Tujuan</label>
                    <div class="col-sm-9">
                      <select id="tujuan" name="tujuan" class="form-control" required>
                          <option>--Pilih--</option>
                          @foreach($tujuan as $f)
                          <option value="{{$f->tujuan}}">{{$f->tujuan}}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>

                <div class="modal-footer">
                  <button type="submit" class="btn btn-primary">Tambah</button>
                </div>
              </form>

          </div>
        </div>
      </div>


  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script>
      $(document).ready(function () {
          // Ketika nilai dalam select berubah
          $("#kelas").change(function () {
              // Jika nilai yang dipilih adalah "other"
              if ($(this).val() == "Other") {
                  // Tampilkan form input tambahan
                  $("#otherKelas").show();
              } else {
                  // Sembunyikan form input tambahan
                  $("#otherKelas").hide();
              }
          });
      });

      $(document).ready(function () {
          // Ketika nilai dalam select berubah
          $("#tingkat").change(function () {
              // Jika nilai yang dipilih adalah "other"
              if ($(this).val() == "Other") {
                  // Tampilkan form input tambahan
                  $("#otherTingkat").show();
              } else {
                  // Sembunyikan form input tambahan
                  $("#otherTingkat").hide();
              }
          });
      });
  </script>

@endsection
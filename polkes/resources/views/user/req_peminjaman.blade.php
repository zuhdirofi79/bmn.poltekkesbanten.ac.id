@extends('layouts.thema')

@section('content')
      <div class="container-fluid col-lg-9">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Peminjaman Peralatan {{$ruangan}}</h5>

            @if(session('error'))
              <div class="alert alert-danger">
                {{ session('error') }}
              </div>
            @endif

            <form action="{{url('user/add_peminjaman')}}" method="post" enctype="multipart/form-data">
              @csrf
                <input type="hidden" name="jurusan" value="{{$jurusan}}">

                  <div class="form-group mb-3 row">
                    <label for="name" class="col-sm-3 col-form-label">Nama</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="name" name="name" value="{{auth()->user()->name}}" readonly>
                    </div>
                  </div>

                  <div class="form-group mb-3 row">
                    <label for="jenis" class="col-sm-3 col-form-label">Jenis Alat</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="jenis" name="jenis" value="{{$jenis}}" readonly>
                    </div>
                  </div>

                  <div class="form-group mb-3 row">
                    <label for="ruangan" class="col-sm-3 col-form-label">Ruang Lab</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" id="ruangan" name="ruangan" value="{{$ruangan}}" readonly>
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
                    <label for="prodi_id" class="col-sm-3 col-form-label">Prodi</label>
                    <div class="col-sm-9">
                      <select id="prodi_id" name="prodi_id" class="form-control" required>
                        <option value="">-- Pilih Prodi --</option>
                        @foreach($prodis as $prodi)
                          <option value="{{ $prodi->id }}">{{ $prodi->nama_prodi }}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                  
                  @if($jenis=='Bahan Medis')
                  
                  <div class="form-group mb-3 row">
                    <label for="tgl_peminjaman" class="col-sm-3 col-form-label">Tgl Pemakaian</label>
                    <div class="col-sm-9">
                      <input type="date" class="form-control" id="tgl_peminjaman" name="tgl_peminjaman" required>
                    </div>
                  </div>
                  
                  @else
                  
                  <div class="form-group mb-3 row">
                    <label for="tgl_peminjaman" class="col-sm-3 col-form-label">Tgl Peminjaman</label>
                    <div class="col-sm-9">
                      <input type="date" class="form-control" id="tgl_peminjaman" name="tgl_peminjaman" required>
                    </div>
                  </div>

                  <div class="form-group mb-3 row">
                    <label for="tgl_pengembalian" class="col-sm-3 col-form-label">Tgl Pengembalian</label>
                    <div class="col-sm-9">
                      <input type="date" class="form-control" id="tgl_pengembalian" name="tgl_pengembalian" required>
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
                    <label for="tujuan" class="col-sm-3 col-form-label">Tujuan</label>
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
                    <label class="col-sm-3 col-form-label">Alat/Bahan (boleh pilih lebih dari 1)</label>
                    <div class="col-sm-9 form-check">
                    <!--
                      @foreach($barang as $f)
                      <input type="checkbox" name="barang_id[]" value="{{$f->id}}" class="form-check-input" id="exampleCheck{{$f->id}}">
                      <label class="form-check-label" for="exampleCheck{{$f->id}}">Stok tersedia = {{$f->stok}} {{$f->satuan}}, {{$f->nama}}</label><br>
                      @endforeach
                      -->
                      <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Checklish</th>
                                <th>Nama Barang</th>
                                <th>Stok Tersedia</th>
                            </tr>
                        </thead>
                        <tbody>
                          @foreach($barang as $f) 
                          <tr>
                            <td><input type="checkbox" name="barang_id[]" value="{{$f->id}}"></td>
                            <td>{{$f->nama}}</td>
                            <td>{{$f->stok}} {{$f->satuan}}</td>
                          </tr>
                          @endforeach
                        </tbody>
                    </table>
                    
                    </div>
                  </div>

                  <div class="form-group mb-3 row">
                    <label class="col-sm-3 col-form-label">Bukti Foto</label>
                    <div class="col-sm-9">
                      <input type="file"
                        name="bukti_foto[]"
                        id="bukti_foto"
                        class="form-control"
                        multiple
                        accept="image/jpeg,image/png,image/jpg"
                        capture="environment">
                      <small class="text-muted">Maksimal 5 foto, 2MB per file. Format: JPEG, JPG, PNG.</small>
                      @error('bukti_foto') <span class="text-danger">{{ $message }}</span> @enderror
                      @error('bukti_foto.*') <span class="text-danger d-block">{{ $message }}</span> @enderror
                      <div id="preview_foto" class="row mt-2"></div>
                    </div>
                  </div>

                <div class="modal-footer">
                  <!-- <button type="submit" class="btn btn-primary">Next</button> -->
                  <a href="" class="btn btn-primary" data-toggle="modal" data-target="#ModalKonfirmasi">Next</a>
                </div>

                <!-- Modal konfirmasi-->
                  <div class="modal fade" id="ModalKonfirmasi" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title" id="exampleModalLabel">Konfirmasi Persetujuan</h5>
                          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
                        </div>
                        <div class="modal-body">
                         <p>Peringatan Peminjaman Alat Laboratorium</p>
                         <p style="text-align: justify;">Dengan ini saya {{auth()->user()->name}}, menyatakan bahwa saya telah membaca, memahami, dan menerima semua aturan dan ketentuan yang berlaku terkait dengan peminjaman alat laboratorium pada {{ENV('APP_NAME')}}. Saya bersedia mentaati setiap aturan yang telah ditetapkan dan bertanggung jawab atas alat-alat yang dipinjamkan kepada saya.</p>
                         <p style="text-align: justify;">Saya memahami bahwa setiap kerusakan atau kehilangan yang terjadi selama masa peminjaman akan menjadi tanggung jawab saya. Saya siap untuk mengganti biaya perbaikan atau mengganti alat yang hilang sesuai dengan ketentuan yang berlaku.</p>
                         <p style="text-align: justify;">Dengan ini, saya menyatakan bahwa saya bertanggung jawab sepenuhnya atas peminjaman alat laboratorium ini dan siap menerima konsekuensi yang timbul apabila melanggar aturan yang telah ditetapkan.</p>
                         <p>Pernyataan ini bersifat mengikat dan sah setelah diberikan konfirmasi.</p>

                         <input type="checkbox" name="setuju" required class="form-check-input" id="setuju">
                         <label class="form-check-label" for="setuju">Saya faham, dan saya mengerti.</label>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                          <button type="submit" class="btn btn-danger">Ajukan Pinjaman</button>
                        </div>
                      </div>
                    </div>
                  </div>

              </form>

          </div>
        </div>
      </div>

  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script>
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

          // Preview Bukti Foto (maksimal 5 file)
          var maxFiles = 5;
          $("#bukti_foto").on("change", function () {
              var files = this.files;
              var container = $("#preview_foto");
              container.empty();
              if (files.length > maxFiles) {
                  container.append('<div class="col-12"><span class="text-danger">Maksimal ' + maxFiles + ' file.</span></div>');
                  this.value = "";
                  return;
              }
              for (var i = 0; i < files.length; i++) {
                  (function (file) {
                      if (!file.type.match("image/(jpeg|jpg|png)")) return;
                      var reader = new FileReader();
                      reader.onload = function (e) {
                          container.append(
                              '<div class="col-md-2 col-4 mb-2">' +
                              '<img src="' + e.target.result + '" class="img-fluid img-thumbnail" alt="preview">' +
                              '</div>'
                          );
                      };
                      reader.readAsDataURL(file);
                  })(files[i]);
              }
          });
      });
  </script>
  
@endsection
@extends('layouts.thema')

@section('content')

    <div class="col-lg-6">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
          	<div style="float: right;">
              <a href="{{asset('doc/template_import_user.xlsx')}}" class="btn btn-outline-success btn-sm">
              	<span><i class="ti ti-download"></i></span> Template Excel</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">IMPORT DATA USER</h5>
            <form method="post" action="{{url('admin/import')}}" enctype="multipart/form-data">
				@csrf
				<label>Import User</label><br><br>
				<input type="file" class="form-control" name="file"><br>
				<button type="submit" class="btn btn-primary">submit</button>
			</form>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">NOTE :</h5>
            <p>kolom USERNAME dapat diisi NIM/NID/NIP/NUP.</p>
            <p>kolom EMAIL, pastikan email aktif sehinggal dapat digunakan untuk memberikan notifikasi.</p>
            <p>kolom jurusan wajib diisi, sesuai dengan jurusannya masing-masing.</p>
            <p>Password default, bisa di beda-bedakan atau disamakan semua, atau bisa juga mengambil NIM/NID/NIP/NUP sebagai password sementara</p>
            <p>Akses menentukan user login sebagai apa: <br>
				- dosen = user login sebagai dosen <br>
				- mahasiswa = user login sebagai mahasiswa <br>
				- lpl = user login sebagai petugas lab <br><br>
				pastikan pada kolom akses penulisan dosen, mahasiswa, dan lpl huruf kecil semua</p>
          </div>
        </div>
      </div>
    </div>

@endsection

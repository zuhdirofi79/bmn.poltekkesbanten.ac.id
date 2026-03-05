@extends('layouts.thema')

@section('content')
      
        <div class="col-md-3">
          <div class="card">
            <div class="card-body">
              <img src="{{asset('img/profile/'.$user->foto_profile)}}" alt="" class="rounded-circle img-fluid"><br><br>
              <h5 class="text-center">
                <!-- <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModal">Upload Foto Profile</button> <br><br> -->
                <b>{{$user->name}}</b> <br> 
              Akses :
              @foreach($roles as $a)
              <span class="badge bg-primary rounded-3 fw-semibold mt-2"><small>{{$a->name}}</small></span> 
              @endforeach
              </h5>
            </div>
          </div>
        </div>

        <div class="col-md-9">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title fw-semibold mb-4">
                <a href="{{url('profile')}}" class="btn btn-{{(request()->segment(1) == 'profile')? '':'outline-'}}primary">Profile</a> &nbsp;&nbsp;
                <a href="{{route('changePassword')}}" class="btn btn-outline-primary">Password</a>
              </h5>

              <form action="{{url('update_profile')}}" method="post">
                @csrf
                <div class="form-group mb-3 row">
                  <label for="name" class="col-sm-2 col-form-label">Nama Lengkap</label>
                  <div class="col-sm-10">
                    <input type="text" class="form-control" id="name" name="name" value="{{$user->name}}" disabled>
                  </div>
                </div>

                <div class="form-group mb-3 row">
                  <label for="username" class="col-sm-2 col-form-label">
                    @if($user->role=='mahasiswa')
                      NIM
                    @elseif($user->role=='dosen')
                      NID
                    @else
                      NIP/NUP
                    @endif
                  </label>
                  <div class="col-sm-10">
                    <input type="text" class="form-control" id="username" name="username" value="{{$user->username}}" disabled>
                  </div>
                </div>

                @if($user->role=='mahasiswa' or $user->role=='dosen')
                <div class="form-group mb-3 row">
                  <label for="jurusan" class="col-sm-2 col-form-label">Jurusan</label>
                  <div class="col-sm-10">
                    <input type="text" class="form-control" id="jurusan" name="jurusan" value="{{$user->jurusan}}" disabled>
                  </div>
                </div>
                @endif

                <div class="form-group mb-3 row">
                  <label for="email" class="col-sm-2 col-form-label">E-Mail</label>
                  <div class="col-sm-10">
                    <input type="email" class="form-control" id="email" name="email" value="{{$user->email}}" required>
                  </div>
                </div>

                <div class="form-group mb-3 row">
                  <label for="jenis_kelamin" class="col-sm-2 col-form-label">Jenis Kelamin</label>
                  <div class="col-sm-10">
                    <select id="jenis_kelamin" name="jenis_kelamin" class="form-control" required>
                      <option @if($user->foto_profile=='default.png') selected @endif>--Pilih--</option>
                      <option value="Laki-laki" @if($user->foto_profile=='male.png') selected @endif >Laki-laki</option>
                      <option value="Perempuan" @if($user->foto_profile=='female.png') selected @endif >Perempuan</option>
                    </select>
                  </div>
                </div>

                <div class="form-group mb-3 row">
                  <label for="no_hp" class="col-sm-2 col-form-label">No HP</label>
                  <div class="col-sm-10">
                    <input type="number" class="form-control" id="no_hp" name="no_hp" value="{{$user->no_hp}}" required>
                  </div>
                </div>

                <button type="submit" class="btn btn-outline-primary">Update</button>
              </form>

            </div>
          </div>
        </div>

    <!-- Modal foto profile-->
    <div class="modal fade" id="exampleModal" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('upload_foto')}}" method="post" enctype="multipart/form-data">
        @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Upload Foto Profile</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="foto_profile" class="col-sm-3 col-form-label">Foto Profile</label>
              <div class="col-sm-9">
                <input type="file" class="form-control" id="foto_profile" name="foto_profile" required>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Upload</button>
          </div>
        </form>
        </div>
      </div>
    </div>

@endsection
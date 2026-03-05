@extends('layouts.thema')

@section('content')
      
        <div class="col-md-3">
          <div class="card">
            <div class="card-body">
              <img src="{{asset('img/profile/'.$user->foto_profile)}}" alt="" class="rounded-circle img-fluid"><br><br>
              <h5 class="text-center">
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
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                  <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Profile</a>
                  </li>
                  <li class="nav-item" role="presentation">
                    <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Reset Password</a>
                  </li>
                </ul>
                </h5>

                <div class="tab-content" id="myTabContent">
                  <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                    <form action="{{url('admin/user_update')}}" method="post">
                      @csrf
                      <input type="hidden" name="id" value="{{$user->id}}">
                      <div class="form-group mb-3 row">
                        <label for="name" class="col-sm-2 col-form-label">Nama Lengkap</label>
                        <div class="col-sm-10">
                          <input type="text" class="form-control" id="name" name="name" value="{{$user->name}}" required>
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
                          <input type="text" class="form-control" id="username" name="username" value="{{$user->username}}" required>
                        </div>
                      </div>
                      
                      <div class="form-group mb-3 row">
                        <label for="jurusan" class="col-sm-2 col-form-label">Jurusan</label>
                        <div class="col-sm-10">
                          <select id="jurusan" name="jurusan" class="form-control" required>
                            <option value="{{$user->jurusan}}">{{$user->jurusan}}</option>
                            <option value="TLM">TLM</option>
                            <option value="Kebidanan">Kebidanan</option>
                            <option value="Keperawatan">Keperawatan</option>
                          </select>
                        </div>
                      </div>
                      
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
                          <input type="number" class="form-control" id="no_hp" name="no_hp" value="{{$user->no_hp}}">
                        </div>
                      </div>
                      <button type="submit" class="btn btn-outline-primary">Update</button>
                    </form>
                  </div>

                  <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <form method="POST" action="{{url('admin/user_reset')}}">
                      @csrf
                      <input type="hidden" name="id" value="{{$user->id}}">

                      <div class="form-group{{ $errors->has('new-password') ? ' has-error' : '' }} mb-3 row">
                        <label for="new-password" class="col-sm-3 col-form-label">Password Baru</label>
                        <div class="col-sm-9">
                          <input type="password" class="form-control" id="new-password" name="new-password" required>
                          @if ($errors->has('new-password'))
                          <span class="help-block">
                              <strong>{{ $errors->first('new-password') }}</strong>
                          </span>
                          @endif
                        </div>
                      </div>

                      <div class="form-group{{ $errors->has('new-password') ? ' has-error' : '' }} mb-3 row">
                        <label for="new-password-confirm" class="col-sm-3 col-form-label">Konfirmasi Password</label>
                        <div class="col-sm-9">
                          <input type="password" class="form-control" id="new-password-confirm" name="new-password-confirm" required>
                        </div>
                      </div>

                      <button type="submit" class="btn btn-outline-primary">Update</button>
                    </form>
                  </div>

                </div>
              

              

            </div>
          </div>
        </div>


@endsection
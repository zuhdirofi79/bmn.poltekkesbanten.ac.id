@extends('layouts.thema')

@section('content')
      
        <div class="col-md-3">
          <div class="card">
            <div class="card-body">
              <img src="{{asset('img/profile/'.$user->foto_profile)}}" alt="" class="rounded-circle img-fluid"><br><br>
              <h5 class="text-center"><b>{{$user->name}}</b> <br> 
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
                <a href="{{url('profile')}}" class="btn btn-outline-primary">Profile</a> &nbsp;&nbsp;
                <a href="{{route('changePassword')}}" class="btn btn-{{(request()->segment(1) == 'changePassword')? '':'outline-'}}primary">Password</a>
              </h5>

              <form method="POST" action="{{ route('changePassword') }}">
                @csrf
                <div class="form-group{{ $errors->has('current-password') ? ' has-error' : '' }} mb-3 row">
                  <label for="current-password" class="col-sm-3 col-form-label">Password Lama</label>
                  <div class="col-sm-9">
                    <input type="password" class="form-control" id="current-password" name="current-password" required>
                    @if ($errors->has('current-password'))
                    <span class="help-block">
                        <strong>{{ $errors->first('current-password') }}</strong>
                    </span>
                    @endif
                  </div>
                </div>


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

@endsection
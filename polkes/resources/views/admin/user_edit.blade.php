@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card col-sm-8">
          <div class="card-body row">
            <h5 class="card-title fw-semibold mb-4">User Edit </h5>
            <br>

            <div class="col-sm-12">
              <div class="form-group mb-3 row">
                <label for="nama" class="col-sm-2 col-form-label">Nama</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="nama" value="{{$user->name}}" disabled>
                </div>
              </div>

              <div class="form-group mb-3 row">
                <label for="nama" class="col-sm-2 col-form-label">NIM/NID</label>
                <div class="col-sm-10">
                  <input type="text" class="form-control" id="nama" value="{{$user->username}}" disabled>
                </div>
              </div>

              <div class="form-group mb-3 row">
                <label for="email" class="col-sm-2 col-form-label">Email</label>
                <div class="col-sm-10">
                  <input type="email" class="form-control" id="email" Value="{{$user->email}}" disabled>
                </div>
              </div>
            </div>

            <div class="col-sm-6">
              <p><b>Role yang dimiliki</b></p>
              <table class="table table-bordered table-sm">
                <thead>
                  @foreach($role_have as $a)
                  <tr>
                    <td>{{$a->name}}</td>
                    <td><a href="{{url('admin/delete_role_user/'.$id.'/'.$a->role_id)}}" class="btn btn-outline-danger btn-sm" onclick="return confirm('Apakah anda yakin ingin menghapus role {{$a->name}} ?')">Delete</a></td>
                  </tr>
                  @endforeach
                </thead>
              </table>
            </div>
            <div class="col-sm-6">
              <p><b>Role yang tidak dimiliki</b></p>
              <table class="table table-bordered table-sm">
                <thead>
                  @foreach($role as $a)
                  <tr>
                    <td>{{$a->name}}</td>
                    <td><a href="{{url('admin/add_role_user/'.$id.'/'.$a->id)}}" class="btn btn-outline-primary btn-sm" onclick="return confirm('Apakah anda yakin ingin menambah role {{$a->name}} ?')">Add</a></td>
                  </tr>
                  @endforeach
                </thead>
              </table>
            </div>
          </div>

          </div>
        </div>
      </div>
@endsection
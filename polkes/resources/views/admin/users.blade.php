@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleModal">+ Tambah Data</a>
              <a href="{{url('admin/import_user')}}" class="btn btn-outline-primary btn-sm">+ import Data</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">Users</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Telepon</th>
                        <th width="56">Edit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($user as $a)
                    <tr>
                        <td>{{$loop->iteration}}</td>
                        <td>{{$a->username}}</td>
                        <td>{{$a->name}}</td>
                        <td>{{$a->email}}</td>
                        <td>{{$a->no_hp}}</td>
                        <td><a href="{{url('admin/user/'.$a->id)}}" target="_blank" class="btn btn-outline-primary btn-sm"><span><i class="ti ti-edit"></i></span></a>
                        <a href="{{url('admin/delete_user/'.Crypt::encrypt($a->id))}}" class="btn btn-outline-danger btn-sm" 
                        onclick="return confirm('Apakah anda yakin ingin menghapus user {{$a->username}} - {{$a->name}}?');"><span><i class="ti ti-trash fs-4"></i></span></a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

          </div>
        </div>
      </div>


    <!-- Modal add kategori-->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <form action="{{url('admin/add_user')}}" method="post">
        @csrf
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data User</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="username" class="col-sm-3 col-form-label">NIM/NID/NIP/NUP</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="username" name="username" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="name" class="col-sm-3 col-form-label">Nama Lengkap</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="name" name="name" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="email" class="col-sm-3 col-form-label">Email</label>
              <div class="col-sm-9">
                <input type="email" class="form-control" id="email" name="email" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="role" class="col-sm-3 col-form-label">Akses Sebagai</label>
              <div class="col-sm-9">
                <select id="role" name="role" class="form-control" required>
                  <option disabled selected>-- Pilih --</option>
                  <option value="mahasiswa">Mahasiswa</option>
                  <option value="dosen">Dosen</option>
                  <option value="plp">PLP</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="jurusan" class="col-sm-3 col-form-label">Jurusan</label>
              <div class="col-sm-9">
                <select id="jurusan" name="jurusan" class="form-control" required>
                  <option disabled selected>-- Pilih --</option>
                  <option value="TLM">TLM</option>
                  <option value="Kebidanan">Kebidanan</option>
                  <option value="Keperawatan">Keperawatan</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="password" class="col-sm-3 col-form-label">Password</label>
              <div class="col-sm-9">
                <input type="password" class="form-control" id="password" name="password" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="password_confirmation" class="col-sm-3 col-form-label">Konfirmasi Password</label>
              <div class="col-sm-9">
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
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
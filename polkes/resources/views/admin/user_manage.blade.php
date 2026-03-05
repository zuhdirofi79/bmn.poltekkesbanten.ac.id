@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Users Manage</h5>
            <br>

            <table id="myTable" class="table table-striped table-bordered table-sm" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Role</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $key => $data)
                    <tr>
                        <td>{{$loop->iteration}}</td>
                        <td>{{$data->username}}</td>
                        <td>{{$data->name}}</td>
                        <td>
                            @if(!empty($data->getRoleNames()))
                             @foreach($data->getRoleNames() as $v)
                                <label class="badge bg-danger rounded-3 fw-semibold">{{ $v }}</label>
                             @endforeach
                            @endif
                        </td>
                        <td><a href="{{url('admin/user_edit/'.$data->id)}}" target="_blank" class="btn btn-outline-primary btn-sm"><span><i class="ti ti-edit"></i></span></a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

          </div>
        </div>
      </div>
@endsection
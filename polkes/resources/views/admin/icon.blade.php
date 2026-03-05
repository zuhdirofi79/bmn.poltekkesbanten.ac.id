@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">icon</h5>
            <br>

            <form method="post" action="{{url('admin/delete_icon')}}"> 
                @csrf
            <table class="table table-striped table-bordered table-sm" style="width:50%">
                <thead>
                    <tr>
                        <th></th>
                        <th>id</th>
                        <th>icon</th>
                        <th>name</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($icon as $a)
                    <tr>
                        <td><input type="checkbox" value="{{$a->id}}" class="form-check-input" name="id[]"></td>
                        <td>{{$loop->iteration}}</td>
                        <td><span><i class="ti ti-{{$a->name}} fs-8"></i></span></td>
                        <td>{{$a->name}}</td>
                        <td><button type="submit" class="btn btn-outline-danger"><span><i class="ti ti-trash fs-6"></i></span></button></td>
                    </tr>
                    @endforeach
                </tfoot>
            </table>
            </form>

          </div>
        </div>
      </div>
@endsection
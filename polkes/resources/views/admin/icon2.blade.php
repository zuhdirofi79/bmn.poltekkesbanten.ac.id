@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">icon</h5>
            <br>
            @foreach($icon as $a)
            <span><i class="ti ti-{{$a->name}} fs-8"></i></span>
            @endforeach

          </div>
        </div>
      </div>
@endsection
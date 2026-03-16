@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Request Peminjaman Peralatan {{$jurusan}}</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="30">No</th>
                        <th>Nama Peminjam</th>
                        <th>Jenis Alat</th>
                        <th>Ruang Lab</th>
                        <th>Tingkat</th>
                        <th>Prodi</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th>Tgl Permintaan</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->user->name ?? '-'}}</td>
                    <td>{{$a->jenis}}</td>
                    <td>{{$a->ruangan}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{ $a->prodi->nama_prodi ?? '-' }}</td>
                    <td>{{ $a->jam_mulai ?? '-' }}</td>
                    <td>{{ $a->jam_selesai ?? '-' }}</td>
                    <td>{{$a->tgl_pinjaman}}</td>
                    <td><span class="badge bg-dark rounded-3 fw-semibold">{{$a->status}}</span></td>
                    <td><a href="{{url('petugas/request_detail/'.$a->id)}}" target="_blank" class="btn btn-outline-primary btn-sm">Detail</a></td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>

@endsection
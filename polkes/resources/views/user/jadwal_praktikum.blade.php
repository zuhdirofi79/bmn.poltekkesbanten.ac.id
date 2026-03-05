@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">JADWAL PRAKTEK LAB JURUSAN {{strtoupper($jurusan)}} SEMESTER {{strtoupper($semester->semester)}} {{$semester->ta}}</h5>

            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Tingkat</th>
                        <th>Ruangan Lab</th>
                        <th>Hari</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->tgl}}</td>
                    <td>{{$a->kelas}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->ruang}}</td>
                    <td>{{$a->hari}}</td>
                    <td>{{substr($a->waktu_mulai,0,5)}}</td>
                    <td>{{substr($a->waktu_selesai,0,5)}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>

          </div>
        </div>
      </div>

      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a  href="{{url('user/req_jadwal')}}" class="btn btn-outline-primary btn-sm"><span><i class="ti ti-plus"></i></span> Formulir Peminjaman Ruang Lab</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">PERMINTAAN PEMINJAMAN RUANG LAB JURUSAN {{strtoupper($jurusan)}} SEMESTER {{strtoupper($semester->semester)}} {{$semester->ta}}</h5>

            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Tingkat</th>
                        <th>Ruangan Lab</th>
                        <th>Hari</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data_req as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->updated_at}}</td>
                    <td>{{$a->kelas}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->ruang}}</td>
                    <td>{{$a->hari}}</td>
                    <td>{{substr($a->waktu_mulai,0,5)}}</td>
                    <td>{{substr($a->waktu_selesai,0,5)}}</td>
                    <td>
                        @if($a->status=='Diterima')
                        <span class="badge bg-success rounded-3 fw-semibold">{{$a->status}}</span>
                        @elseif($a->status=='Menunggu Konfirmasi')
                        <span class="badge bg-dark rounded-3 fw-semibold">{{$a->status}}</span>
                        @else
                        <span class="badge bg-danger rounded-3 fw-semibold">{{$a->status}}</span>
                        @endif
                    </td>
                  </tr>
                  @endforeach
                </tbody>
            </table>

          </div>
        </div>
      </div>


@endsection
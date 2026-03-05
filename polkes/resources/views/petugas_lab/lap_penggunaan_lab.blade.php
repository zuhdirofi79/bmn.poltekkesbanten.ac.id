@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Penggunaan Lab</h5>

            <form action="{{ url('petugas/laporan/penggunaan_lab') }}" method="get">  
              <div style="" class="d-flex justify-content-center">
                <input type="hidden" name="jurusan" value="{{$jurusan}}">
                <input type="date" class="form-control" id="tgl_awal" name="tgl_awal" style="width:250px;" required>
                 &nbsp; &nbsp; Sampai Dengan &nbsp; &nbsp;
                <input type="date" class="form-control" id="tgl_akhir" name="tgl_akhir" style="width:250px;" required>
                 &nbsp;
                <button type="submit" class="btn btn-outline-primary">Submit</button>
              </div>
            </form>
            <br>

            @if($data)
            <table id="exampleButton" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Ruang Lab</th>
                        <th>Penanggung Jawab</th>
                        <th>Jurusan</th>
                        <th>Kelas</th>
                        <th>Tingkat</th>
                        <th>Tgl Praktikum</th>
                        <th>Hari</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th>Tujuan</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->ruang}}</td>
                    <td>{{$a->penanggung_jawab}}</td>
                    <td>{{$a->jurusan}}</td>
                    <td>{{$a->kelas}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{$a->tgl}}</td>
                    <td>{{$a->hari}}</td>
                    <td>{{substr($a->waktu_mulai,0,5)}}</td>
                    <td>{{substr($a->waktu_selesai,0,5)}}</td>
                    <td>{{$a->tujuan}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
            @endif
          </div>
        </div>
      </div>
    </div>

@endsection
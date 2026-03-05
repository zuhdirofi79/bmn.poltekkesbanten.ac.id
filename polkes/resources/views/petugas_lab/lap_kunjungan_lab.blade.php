@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Kunjungan Lab</h5>

            <form action="{{ url('petugas/laporan/kunjungan_lab') }}" method="get">  
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
                        <th>Nama</th>
                        <th>Tgl Kunjungan</th>
                        <th>Jurusan</th>
                        @if($jurusan=='keperawatan')
                        <th>Prodi</th>
                        @endif
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th>Ruang Lab</th>
                        <th>Kelas</th>
                        <th>Tujuan</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->name}}</td>
                    <td>{{$a->tgl_kunjungan}}</td>
                    <td>{{$a->jurusan}}</td>
                    @if($jurusan=='keperawatan')
                    <td>{{$a->prodi}}</td>
                    @endif
                    <td>{{substr($a->waktu_mulai,0,5)}}</td>
                    <td>{{substr($a->waktu_selesai,0,5)}}</td>
                    <td>{{$a->ruang_lab}}</td>
                    <td>{{$a->kelas}}</td>
                    <td>{{$a->tujuan}}</td>
                    <td>{{$a->keterangan}}</td>
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
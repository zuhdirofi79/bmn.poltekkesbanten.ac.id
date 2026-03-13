@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Bahan Medis</h5>

            <form action="{{ url('petugas/laporan/bahan_bhp') }}" method="get" class="mb-4">
              <input type="hidden" name="jurusan" value="{{ $jurusan }}">
              <label for="status_expired" class="form-label">Status Expired</label>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <select name="status_expired" id="status_expired" class="form-select" style="width:auto; max-width:220px;">
                  <option value="all" {{ ($statusExpired ?? 'all') === 'all' ? 'selected' : '' }}>Semua</option>
                  <option value="expired" {{ ($statusExpired ?? '') === 'expired' ? 'selected' : '' }}>Sudah Expired</option>
                  <option value="not_expired" {{ ($statusExpired ?? '') === 'not_expired' ? 'selected' : '' }}>Belum Expired</option>
                </select>
                <button type="submit" class="btn btn-outline-primary">Filter</button>
              </div>
            </form>

            <table id="exampleButton" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Kode Barang</th>
                        <th>Merk</th>
                        <th>Stok</th>
                        <th>tgl Expired</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->kode_barang}}</td>
                    <td>{{$a->kimia}}</td>
                    <td>{{$a->stok}} {{$a->satuan}}</td>
                    <td>{{$a->tgl_expired}}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

@endsection
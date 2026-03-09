@extends('layouts.thema')

@section('content')
      <div class="container-fluid col-lg-9">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">EDIT BAHAN BHP</h5>
            <form action="{{url('petugas/edit_bhp_bahan')}}" method="post">
            @csrf
            <input type="hidden" name="id" value="{{$data->id}}">
            <input type="hidden" name="jurusan" value="{{$data->jurusan}}">

            <div class="form-group mb-3 row">
              <label for="nama" class="col-sm-3 col-form-label">Nama</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="nama" name="nama" value="{{$data->nama}}" required>
              </div>
            </div>
            <div class="form-group mb-3 row">
              <label for="kode_barang" class="col-sm-3 col-form-label">Kode Barang</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="kode_barang" name="kode_barang" value="{{$data->kode_barang}}" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="kimia" class="col-sm-3 col-form-label">Merk</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="kimia" name="kimia" value="{{$data->kimia}}">
              </div>
            </div>
                          
            <div class="form-group mb-3 row">
                <label for="satuan" class="col-sm-3 col-form-label">Satuan</label>
                <div class="col-sm-9">
                  <select id="satuan" name="satuan" class="form-control" required>
                      <option value="gram" {{($data->satuan=='gram')?'selected':''}}>gram</option>
                      <option value="mg" {{($data->satuan=='mg')?'selected':''}}>mg</option>
                      <option value="liter" {{($data->satuan=='liter')?'selected':''}}>liter</option>
                      <option value="ml" {{($data->satuan=='ml')?'selected':''}}>ml</option>
                      <option value="box" {{($data->satuan=='box')?'selected':''}}>box</option>
                      <option value="pcs" {{($data->satuan=='pcs')?'selected':''}}>pcs</option>
                      <option value="pack" {{($data->satuan=='pack')?'selected':''}}>pack</option>
                      <option value="botol" {{($data->satuan=='botol')?'selected':''}}>botol</option>
                  </select>
                </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="tgl_expired" class="col-sm-3 col-form-label">Tgl Expired</label>
              <div class="col-sm-9">
                <input type="date" class="form-control" id="tgl_expired" name="tgl_expired" value="{{$data->tgl_expired}}" required>
              </div>
            </div>


            <div style="float: right;">
              <a href="{{url('petugas/barangbhp?jurusan='.$data->jurusan)}}" class="btn btn-secondary">Back</a>
              <button type="submit" class="btn btn-primary">Edit</button>
            </div>

            </form>
          </div>
        </div>
      </div>

@endsection
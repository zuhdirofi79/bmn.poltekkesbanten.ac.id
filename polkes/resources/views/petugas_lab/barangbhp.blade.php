@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <div style="float: right;">
              <a href="{{url('petugas/barang?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Aset Laboratorium</a>&nbsp;
              <a href="{{url('petugas/barangmedis?jurusan='.$jurusan)}}" class="btn btn-outline-primary btn-sm">Alat Medis</a>&nbsp;
              <a href="{{url('petugas/barangbhp?jurusan='.$jurusan)}}" class="btn btn-primary btn-sm">Bahan Medis</a>
            </div>
            <h5 class="card-title fw-semibold mb-4">BAHAN MEDIS - JURUSAN {{strtoupper($jurusan)}}</h5><hr>

            <p style="text-align:right">
              <a  href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#ModalAddBahan"><span><i class="ti ti-plus"></i></span> Data Bahan</a>
              <a  href="" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#exampleStok"><span><i class="ti ti-plus"></i></span> Stok Barang</a>
            </p>
            
            <form action="{{url('petugas/delete_bhp_bahan')}}" method="post">
              @csrf
              <input type="hidden" name="jurusan" value="{{$jurusan}}">

            <table id="myTable2" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th></th>
                        <th>Nama</th>
                        <th>Ruangan</th>
                        <th>Lokasi</th>
                        <th>Kode Barang</th>
                        <th>Merk</th>
                        <th>Stok</th>
                        <th>Satuan</th>
                        <th>tgl Expired</th>
                        <th width="30">Edit</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($bhp_bahan as $b)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td><input type="checkbox" class="custom-control-input checkItem" name="ids[]"  value="{{$b->id}}"></td>
                    <td>{{$b->nama}}</td>
                    <td>{{ optional($b->ruangan)->nama_ruang ?? '-' }}</td>
                    <td>{{ optional(optional($b->ruangan)->location)->name ?? '-' }}</td>
                    <td>{{$b->kode_barang}}</td>
                    <td>{{$b->kimia}}</td>
                    <td>{{$b->stok}}</td>
                    <td>{{$b->satuan}}</td>
                    <td>{{$b->tgl_expired}}</td>
                    <td>
                      <a href="{{url('petugas/edit_bhp/'.$b->id)}}" class="btn btn-primary btn-sm"><span><i class="ti ti-edit fs-4"></i></span></a>
                    </td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
            <div class="mt-3">
              <button type="button" class="btn btn-secondary btn-sm" id="toggleAll">Tandai Semua</button>
              <button type="submit" class="btn btn-danger btn-sm">Hapus Data yang Ditandai</button>
            </div>
            </form>
          </div>
        </div>
      </div>


    <!-- Modal add bhp bahan-->
    <div class="modal fade" id="ModalAddBahan" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('petugas/add_bhp_bahan')}}" method="post">
        @csrf
          <input type="hidden" name="jurusan" value="{{$jurusan}}">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Data BHP Bahan</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="nama" class="col-sm-3 col-form-label">Nama Bahan</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="nama" name="nama" required>
              </div>
            </div>
            
            <div class="form-group mb-3 row">
              <label for="kimia" class="col-sm-3 col-form-label">Merk</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="kimia" name="kimia">
                <small class="form-text text-muted">Opsional</small>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="kode_barang" class="col-sm-3 col-form-label">Kode Barang</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="kode_barang" name="kode_barang" placeholder="Masukkan kode barang">
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="stok" class="col-sm-3 col-form-label">Stok Awal</label>
              <div class="col-sm-9">
                <input type="number" class="form-control" id="stok" name="stok" required>
              </div>
            </div>
            
            <div class="form-group mb-3 row">
              <label for="satuan" class="col-sm-3 col-form-label">Satuan</label>
              <div class="col-sm-9">
                <select id="satuan" name="satuan" class="form-control" required>
                    <option value="">--Pilih--</option>
                    <option value="gram">gram</option>
                    <option value="mg">mg</option>
                    <option value="liter">liter</option>
                    <option value="ml">ml</option>
                    <option value="box">box</option>
                    <option value="pcs">pcs</option>
                    <option value="pack">pack</option>
                    <option value="botol">botol</option>
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="tgl_expired" class="col-sm-3 col-form-label">Tgl Expired</label>
              <div class="col-sm-9">
                <input type="date" class="form-control" id="tgl_expired" name="tgl_expired" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="ruangan_id" class="col-sm-3 col-form-label">Ruangan</label>
              <div class="col-sm-9">
                <select name="ruangan_id" class="form-control">
                  <option value="">-- Pilih Ruangan --</option>
                  @foreach($ruangan as $r)
                  <option value="{{$r->id}}">{{$r->kampus}} - {{$r->nama_ruang}}</option>
                  @endforeach
                </select>
                <small class="text-muted">Lokasi mengikuti ruangan yang dipilih.</small>
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

    <!-- Modal stok barang-->
    <div class="modal fade" id="exampleStok" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="{{url('petugas/penambahan_stok_barang')}}" method="post">
        @csrf
          <input type="hidden" name="jurusan" value="{{$jurusan}}">
          <input type="hidden" name="jenis_barang" value="Bahan Medis">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Tambah Stok Barang</h5>
            <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
          </div>
          <div class="modal-body">

            <div class="form-group mb-3 row">
              <label for="barang_id" class="col-sm-3 col-form-label">Nama Barang</label>
              <div class="col-sm-9">
                <select id="barang_id" name="barang_id" class="form-control" required>
                    <option value="">--Pilih--</option>
                  @foreach($bhp_bahan as $a)
                    <option value="{{$a->id}}">{{$a->nama}}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="stok" class="col-sm-3 col-form-label">Stok Penambahan</label>
              <div class="col-sm-9">
                <input type="number" class="form-control" id="stok" name="stok" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="tgl_expired" class="col-sm-3 col-form-label">Tgl Expired</label>
              <div class="col-sm-9">
                <input type="date" class="form-control" id="tgl_expired" name="tgl_expired" required>
              </div>
            </div>

            <div class="form-group mb-3 row">
              <label for="keterangan" class="col-sm-3 col-form-label">Keterangan</label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="keterangan" name="keterangan" value="Penambahan Stok Barang">
                <small>Opsional.</small>
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

<script>
    // Toggle semua checkbox
  document.getElementById('toggleAll').addEventListener('click', function () {
    let checkboxes = document.querySelectorAll('.checkItem');
    let allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
  });
</script>
@endsection
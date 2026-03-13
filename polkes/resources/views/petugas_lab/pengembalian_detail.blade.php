@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Peminjaman & Pengembalian Peralatan Detail {{$jurusan}}</h5>
            <form action="{{url('petugas/pengembalian_submit')}}" method="post" onsubmit="disableSubmitButton()">
              @csrf
              <input type="hidden" name="peminjaman_id" value="{{$id}}">
            <table class="table table-bordered" style="width:100%">
                <thead style="background-color:#78faed;">
                    <tr>
                        <th width="30">No</th>
                        <th>Jenis</th>
                        <th>Nama Barang</th>
                        @if($status->jenis=='Bahan Medis')
                        <th>Tgl Pemakaian</th>
                        <th>Stok Pemakaian</th>
                        <th>Status</th>
                        @else
                        <th>Tgl Pinjaman</th>
                        <th>Tgl Pengembalian</th>
                        <th>Stok Pinjam</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <input type="hidden" name="id[]" value="{{$a->id}}">
                  <input type="hidden" name="barang_id[]" value="{{$a->barang_id}}">
                  <input type="hidden" name="stok_pinjam[]" value="{{$a->stok_pinjam}}">
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->jenis}}</td>
                    <td>{{$a->nama}}</td>
                    @if($status->jenis=='Bahan Medis')
                        <td>{{$a->tgl_pinjaman}}</td>
                        <td>{{$a->stok_pinjam}} {{$a->satuan}}</td>
                        <td>{{$a->status}}</td>
                    @else
                        <td>{{$a->tgl_pinjaman}}</td>
                        <td>{{$a->tgl_pengembalian}}</td>
                        <td>{{$a->stok_pinjam}} {{$a->satuan}}</td>
                        <td>
                        @if($a->status=='Dipinjamkan')
                          <select id="status" name="status[]" class="form-control" required>
                            <option value="">{{$a->status}}</option>
                            <option value="Dikembalikan">Dikembalikan</option>
                            <option value="Hilang">Hilang</option>
                          </select>
                        @else
                          {{$a->status}}
                        @endif
                        </td>
                        <td>
                        @if($a->status=='Dipinjamkan')
                          <select id="kondisi" name="kondisi[]" class="form-control" required>
                            <option value=""></option>
                            <option value="Baik">Baik</option>
                            <option value="Rusak">Rusak</option>
                            <option value="Pecah">Pecah</option>
                            <option value="Hilang">Hilang</option>
                          </select>
                        @else
                          {{$a->kondisi}}
                        @endif
                        </td>
                    @endif
                  </tr>
                  @endforeach
                </tbody>
            </table>
            
            @if($data)
            @if($status->status=='Menunggu Dikembalikan')
            <div style="float: right;">
              <button class="btn btn-primary btn-sm" type="submit" id="submitButton">Submit</button>
            </div>
            @endif
            @endif
            </form>
          </div>
        </div>
      </div>

<script>
    function disableSubmitButton() {
        document.getElementById("submitButton").disabled = true;
    }
</script>
@endsection
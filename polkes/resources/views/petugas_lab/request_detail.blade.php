@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Request Peminjaman Peralatan Detail {{$jurusan}}</h5>
            <form action="{{url('petugas/request_submit')}}" method="post" onsubmit="disableSubmitButton()">
              @csrf
              <input type="hidden" name="peminjaman_id" value="{{$id}}">
            <table class="table table-bordered" style="width:100%">
                <thead style="background-color:#78faed;">
                    <tr>
                        <th width="30">No</th>
                        <th>Jenis</th>
                        <th>Nama Barang</th>
                        @if($jenis=='Bahan BHP')
                        <th>Tgl Pemakaian</th>
                        @else
                        <th>Tgl Pinjaman</th>
                        <th>Tgl Pengembalian</th>
                        @endif
                        <th>Stok Tersedia</th>
                        <th>Stok Pinjam</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <input type="hidden" name="id[]" value="{{$a->id}}">
                  <input type="hidden" name="barang_id[]" value="{{$a->barang_id}}">
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->jenis}}</td>
                    <td>{{$a->nama}}</td>
                    @if($jenis=='Bahan BHP')
                    <td>{{$a->tgl_pinjaman}}</td>
                    @else
                    <td>{{$a->tgl_pinjaman}}</td>
                    <td>{{$a->tgl_pengembalian}}</td>
                    @endif
                    <td>{{$a->stok}} {{$a->satuan}}</td>
                    <td>
                        @if($a->stok<=0)
                        <input type="number" name="stok_pinjam[]" value="0" class="form-control is-invalid" readonly>
                        <div id="validationServer03Feedback" class="invalid-feedback">
                            Stok Habis
                        </div>
                        
                        @else
                        <input type="number" name="stok_pinjam[]" value="{{$a->stok_pinjam}}" max="{{$a->stok}}" min="1" class="form-control" required>
                        @endif
                    </td>
                    <td>
                      <select id="status" name="status[]" class="form-control" required>
                        <option value="">{{$a->status}}</option>
                        @if($jenis=='Bahan BHP')
                        <option value="Pemakaian">Pemakaian</option>
                        @else
                        <option value="Dipinjamkan">Dipinjamkan</option>
                        @endif
                        <option value="Ditolak">Ditolak</option>
                      </select>
                    </td>
                  </tr>
                  @endforeach
                </tbody>
            </table>

            <div style="float: right;">
              <button class="btn btn-primary btn-sm" type="submit" id="submitButton">Submit</button>
            </div>
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
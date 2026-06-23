@extends('layouts.bhp_kebersihan_admin')

@section('page_title', 'Buat Stock Opname')

@section('content')
<div class="pa-card">
  <form method="POST" action="{{ route('bhp.admin.opname.store') }}">
    @csrf
    <div class="row g-2 mb-3">
      <div class="col-md-3">
        <label class="form-label">Tanggal Opname</label>
        <input type="date" name="tanggal_opname" value="{{ date('Y-m-d') }}" class="form-control" required>
      </div>
      <div class="col-md-9">
        <label class="form-label">Catatan</label>
        <input type="text" name="catatan" class="form-control" placeholder="Opsional">
      </div>
    </div>

    <div class="table-responsive">
      <table class="pa-table">
        <thead>
          <tr><th>Kode</th><th>Nama</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
          @foreach($rows as $i => $row)
            <tr>
              <td>{{ $row['kode'] }}<input type="hidden" name="items[{{ $i }}][barang_id]" value="{{ $row['id'] }}"></td>
              <td>{{ $row['nama'] }}</td>
              <td>
                {{ $row['sistem'] }}
                <input type="hidden" name="items[{{ $i }}][stok_sistem]" value="{{ $row['sistem'] }}">
              </td>
              <td><input type="number" min="0" name="items[{{ $i }}][stok_fisik]" value="{{ $row['sistem'] }}" class="form-control form-control-sm" style="width:100px"></td>
              <td><input type="text" name="items[{{ $i }}][keterangan]" class="form-control form-control-sm"></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <button type="submit" class="btn btn-prs-blue mt-3">Simpan Stock Opname</button>
  </form>
</div>
@endsection

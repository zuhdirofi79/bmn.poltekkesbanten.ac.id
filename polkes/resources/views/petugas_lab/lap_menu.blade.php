    <div class="col-lg-3">
      <div class="container-fluid">
        <div class="list-group">
          <a href="{{url('petugas/laporan/alat_lab?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='alat_lab')?'active':''}}">Lap Aset Laboratorium</a>
          <a href="{{url('petugas/laporan/alat_medis?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='alat_medis')?'active':''}}">Lap Alat Medis</a>
          <a href="{{url('petugas/laporan/bahan_bhp?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='bahan_bhp')?'active':''}}">Lap Bahan Medis (Aktif)</a>
          <a href="{{url('petugas/laporan/bahan_bhp_dimusnahkan?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='bahan_bhp_dimusnahkan')?'active':''}}">Lap Barang Dimusnahkan</a>
          <a href="{{url('petugas/laporan/penambahan_alat_lab?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='penambahan_alat_lab')?'active':''}}">Lap Penambahan Aset Laboratorium</a>
          <a href="{{url('petugas/laporan/penambahan_alat_medis?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='penambahan_alat_medis')?'active':''}}">Lap Penambahan Alat Medis</a>
          <a href="{{url('petugas/laporan/penambahan_bahan_bhp?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='penambahan_bahan_bhp')?'active':''}}">Lap Penambahan Bahan Medis</a>
          <a href="{{url('petugas/laporan/pinjaman_alat_lab?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='pinjaman_alat_lab')?'active':''}}">Lap Peminjaman dan Pengembalian Aset Laboratorium</a>
          <a href="{{url('petugas/laporan/pinjaman_alat_medis?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='pinjaman_alat_medis')?'active':''}}">Lap Peminjaman dan Pengembalian Alat Medis</a>
          <a href="{{url('petugas/laporan/pinjaman_bahan_bhp?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='pinjaman_bahan_bhp')?'active':''}}">Lap Peminjaman dan Pengembalian Bahan Medis</a>
          <a href="{{url('petugas/laporan/kunjungan_lab?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='kunjungan_lab')?'active':''}}">Lap Pengunjung Lab</a>
          <a href="{{url('petugas/laporan/penggunaan_lab?jurusan='.$jurusan)}}" class="list-group-item list-group-item-action {{(request()->segment(3)=='penggunaan_lab')?'active':''}}">Lap Penggunaan Lab</a>
        </div>
      </div>
    </div>
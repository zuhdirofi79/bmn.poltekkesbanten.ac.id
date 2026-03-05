<title>Laporan Pinjaman Barang Per tanggal {{substr($tgl_awal,0,10)}} sampai {{substr($tgl_akhir,0,10)}}</title>
<style>
table td, table th  { font-family: Arial, Helvetica, sans-serif;font-size: 12px;text-align:center}
.stylefontCustom { font-family: Arial, Helvetica, sans-serif;}
table {
  border-collapse: collapse;
    width: 100%;
}
table, th, td {
  border: 1px solid #000;
  text-align: left
}
th, td {
  padding : 2px
}

.head  th, .head  td {
  border: 0px ;
  text-align: left
}
</style>


<div style="margin:auto;width:700px">


<div class="stylefontCustom" style="text-align:center;font-size:12px;font-weight:bold" >
    Laporan Pinjaman Barang<br>
    {{substr($tgl_awal,0,10)}} sampai {{substr($tgl_akhir,0,10)}}
</div>
<br>

<table width=100%>
  <tr>
      <th width=5px>No</th>
      <th width=70px>Tgl Pinjam</th>
      <th width=90px>Nama</th>
      <th width=60px>Kode Barang</th>
      <th width=130px>Nama Barang</th>
      <th width=50px>Jumlah</th>
      <th>Status</th>
      <th width=90px>Petugas</th>
  </tr>
  @foreach($laporan_pinjaman as $a)
  <tr>
      <td>{{ $loop->iteration }}.</td>
      <td>{{ substr($a->tgl_pinjaman,0,10) }}</td>
      <td>{{ $a->name }}</td>
      <td> {{ $a->kode_barang }}</td>
      <td style="text-align:left"> {{ $a->nama_barang }}</td>
      <td>{{ $a->stok_pinjaman }}</td>
      <td>{{ $a->status }}</td>
      <td>{{ $a->petugas }}</td>
  </tr>
  @endforeach
</table>


</div>

<title>Laporan Barang Masuk Per tanggal {{substr($tgl_awal,0,10)}} sampai {{substr($tgl_akhir,0,10)}}</title>
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
    Laporan Barang Masuk<br>
    {{substr($tgl_awal,0,10)}} sampai {{substr($tgl_akhir,0,10)}}
</div>
<br>

<table width=100%>
  <tr>
      <th width=5px>No</th>
      <th width=70px>Tanggal</th>
      <th width=70px>Kode Barang</th>
      <th>Nama Barang</th>
      <th width=50px>stok</th>
      <th width=90px>Petugas</th>
  </tr>
  @foreach($laporan_barang_masuk as $a)
  <tr>
      <td>{{ $loop->iteration }}.</td>
      <td>{{ substr($a->updated_at,0,10) }}</td>
      <td>{{ $a->kode_barang }}</td>
      <td style="text-align:left"> {{ $a->nama_barang }}</td>
      <td>{{ $a->stok_masuk }}</td>
      <td>{{ $a->petugas }}</td>
  </tr>
  @endforeach
</table>


</div>

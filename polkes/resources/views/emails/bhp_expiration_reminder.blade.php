<h2>Reminder BHP Mendekati Expired</h2>

<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>Nama</th>
        <th>Jurusan</th>
        <th>Tanggal Expired</th>
        <th>Sisa Hari</th>
    </tr>

@foreach($items as $item)
<tr>
    <td>{{ $item['nama'] }}</td>
    <td>{{ $item['jurusan'] }}</td>
    <td>{{ $item['tgl_expired'] }}</td>
    <td>{{ $item['sisa_hari'] }}</td>
</tr>
@endforeach
</table>

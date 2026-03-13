@component('mail::message')
# Pemberitahuan Status Peminjaman

Halo {{$data['nama']}},

Permintaan Peminjaman Anda dengan nomor id {{$data['id']}} telah diterima.

### Detail Peminjaman:
- Nama Barang : {{$data['nama_barang']}}
- Jenis Barang : {{$data['jenis']}}
- Tanggal Peminjaman : {{$data['tgl_pinjaman']}}
- Tanggal Pengembalian : {{$data['tgl_pengembalian']}}

Mohon pastikan Anda mengembalikan barang tersebut sesuai kesepakatan, Jika jenis barang adalah Bahan Medis maka tidak perlu dikembalikan.

Terima kasih.

Salam,
{{ config('app.name') }}
@endcomponent
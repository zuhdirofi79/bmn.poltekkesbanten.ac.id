@component('mail::message')
# Reminder BHP Mendekati Expired

Berikut bahan BHP yang akan mendekati masa expired:

@component('mail::table')
| Nama BHP | Jurusan | Expired Date | Sisa Hari |
|:---------|:--------|:-------------|:----------|
@foreach($items as $item)
| {{ $item['nama'] }} | {{ $item['jurusan'] }} | {{ $item['tgl_expired'] }} | {{ $item['sisa_hari'] }} hari |
@endforeach
@endcomponent

Mohon segera lakukan pengecekan dan tindak lanjut terhadap stok yang mendekati expired.

Terima kasih.

Salam,<br>
{{ config('app.name') }}
@endcomponent

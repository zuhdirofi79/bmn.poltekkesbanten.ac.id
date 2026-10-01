<!DOCTYPE html>
<html lang="id">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>{{ $title }}</title>
  @include('documentation.pdf.styles')
</head>
<body>
  @include('documentation.pdf.cover')
  @include('documentation.pdf.toc')
  @include('documentation.pdf.intro')
  @include('documentation.pdf.aset')
  @include('documentation.pdf.peminjaman')
  @include('documentation.pdf.sewa')
  @include('documentation.pdf.pemeliharaan')
  @include('documentation.pdf.persediaan')
  @include('documentation.pdf.bhp')
  @include('documentation.pdf.closing')
</body>
</html>

@extends('layouts.persediaan_qr')

@section('page_title', $title ?? 'QR tidak valid')

@section('content')
<div class="prs-public-card">
  <h1 class="h5 mb-2">{{ $title ?? 'QR tidak valid' }}</h1>
  <p class="text-muted mb-3">{{ $message ?? 'Kode tidak ditemukan atau sudah tidak aktif.' }}</p>
  <a href="{{ route('public.persediaan.index') }}" class="btn btn-prs-blue w-100">Kembali ke Persediaan</a>
</div>
@endsection

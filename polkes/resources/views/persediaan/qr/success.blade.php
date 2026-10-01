@extends('layouts.persediaan_qr')

@section('page_title', 'Permintaan Diajukan')

@section('content')
<div class="prs-public-card text-center">
  <div class="display-6 mb-2">✓</div>
  <h1 class="h5">Permintaan Berhasil Diajukan</h1>
  <p class="text-muted mb-1">No. Permintaan</p>
  <p class="h4">{{ $permintaan->nomor_permintaan }}</p>
  @if($permintaan->rak)
    <p class="text-muted">Rak {{ $permintaan->rak->kode_rak }} – {{ $permintaan->rak->nama_rak }}</p>
  @endif
  <a href="{{ route('public.persediaan.permintaan.show', $permintaan->id) }}" class="btn btn-prs-blue w-100 mt-3 prs-touch">Lihat Permintaan</a>
</div>
@endsection

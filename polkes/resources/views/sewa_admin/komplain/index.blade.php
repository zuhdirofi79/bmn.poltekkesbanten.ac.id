@extends('layouts.sewa_admin')

@section('page_title', 'Komplain Asrama')
@section('breadcrumbs')
  <a href="{{ route('sewa-admin.dashboard') }}">Dashboard</a> / Komplain Asrama
@endsection

@section('content')
<div class="bmn-card">
  <div class="bmn-card-body text-center py-5">
    <div class="mb-3">
      <span class="badge badge-bmn-warning rounded-pill px-3 py-2" style="font-size:.85rem;">Progress / Coming Soon</span>
    </div>
    <i class="ti ti-message-report text-warning" style="font-size:64px;"></i>
    <h3 class="mt-3">Fitur Komplain Asrama</h3>
    <p class="text-muted mb-0">Fitur Komplain Asrama masih dalam proses pengembangan.</p>
    <p class="text-muted small">Modul ini akan tersedia setelah skema komplain &amp; alur eskalasi disetujui.</p>
  </div>
</div>
@endsection

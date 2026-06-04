@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Persediaan Aset BMN — Pilih Unit Kerja')

@php use App\Support\Persediaan\UnitKerja; @endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=2">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="prs-banner">
    <i class="ti ti-packages"></i>
    <div>
      <h1>Persediaan Aset BMN</h1>
      <p>Poltekkes Kemenkes Banten — Permintaan barang habis pakai / persediaan</p>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="prs-public-card">
    <span class="prs-section-title">Pilih Unit Kerja</span>

    @auth('persediaan')
      <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span>
          Anda login sebagai <strong>{{ auth('persediaan')->user()->nama }}</strong>
          ({{ UnitKerja::label(auth('persediaan')->user()->unit_kerja) }}).
        </span>
        <a href="{{ route('public.persediaan.permintaan.create') }}" class="btn btn-prs-blue btn-sm">
          Buat Permintaan <i class="ti ti-arrow-right"></i>
        </a>
      </div>
    @endauth

    <div class="prs-unit-grid">
      @foreach($units as $unit)
        @php $slug = UnitKerja::slugOf($unit); @endphp
        <a href="{{ route('public.persediaan.login', $slug) }}"
           class="prs-folder prs-folder--{{ $slug }}">
          <span class="prs-folder-ico"><i class="ti ti-building-bank"></i></span>
          <span class="prs-folder-title">{{ UnitKerja::label($unit) }}</span>
        </a>
      @endforeach
    </div>

    <p class="text-muted mt-4 mb-0" style="font-size:.85rem;">
      Pemohon wajib login / register khusus Persediaan sebelum mengisi form permintaan barang.
    </p>

    <div class="prs-admin-area-divider">Area Admin Persediaan</div>

    <div class="prs-admin-card">
      <div class="prs-admin-icon" aria-hidden="true">
        <i class="ti ti-shield-lock"></i>
      </div>
      <div class="flex-grow-1">
        <h3>Admin Persediaan BMN</h3>
        <p>Khusus petugas pengelolaan persediaan, pembelian, stok akhir, dan stock opname.</p>
        <a href="{{ route('persediaan.admin.login.show') }}"
           class="prs-admin-btn"
           aria-label="Masuk ke halaman Login Admin Persediaan">
          <i class="ti ti-login" aria-hidden="true"></i>
          <span>Login Admin Persediaan</span>
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

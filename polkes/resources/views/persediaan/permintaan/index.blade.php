@extends('layouts.public-bmn-dashboard')

@section('page_title', 'Permintaan Saya')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/persediaan.css') }}?v=1">
@endpush

@section('content')
<div class="prs-public-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="prs-section-title mb-0">Permintaan Saya</span>
    <div>
      <a href="{{ route('public.persediaan.permintaan.create') }}" class="btn btn-prs-blue btn-sm">
        <i class="ti ti-plus"></i> Buat Permintaan
      </a>
      <form action="{{ route('public.persediaan.logout') }}" method="POST" class="d-inline">
        @csrf
        <button class="btn btn-outline-danger btn-sm"><i class="ti ti-logout"></i> Keluar</button>
      </form>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="prs-public-card">
    <form method="GET" class="mb-3 d-flex gap-2" style="max-width:420px;">
      <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
             placeholder="Cari nomor permintaan...">
      <button class="btn btn-outline-secondary btn-sm"><i class="ti ti-search"></i></button>
    </form>

    <div class="table-responsive">
      <table class="prs-table">
        <thead>
          <tr>
            <th>Nomor Permintaan</th>
            <th>Tanggal</th>
            <th>Jumlah Item</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($permintaan as $p)
            <tr>
              <td><code>{{ $p->nomor_permintaan }}</code></td>
              <td>{{ optional($p->tanggal_permintaan)->format('d M Y') }}</td>
              <td>{{ $p->items()->count() }}</td>
              <td><span class="prs-badge {{ $p->status }}">{{ $p->statusLabel() }}</span></td>
              <td class="text-end">
                <a href="{{ route('public.persediaan.permintaan.show', $p->id) }}" class="btn btn-outline-primary btn-sm">
                  Detail
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada permintaan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $permintaan->links() }}</div>
  </div>
</div>
@endsection

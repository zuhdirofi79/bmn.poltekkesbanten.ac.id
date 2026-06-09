@extends('layouts.sewa_admin')

@section('page_title', 'Layanan Sewa')
@section('breadcrumbs')
  <a href="{{ route('sewa-admin.dashboard') }}">Dashboard</a> / Layanan Sewa
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-1">Kelola Layanan &amp; Foto Produk</h3>
    <p class="text-muted small mb-0">Unggah foto aset sewa agar tampil di halaman publik seperti marketplace.</p>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@foreach($tarifs as $kategori => $items)
  <div class="bmn-card mb-3">
    <div class="bmn-card-header"><h2>{{ $kategori }}</h2></div>
    <div class="bmn-card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Layanan</th>
              <th>Satuan</th>
              <th>Tarif Umum</th>
              <th>Tarif Pegawai</th>
              <th>Foto</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($items as $tarif)
              <tr>
                <td>{{ $tarif->nama_layanan }}</td>
                <td>{{ $tarif->satuan }}</td>
                <td>Rp {{ number_format((float) $tarif->tarif_umum, 0, ',', '.') }}</td>
                <td>
                  @if($tarif->tarif_pegawai !== null)
                    Rp {{ number_format((float) $tarif->tarif_pegawai, 0, ',', '.') }}
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  @if($tarif->primaryPhoto)
                    <img src="{{ $tarif->primaryPhoto->fotoUrl() }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
                  @endif
                  <span class="badge bg-light text-dark border">{{ $tarif->photos_count }} foto</span>
                </td>
                <td class="text-end">
                  <a href="{{ route('sewa-admin.tarif.show', $tarif->id) }}" class="btn btn-sm btn-outline-primary">
                    Kelola Foto
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endforeach
@endsection

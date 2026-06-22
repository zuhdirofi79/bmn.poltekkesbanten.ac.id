@extends('layouts.public_bmn')

@section('title', 'Sewa BMN')

@push('styles')
{{-- Icon Bootstrap Icons (bi-*) khusus untuk halaman ini.
     Tema utama BMN memakai Tabler Icons (ti-*), jadi kita inject bi-* hanya di sini
     agar tidak menambah beban global. --}}
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  .sewa-admin-area-divider {
    display: flex;
    align-items: center;
    gap: .75rem;
    color: #64748b;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .12em;
    margin: 1.75rem 0 1rem;
  }
  .sewa-admin-area-divider::before,
  .sewa-admin-area-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: #e2e8f0;
  }
  .sewa-admin-card {
    border: 1px dashed #94a3b8;
    border-radius: 12px;
    background: #f8fafc;
    padding: 1.25rem 1.25rem 1.1rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
  }
  .sewa-admin-card .sewa-admin-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 12px;
    background: #0f2a4a;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
  }
  .sewa-admin-card h3 {
    margin: 0 0 .25rem;
    font-size: 1rem;
    font-weight: 600;
    color: #0f172a;
  }
  .sewa-admin-card p {
    margin: 0 0 .65rem;
    color: #475569;
    font-size: .85rem;
    line-height: 1.4;
  }
  .sewa-admin-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem .9rem;
    border: 1px solid #0f2a4a;
    color: #0f2a4a;
    background: transparent;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 500;
    text-decoration: none;
    transition: all .15s ease-in-out;
  }
  .sewa-admin-btn:hover {
    background: #0f2a4a;
    color: #fff;
  }
  .sewa-sk-tarif-card {
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-top: 1rem;
  }
  .sewa-sk-tarif-card .sewa-sk-tarif-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 12px;
    background: #dc2626;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
  }
  .sewa-sk-tarif-card h3 {
    margin: 0 0 .35rem;
    font-size: 1rem;
    font-weight: 600;
    color: #0f172a;
  }
  .sewa-sk-tarif-card p {
    margin: 0 0 .75rem;
    color: #475569;
    font-size: .85rem;
    line-height: 1.45;
  }
  .sewa-sk-tarif-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
  }
  .sewa-sk-btn {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .45rem .85rem;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 500;
    text-decoration: none;
    transition: all .15s ease-in-out;
    border: 1px solid transparent;
  }
  .sewa-sk-btn-primary {
    background: #0f2a4a;
    color: #fff;
    border-color: #0f2a4a;
  }
  .sewa-sk-btn-primary:hover {
    background: #1a3d66;
    color: #fff;
  }
  .sewa-sk-btn-outline {
    background: #fff;
    color: #0f2a4a;
    border-color: #0f2a4a;
  }
  .sewa-sk-btn-outline:hover {
    background: #0f2a4a;
    color: #fff;
  }
  .sewa-sk-preview-frame {
    width: 100%;
    height: 500px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
  }
</style>
@endpush

@php
  $skTarifPdfUrl = 'https://bmn.poltekkesbanten.ac.id/doc/SK%20POLA%20TARIF%20BLU%20penunjang%20akademik%20REV%206.pdf';
  $skTarifDownloadName = 'SK Tarif BLU Sewa Poltekkes Banten.pdf';
@endphp

@section('content')
<div class="bmn-card">
  <div class="bmn-card-header">
    <div>
      <h2>Layanan Sewa BMN</h2>
      <div class="subtitle">Pilih jenis penyewa untuk melihat tarif dan mengajukan sewa</div>
    </div>
  </div>
  <div class="bmn-card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <a href="{{ route('public.sewa.umum') }}" class="btn btn-bmn-primary btn-lg w-100 py-4">
          <i class="ti ti-users fs-4 d-block mb-2"></i>
          Umum
        </a>
      </div>
      <div class="col-md-6">
        <a href="{{ route('public.sewa.pegawai') }}" class="btn btn-bmn-outline-success btn-lg w-100 py-4">
          <i class="ti ti-id-badge fs-4 d-block mb-2"></i>
          Pegawai Poltekkes / Kemenkes / Outsourcing
        </a>
      </div>
    </div>

    <div class="sewa-sk-tarif-card">
      <div class="sewa-sk-tarif-icon" aria-hidden="true">
        <i class="bi bi-file-earmark-pdf"></i>
      </div>
      <div class="flex-grow-1">
        <h3>Tarif Resmi Layanan Sewa BLU</h3>
        <p>
          Tarif sewa mengacu pada SK Direktur Poltekkes Kemenkes Banten tentang
          Tarif Layanan Penunjang Akademik BLU.
        </p>
        <div class="sewa-sk-tarif-actions">
          <a href="{{ $skTarifPdfUrl }}"
             class="sewa-sk-btn sewa-sk-btn-primary"
             target="_blank"
             rel="noopener noreferrer"
             aria-label="Lihat SK Tarif BLU Sewa Poltekkes Banten (PDF, tab baru)">
            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
            Lihat SK Tarif BLU
          </a>
          <a href="{{ $skTarifPdfUrl }}"
             class="sewa-sk-btn sewa-sk-btn-outline"
             download="{{ $skTarifDownloadName }}"
             aria-label="Download SK Tarif BLU Sewa Poltekkes Banten">
            <i class="bi bi-download" aria-hidden="true"></i>
            Download PDF
          </a>
          <button type="button"
                  class="sewa-sk-btn sewa-sk-btn-outline d-none d-md-inline-flex"
                  data-bs-toggle="collapse"
                  data-bs-target="#skTarifPreview"
                  aria-expanded="false"
                  aria-controls="skTarifPreview">
            <i class="bi bi-eye" aria-hidden="true"></i>
            Preview SK Tarif
          </button>
        </div>
        <div class="d-none d-md-block">
          <div class="collapse mt-3" id="skTarifPreview">
            <iframe class="sewa-sk-preview-frame"
                    src="{{ $skTarifPdfUrl }}"
                    title="SK Tarif BLU Sewa Poltekkes Banten"
                    loading="lazy"></iframe>
          </div>
        </div>
      </div>
    </div>

    <hr>

    <div class="text-center small">
      Sudah pernah mengajukan? <a href="{{ route('public.sewa.status') }}">Cek status pengajuan Anda</a>
    </div>

    {{-- ===== AREA ADMIN SEWA (terpisah dari Login Admin BMN) ===== --}}
    <div class="sewa-admin-area-divider">Area Admin Sewa</div>

    <div class="sewa-admin-card">
      <div class="sewa-admin-icon" aria-hidden="true">
        <i class="bi bi-person-lock"></i>
      </div>
      <div class="flex-grow-1">
        <h3>Admin Pengelolaan Sewa</h3>
        <p>Khusus petugas pengelolaan dan verifikasi sewa BMN.</p>
        <a href="{{ route('sewa-admin.login.show') }}"
           class="sewa-admin-btn"
           aria-label="Masuk ke halaman Login Admin Sewa">
          <i class="bi bi-person-lock" aria-hidden="true"></i>
          <span>Login Admin Sewa</span>
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

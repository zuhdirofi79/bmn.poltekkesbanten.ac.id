@extends('layouts.persediaan_admin')

@section('page_title', 'Master Rak')

@section('content')
<div class="pa-card mt-0">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h2 class="mb-1">QR Pintu Direktorat</h2>
      <p class="text-muted mb-2">Scan QR ini di pintu ruang Persediaan. Token tidak berubah saat gambar di-generate ulang.</p>
      <div class="small"><code>{{ $aksesUrl }}</code></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <form method="POST" action="{{ route('persediaan.admin.master-rak.akses.generate-qr') }}">
        @csrf
        <button class="btn btn-prs-blue btn-sm"><i class="ti ti-qrcode"></i> Generate / Ulang QR Pintu</button>
      </form>
      <a href="{{ route('persediaan.admin.master-rak.akses.download-qr') }}" class="btn btn-outline-secondary btn-sm">Download</a>
      <a href="{{ route('persediaan.admin.master-rak.akses.print') }}" target="_blank" class="btn btn-outline-secondary btn-sm">Print</a>
      <form method="POST" action="{{ route('persediaan.admin.master-rak.akses.rotate-token') }}"
            onsubmit="return confirm('Rotasi token membuat QR Pintu lama tidak berlaku. Lanjutkan?');">
        @csrf
        <button class="btn btn-outline-danger btn-sm">Rotasi Token QR</button>
      </form>
    </div>
  </div>
  @if($aksesQr)
    <img src="{{ $aksesQr }}" alt="QR Pintu" style="max-width:180px;margin-top:12px;border:1px solid #e3e9f2;border-radius:8px;padding:8px;background:#fff;">
  @endif
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <span class="prs-section-title mb-0">Master Rak Direktorat</span>
  <div class="d-flex flex-wrap gap-2">
    <form method="POST" action="{{ route('persediaan.admin.master-rak.generate-all-qr') }}">
      @csrf
      <button class="btn btn-outline-primary btn-sm"><i class="ti ti-qrcode"></i> Generate QR Semua Rak</button>
    </form>
    <a href="{{ route('persediaan.admin.master-rak.print') }}" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-printer"></i> Print QR Batch
    </a>
    <button class="btn btn-prs-green btn-sm" data-bs-toggle="modal" data-bs-target="#addRakModal">
      <i class="ti ti-plus"></i> Tambah Rak
    </button>
  </div>
</div>

<div class="pa-card mt-0">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Cari kode / nama</label>
      <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm">
    </div>
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Semua</option>
        <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
        <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-prs-blue btn-sm w-100"><i class="ti ti-filter"></i> Filter</button>
    </div>
  </form>
</div>

<div class="pa-card">
  <div class="table-responsive">
    <table class="pa-table mb-0">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama</th>
          <th>Lokasi</th>
          <th class="text-end">Barang</th>
          <th>Status</th>
          <th>QR</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($raks as $rak)
          <tr>
            <td><code>{{ $rak->kode_rak }}</code></td>
            <td>{{ $rak->nama_rak }}</td>
            <td>{{ $rak->lokasi_detail ?: '-' }}</td>
            <td class="text-end">{{ number_format($rak->assignments_count, 0, ',', '.') }}</td>
            <td>
              @if($rak->isAktif())
                <span class="prs-badge selesai">Aktif</span>
              @else
                <span class="prs-badge ditolak">Nonaktif</span>
              @endif
            </td>
            <td>
              @if($rak->qr_path)
                <span class="prs-badge disetujui">Ada</span>
              @else
                <span class="text-muted">Belum</span>
              @endif
            </td>
            <td class="text-end">
              <a href="{{ route('persediaan.admin.master-rak.show', $rak->id) }}" class="btn btn-outline-primary btn-sm" title="Kelola">
                <i class="ti ti-eye"></i>
              </a>
              <form method="POST" action="{{ route('persediaan.admin.master-rak.generate-qr', $rak->id) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-secondary btn-sm" title="Generate QR"><i class="ti ti-qrcode"></i></button>
              </form>
              <a href="{{ route('persediaan.admin.master-rak.download-qr', $rak->id) }}" class="btn btn-outline-secondary btn-sm" title="Download">
                <i class="ti ti-download"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-3">Belum ada rak Direktorat.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $raks->links() }}</div>
</div>

<div class="modal fade" id="addRakModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('persediaan.admin.master-rak.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Rak</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="unit_kerja" value="Direktorat">
        <div class="mb-2">
          <label class="form-label">Kode Rak</label>
          <input type="text" name="kode_rak" class="form-control" placeholder="A01" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Nama Rak</label>
          <input type="text" name="nama_rak" class="form-control" placeholder="ATK" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Lokasi detail</label>
          <input type="text" name="lokasi_detail" class="form-control">
        </div>
        <p class="small text-muted mb-0">Unit kerja dikunci ke Direktorat pada tahap ini.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-prs-green">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection

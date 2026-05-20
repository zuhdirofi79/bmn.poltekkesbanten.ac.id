@extends('layouts.thema')

@php
  /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $list */
  $statusBadge = [
    'pending'      => 'bg-warning text-dark',
    'approved'     => 'bg-info text-dark',
    'rejected'     => 'bg-danger',
    'dipinjam'     => 'bg-primary',
    'dikembalikan' => 'bg-success',
  ];
@endphp

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="card-title fw-semibold mb-0">
          <i class="ti ti-clipboard-check"></i> Peminjaman Aset BMN
        </h5>
        <div class="d-flex flex-wrap gap-2">
          @if($canRequest)
            <a href="{{ route('bmn.peminjaman-aset.create') }}" class="btn btn-primary btn-sm">
              <i class="ti ti-plus"></i> Ajukan Peminjaman
            </a>
          @endif
        </div>
      </div>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <form method="get" action="{{ route('bmn.peminjaman-aset.index') }}" class="card border mb-3">
        <div class="card-body py-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label mb-1">Pencarian</label>
              <input type="text" name="q" class="form-control form-control-sm"
                     value="{{ $filters['q'] ?? '' }}"
                     placeholder="Kode transaksi / tujuan peminjaman">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Status</label>
              <select name="status" class="form-control form-control-sm">
                <option value="">— Semua —</option>
                @foreach(['pending','approved','rejected','dipinjam','dikembalikan'] as $st)
                  <option value="{{ $st }}" {{ ($filters['status'] ?? '') === $st ? 'selected' : '' }}>
                    {{ ucfirst($st) }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Jurusan</label>
              <select name="jurusan" class="form-control form-control-sm">
                <option value="">— Semua —</option>
                @foreach(['direktorat','tlm','keperawatan','kebidanan'] as $j)
                  <option value="{{ $j }}" {{ ($filters['jurusan'] ?? '') === $j ? 'selected' : '' }}>
                    {{ ucfirst($j) }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">Tgl Pinjam Dari</label>
              <input type="date" name="tanggal_from" class="form-control form-control-sm"
                     value="{{ $filters['tanggal_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1">s/d</label>
              <input type="date" name="tanggal_to" class="form-control form-control-sm"
                     value="{{ $filters['tanggal_to'] ?? '' }}">
            </div>
            <div class="col-md-12 d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="ti ti-search"></i> Terapkan
              </button>
              <a href="{{ route('bmn.peminjaman-aset.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-refresh"></i> Reset
              </a>
            </div>
          </div>
        </div>
      </form>

      <div class="small text-muted mb-2">
        Total {{ number_format($list->total(), 0, ',', '.') }} transaksi
        @if($list->total() > 0)
          · Menampilkan {{ $list->firstItem() }}&ndash;{{ $list->lastItem() }}
        @endif
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle mb-0">
          <thead>
            <tr>
              <th width="40">#</th>
              <th>Kode</th>
              <th>Peminjam</th>
              <th>Jurusan</th>
              <th>Tujuan</th>
              <th>Tgl Pinjam</th>
              <th>Rencana Kembali</th>
              <th>Jml Aset</th>
              <th>Status</th>
              <th>Approver</th>
              <th width="80" class="text-center">Nota</th>
              <th width="100">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($list as $row)
              <tr>
                <td>{{ $list->firstItem() + $loop->index }}</td>
                <td><code>{{ $row->kode_peminjaman }}</code></td>
                <td>{{ optional($row->user)->name ?? '-' }}</td>
                <td>{{ $row->jurusan ? ucfirst($row->jurusan) : '-' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($row->tujuan_peminjaman, 60) }}</td>
                <td>{{ optional($row->tanggal_pinjam)->format('Y-m-d') }}</td>
                <td>{{ optional($row->rencana_kembali)->format('Y-m-d') }}</td>
                <td class="text-center">{{ $row->details_count }}</td>
                <td>
                  <span class="badge {{ $statusBadge[$row->status] ?? 'bg-secondary' }} rounded-pill">
                    {{ ucfirst($row->status) }}
                  </span>
                </td>
                <td>{{ optional($row->approver)->name ?? '—' }}</td>
                <td class="text-center">
                  @php $notaUrl = $row->notaDinasUrl(); @endphp
                  @if($notaUrl)
                    <a href="{{ $notaUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-sm btn-outline-primary"
                       title="Lihat Nota Dinas">
                      <i class="ti ti-file-text"></i>
                    </a>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  <a href="{{ route('bmn.peminjaman-aset.show', $row->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-eye"></i>
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="12" class="text-center text-muted">Belum ada transaksi peminjaman.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3 bmn-pagination">
        {{ $list->appends(request()->query())->links('pagination::bootstrap-5') }}
      </div>
    </div>
  </div>
</div>
@endsection

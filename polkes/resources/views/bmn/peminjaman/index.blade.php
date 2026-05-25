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
  $approvalTabs = [
    '' => 'Semua',
    'verifikator_1' => 'Menunggu Verifikator 1',
    'verifikator_2' => 'Menunggu Verifikator 2',
    'final_report' => 'Menunggu Final Report',
    'completed' => 'Selesai Verifikasi',
    'rejected' => 'Ditolak',
  ];
  $currentApprovalStage = $filters['approval_stage'] ?? '';
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

      <ul class="nav nav-pills flex-wrap gap-1 mb-3">
        @foreach($approvalTabs as $stageValue => $stageLabel)
          @php
            $tabQuery = array_filter(array_merge($filters, ['approval_stage' => $stageValue]));
            if ($stageValue === '') {
              unset($tabQuery['approval_stage']);
            }
          @endphp
          <li class="nav-item">
            <a class="nav-link btn-sm py-1 px-2 {{ $currentApprovalStage === $stageValue ? 'active' : '' }}"
               href="{{ route('bmn.peminjaman-aset.index', $tabQuery) }}">
              {{ $stageLabel }}
            </a>
          </li>
        @endforeach
      </ul>

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
            @if($currentApprovalStage !== '')
              <input type="hidden" name="approval_stage" value="{{ $currentApprovalStage }}">
            @endif
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
              <th>Tujuan</th>
              <th>Tgl Pinjam</th>
              <th>Status</th>
              <th>Tahap Approval</th>
              <th>Verifikator 1</th>
              <th>Verifikator 2</th>
              <th>Final Report</th>
              <th width="100">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($list as $row)
              @php $stageBadge = $row->approvalStageBadge(); @endphp
              <tr>
                <td>{{ $list->firstItem() + $loop->index }}</td>
                <td><code>{{ $row->kode_peminjaman }}</code></td>
                <td>{{ $row->contactName() }}</td>
                <td>{{ \Illuminate\Support\Str::limit($row->tujuan_peminjaman, 60) }}</td>
                <td>{{ optional($row->tanggal_pinjam)->format('Y-m-d') }}</td>
                <td>
                  <span class="badge {{ $statusBadge[$row->status] ?? 'bg-secondary' }} rounded-pill">
                    {{ ucfirst($row->status) }}
                  </span>
                </td>
                <td>
                  <span class="badge {{ $stageBadge['badge'] }} rounded-pill">
                    {{ $stageBadge['label'] }}
                  </span>
                </td>
                <td class="small">
                  @if($row->verifier_1_approved_at)
                    {{ optional($row->verifier1)->name ?? 'Pak Hendri' }}
                    <br><span class="text-muted">{{ $row->verifier_1_approved_at->format('Y-m-d H:i') }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="small">
                  @if($row->verifier_2_approved_at)
                    {{ optional($row->verifier2)->name ?? 'Pak Singgih' }}
                    <br><span class="text-muted">{{ $row->verifier_2_approved_at->format('Y-m-d H:i') }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="small">
                  @if($row->final_report_at)
                    {{ optional($row->finalReporter)->name ?? 'Pak Hendri' }}
                    <br><span class="text-muted">{{ $row->final_report_at->format('Y-m-d H:i') }}</span>
                  @else
                    <span class="text-muted">—</span>
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
                <td colspan="11" class="text-center text-muted">Belum ada transaksi peminjaman.</td>
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

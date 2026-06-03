@extends('layouts.bmn_admin')

@php
  $statusBadge = [
    'pending'      => 'bg-warning text-dark',
    'approved'     => 'bg-info text-dark',
    'rejected'     => 'bg-danger',
    'dipinjam'     => 'bg-primary',
    'dikembalikan' => 'bg-success',
  ];
  $timelineStatusBadge = [
    'pending' => 'bg-secondary',
    'approved' => 'bg-success',
    'rejected' => 'bg-danger',
    'completed' => 'bg-primary',
  ];
  $stageBadge = $peminjaman->approvalStageBadge();
  $singgihTl = $peminjaman->timelineSinggihApproval();
  $hendriTl = $peminjaman->timelineHendriApproval();

  $stageStatusLabel = [
    'pending'  => 'Pending',
    'approved' => 'Selesai',
    'rejected' => 'Ditolak',
  ];

  // Saat Singgih masih Pending, tahap Hendri belum aktif → label "Menunggu".
  $hendriDisplayStatus = $hendriTl['status'];
  if ($hendriDisplayStatus === 'pending' && $singgihTl['status'] !== 'approved') {
      $hendriDisplayStatus = 'menunggu';
  }
  $hendriBadgeClass = match ($hendriDisplayStatus) {
      'approved' => 'bg-success',
      'rejected' => 'bg-danger',
      'pending'  => 'bg-warning text-dark',
      default    => 'bg-secondary', // menunggu
  };
  $hendriLabel = match ($hendriDisplayStatus) {
      'approved' => 'Selesai',
      'rejected' => 'Ditolak',
      'pending'  => 'Pending',
      default    => 'Menunggu',
  };
@endphp

@section('page_title', 'Detail Peminjaman')
@section('breadcrumbs')
  <a href="{{ route('bmn.dashboard.index') }}">Dashboard</a> / <a href="{{ route('bmn.peminjaman-aset.index') }}">Peminjaman BMN</a> / {{ $peminjaman->kode_peminjaman }}
@endsection

@section('content')
<div class="d-flex justify-content-end mb-3">
  <a href="{{ route('bmn.peminjaman-aset.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="ti ti-arrow-left"></i> Kembali
  </a>
</div>
<div class="container-fluid">

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-md-8">
      <div class="card border h-100">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">Informasi Transaksi</h6>
          <div class="row g-2 small">
            <div class="col-md-4 text-muted">Status</div>
            <div class="col-md-8">
              <span class="badge {{ $statusBadge[$peminjaman->status] ?? 'bg-secondary' }} rounded-pill">
                {{ ucfirst($peminjaman->status) }}
              </span>
            </div>

            <div class="col-md-4 text-muted">Tahap Approval</div>
            <div class="col-md-8">
              <span class="badge {{ $stageBadge['badge'] }} rounded-pill">
                {{ $stageBadge['label'] }}
              </span>
            </div>

            <div class="col-md-4 text-muted">Nama Peminjam</div>
            <div class="col-md-8">
              {{ $peminjaman->nama_peminjam }}
              <span class="text-muted">
                ({{ optional($peminjaman->user)->jurusan ? ucfirst(optional($peminjaman->user)->jurusan) : ($peminjaman->jurusan ? ucfirst($peminjaman->jurusan) : '-') }})
              </span>
            </div>

            <div class="col-md-4 text-muted">No WhatsApp</div>
            <div class="col-md-8">
              @if($peminjaman->wa_peminjam_link)
                <a href="{{ $peminjaman->wa_peminjam_link }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-sm btn-success">
                  <i class="ti ti-brand-whatsapp"></i> Hubungi WhatsApp
                </a>
                <span class="text-muted ms-2 small">{{ $peminjaman->wa_peminjam }}</span>
              @else
                -
              @endif
            </div>

            <div class="col-md-4 text-muted">Email</div>
            <div class="col-md-8">
              @if($peminjaman->email_peminjam !== '-')
                <a href="mailto:{{ $peminjaman->email_peminjam }}"
                   class="btn btn-sm btn-outline-primary">
                  <i class="ti ti-mail"></i> Kirim Email
                </a>
                <span class="text-muted ms-2 small">{{ $peminjaman->email_peminjam }}</span>
              @else
                -
              @endif
            </div>

            <div class="col-md-4 text-muted">Tujuan</div>
            <div class="col-md-8">{{ $peminjaman->tujuan_peminjaman }}</div>

            @if($peminjaman->requested_barang)
              <div class="col-md-4 text-muted">Barang Diajukan</div>
              <div class="col-md-8">{{ $peminjaman->requested_barang }}</div>
            @endif

            @if($peminjaman->screened_at)
              <div class="col-md-4 text-muted">Screening Rumah Tangga</div>
              <div class="col-md-8">
                {{ optional($peminjaman->screener)->name ?? '-' }}
                · {{ $peminjaman->screened_at->format('Y-m-d H:i') }}
                · {{ ucfirst(str_replace('_', ' ', (string) $peminjaman->screening_status)) }}
                @if($peminjaman->screening_note)
                  <div class="text-muted mt-1">{{ $peminjaman->screening_note }}</div>
                @endif
              </div>
            @endif

            <div class="col-md-4 text-muted">Tanggal Pinjam</div>
            <div class="col-md-8">{{ optional($peminjaman->tanggal_pinjam)->format('Y-m-d') }}</div>

            <div class="col-md-4 text-muted">Rencana Kembali</div>
            <div class="col-md-8">{{ optional($peminjaman->rencana_kembali)->format('Y-m-d') }}</div>

            <div class="col-md-4 text-muted">Catatan</div>
            <div class="col-md-8">{{ $peminjaman->catatan ?? '-' }}</div>

            <div class="col-md-4 text-muted">Nota Dinas</div>
            <div class="col-md-8">
              @php
                $notaUrl = $peminjaman->notaDinasUrl();
                $notaExistsLocal = $peminjaman->notaDinasExistsLocally();
              @endphp
              @if($notaUrl)
                <a href="{{ $notaUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="btn btn-sm btn-outline-primary">
                  <i class="ti ti-file-text"></i> Lihat Nota Dinas
                </a>
                @if($peminjaman->nota_dinas_original_name)
                  <small class="text-muted ms-1">
                    {{ $peminjaman->nota_dinas_original_name }}
                  </small>
                @endif
                @if($notaExistsLocal === false)
                  <span class="badge bg-danger ms-1" title="File fisik tidak ditemukan di server">
                    File tidak ditemukan
                  </span>
                @endif
              @else
                <span class="text-muted">-</span>
              @endif
            </div>

            <div class="col-md-4 text-muted">Approver (Final)</div>
            <div class="col-md-8">
              {{ optional($peminjaman->approver)->name ?? '—' }}
              @if($peminjaman->approved_at)
                <span class="text-muted">· {{ $peminjaman->approved_at->format('Y-m-d H:i') }}</span>
              @endif
            </div>

            @if($peminjaman->status === 'rejected')
              <div class="col-md-4 text-muted">Ditolak Oleh</div>
              <div class="col-md-8">
                {{ optional($peminjaman->rejecter)->name ?? '—' }}
                @if($peminjaman->rejected_at)
                  <span class="text-muted">· {{ $peminjaman->rejected_at->format('Y-m-d H:i') }}</span>
                @endif
              </div>
              @if($peminjaman->rejected_reason)
                <div class="col-md-4 text-muted">Alasan Penolakan</div>
                <div class="col-md-8">{{ $peminjaman->rejected_reason }}</div>
              @endif
            @endif

            @if($peminjaman->pengembalian)
              <div class="col-12"><hr class="my-2"></div>
              <div class="col-md-4 text-muted">Tanggal Kembali</div>
              <div class="col-md-8">{{ optional($peminjaman->pengembalian->tanggal_kembali)->format('Y-m-d') }}</div>
              @php
                $kondisiSet = collect($peminjaman->details)
                  ->pluck('kondisi_kembali')
                  ->filter()
                  ->unique()
                  ->values();
              @endphp
              <div class="col-md-4 text-muted">Kondisi Kembali</div>
              <div class="col-md-8">
                @if($kondisiSet->count() > 0)
                  {{ $kondisiSet->map(fn($k) => ucfirst(str_replace('_',' ', (string) $k)))->implode(', ') }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </div>
              <div class="col-md-4 text-muted">Diterima Oleh</div>
              <div class="col-md-8">{{ optional($peminjaman->pengembalian->penerima)->name ?? '-' }}</div>
              <div class="col-md-4 text-muted">Catatan Pengembalian</div>
              <div class="col-md-8">{{ $peminjaman->pengembalian->catatan_pengembalian ?? '-' }}</div>

              <div class="col-md-4 text-muted">Bukti Foto</div>
              <div class="col-md-8">
                @if($peminjaman->pengembalian->bukti_foto_path)
                  <a href="{{ asset('storage/'.$peminjaman->pengembalian->bukti_foto_path) }}"
                     target="_blank"
                     rel="noopener noreferrer">
                    <img src="{{ asset('storage/'.$peminjaman->pengembalian->bukti_foto_path) }}"
                         alt="Bukti Pengembalian"
                         style="max-width:160px;max-height:160px;object-fit:cover;border-radius:6px;border:1px solid #e9ecef;">
                  </a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-2">Timeline Approval</h6>
          <div class="small">
            <div class="mb-3 pb-2 border-bottom">
              <div class="fw-semibold">1. Screening Rumah Tangga — Pak Singgih</div>
              @php
                $singgihBadgeClass = match ($singgihTl['status']) {
                    'approved' => 'bg-success',
                    'rejected' => 'bg-danger',
                    default    => 'bg-warning text-dark',
                };
              @endphp
              <span class="badge {{ $singgihBadgeClass }} rounded-pill">
                {{ $stageStatusLabel[$singgihTl['status']] ?? ucfirst($singgihTl['status']) }}
              </span>
              @if($singgihTl['at'])
                <div class="text-muted mt-1">{{ $singgihTl['at']->format('Y-m-d H:i') }}</div>
              @endif
              @if($singgihTl['note'])
                <div class="mt-1">{{ $singgihTl['note'] }}</div>
              @endif
            </div>
            <div>
              <div class="fw-semibold">2. Approval Final BMN — Pak Hendri</div>
              <span class="badge {{ $hendriBadgeClass }} rounded-pill">
                {{ $hendriLabel }}
              </span>
              @if($hendriTl['at'])
                <div class="text-muted mt-1">{{ $hendriTl['at']->format('Y-m-d H:i') }}</div>
              @endif
              @if($hendriTl['note'])
                <div class="mt-1">{{ $hendriTl['note'] }}</div>
              @endif
            </div>
          </div>
        </div>
      </div>

      <div class="card border h-100">
        <div class="card-body">
          <h6 class="fw-semibold mb-2">Aksi</h6>

          {{-- Workflow baru: Pak Singgih sebagai verifikator pertama (screening_rumah_tangga + legacy verifikator_1). --}}
          @if($canApproveSinggih)
            <form method="post" action="{{ route('bmn.peminjaman-aset.approve-singgih', $peminjaman->id) }}"
                  class="mb-2" onsubmit="return confirm('Setujui pengajuan ini sebagai Pak Singgih?');">
              @csrf
              <div class="mb-2">
                <label class="form-label small mb-1">Catatan (opsional)</label>
                <textarea name="note" class="form-control form-control-sm" rows="2" maxlength="1000"></textarea>
              </div>
              <button type="submit" class="btn btn-success w-100 btn-sm">
                <i class="ti ti-check"></i> Setujui sebagai Pak Singgih
              </button>
            </form>
          @endif

          @if($canRejectSinggih)
            <form method="post" action="{{ route('bmn.peminjaman-aset.reject-singgih', $peminjaman->id) }}"
                  class="mb-2" onsubmit="return confirmRejectSinggih(this);">
              @csrf
              <input type="hidden" name="rejected_reason" id="rejectedReasonSinggih">
              <button type="submit" class="btn btn-danger w-100 btn-sm">
                <i class="ti ti-x"></i> Tolak (Pak Singgih)
              </button>
            </form>
          @endif

          {{-- Halaman screening detail (assign aset spesifik) tetap tersedia untuk Rumah Tangga
               saat pengajuan masih awaiting screening. --}}
          @if($canScreen)
            <a href="{{ route('bmn.peminjaman-aset.screening', $peminjaman->id) }}" class="btn btn-outline-warning w-100 btn-sm mb-2">
              <i class="ti ti-search"></i> Screening / Cocokkan Aset
            </a>
          @endif

          {{-- Workflow baru: Pak Hendri sebagai verifikator final (final_hendri + legacy verifikator_2/final_report). --}}
          @if($canApproveHendri)
            <form method="post" action="{{ route('bmn.peminjaman-aset.approve-hendri', $peminjaman->id) }}"
                  class="mb-2" onsubmit="return confirm('Setujui peminjaman ini sebagai Pak Hendri (final)?');">
              @csrf
              <div class="mb-2">
                <label class="form-label small mb-1">Catatan (opsional)</label>
                <textarea name="note" class="form-control form-control-sm" rows="2" maxlength="1000"></textarea>
              </div>
              <button type="submit" class="btn btn-success w-100 btn-sm">
                <i class="ti ti-check"></i> Setujui Final Pak Hendri
              </button>
            </form>
          @endif

          @if($canRejectHendri)
            <form method="post" action="{{ route('bmn.peminjaman-aset.reject-hendri', $peminjaman->id) }}"
                  class="mb-2" onsubmit="return confirmRejectHendri(this);">
              @csrf
              <input type="hidden" name="rejected_reason" id="rejectedReasonHendri">
              <button type="submit" class="btn btn-danger w-100 btn-sm">
                <i class="ti ti-x"></i> Tolak (Pak Hendri)
              </button>
            </form>
          @endif

          {{-- Approval LEGACY: hanya untuk transaksi sangat lama yang status-nya pending tetapi
               tidak punya approval_stage multi-tahap. Approver biasa (admin + can_approve_bmn). --}}
          @if($peminjaman->status === 'pending' && $canApproveLegacy)
            <form method="post" action="{{ route('bmn.peminjaman-aset.approve', $peminjaman->id) }}"
                  class="mb-2" onsubmit="return confirm('Setujui peminjaman ini?');">
              @csrf
              <input type="hidden" name="aksi" value="approve">
              <button type="submit" class="btn btn-success w-100 btn-sm">
                <i class="ti ti-check"></i> Approve (Legacy)
              </button>
            </form>
            <form method="post" action="{{ route('bmn.peminjaman-aset.approve', $peminjaman->id) }}"
                  onsubmit="return confirmReject(this);">
              @csrf
              <input type="hidden" name="aksi" value="reject">
              <input type="hidden" name="alasan_reject" id="alasanReject">
              <button type="submit" class="btn btn-danger w-100 btn-sm mb-2">
                <i class="ti ti-x"></i> Reject (Legacy)
              </button>
            </form>
          @endif

          @if($peminjaman->status === 'approved' && $canMarkDipinjam)
            <form method="post" action="{{ route('bmn.peminjaman-aset.mark-dipinjam', $peminjaman->id) }}"
                  onsubmit="return confirm('Tandai sebagai DIPINJAM? Status aset akan dikunci.');">
              @csrf
              <button type="submit" class="btn btn-primary w-100 btn-sm mb-2">
                <i class="ti ti-arrow-right"></i> Tandai Dipinjam
              </button>
            </form>
          @endif

          @if($canCreatePengembalian)
            <a href="{{ route('bmn.pengembalian-aset.create', $peminjaman->id) }}" class="btn btn-warning w-100 btn-sm">
              <i class="ti ti-rotate-2"></i> Catat Pengembalian
            </a>
          @endif

          @if($peminjaman->status === 'pending'
              && !$canApproveSinggih
              && !$canApproveHendri
              && !$canApproveLegacy
              && !$canScreen)
            <p class="text-muted small mb-0">
              <i class="ti ti-info-circle"></i> Menunggu persetujuan verifikator terkait.
            </p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="card border mb-3">
    <div class="card-body">
      <h6 class="fw-semibold mb-2">Daftar Aset ({{ $peminjaman->details->count() }})</h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th width="40">#</th>
              <th width="70">Foto</th>
              <th>Kode Barang</th>
              <th>NUP</th>
              <th>Nama</th>
              <th>Kategori</th>
              <th>Kondisi</th>
              <th>Lokasi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($peminjaman->details as $det)
              @php
                $a = $det->aset;
                $fotoList = (is_array(optional($a)->foto)) ? $a->foto : [];
                $thumb = '';
                if (!empty($fotoList[0])) {
                  $tb = $fotoList[0]['thumb'] ?? $fotoList[0]['full'] ?? '';
                  if ($tb !== '') {
                    $thumb = preg_match('/^https?:\/\//i', $tb) ? $tb : url($tb);
                  }
                }
              @endphp
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                  @if($thumb !== '')
                    <a href="{{ $thumb }}" target="_blank" rel="noopener noreferrer">
                      <img src="{{ $thumb }}" alt="Foto" width="48" height="48"
                           style="object-fit:cover;border-radius:4px;border:1px solid #e9ecef;" loading="lazy">
                    </a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ optional($a)->kode_barang ?? '-' }}</td>
                <td>{{ optional($a)->nup ?? '-' }}</td>
                <td>{{ optional($a)->nama_barang ?? '-' }}</td>
                <td>{{ optional(optional($a)->kategori)->nama_kategori ?? '-' }}</td>
                <td>{{ optional($a)->kondisi ? ucfirst(str_replace('_',' ',$a->kondisi)) : '-' }}</td>
                <td>{{ $a ? $a->labelLokasi() : '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @if((auth()->user()->canApproveBmn() || auth()->user()->hasRole('admin') || auth()->user()->isBmnApprovalParticipant()) && $userHistory->count() > 0)
    <div class="card border mb-3">
      <div class="card-body">
        <h6 class="fw-semibold mb-2">Histori Transaksi Lain Peminjam (10 terbaru)</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Kode</th>
                <th>Status</th>
                <th>Tgl Pinjam</th>
                <th>Rencana Kembali</th>
              </tr>
            </thead>
            <tbody>
              @foreach($userHistory as $h)
                <tr>
                  <td>
                    <a href="{{ route('bmn.peminjaman-aset.show', $h->id) }}">
                      <code>{{ $h->kode_peminjaman }}</code>
                    </a>
                  </td>
                  <td>
                    <span class="badge {{ $statusBadge[$h->status] ?? 'bg-secondary' }} rounded-pill">
                      {{ ucfirst($h->status) }}
                    </span>
                  </td>
                  <td>{{ optional($h->tanggal_pinjam)->format('Y-m-d') }}</td>
                  <td>{{ optional($h->rencana_kembali)->format('Y-m-d') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif
</div>

<script>
  function confirmReject(form) {
    var reason = window.prompt('Alasan penolakan (wajib):', '');
    if (reason === null) return false;
    reason = (reason || '').trim();
    if (reason === '') {
      window.alert('Alasan penolakan wajib diisi.');
      return false;
    }
    form.querySelector('#alasanReject').value = reason;
    return window.confirm('Tolak peminjaman ini?');
  }

  function confirmRejectSinggih(form) {
    var reason = window.prompt('Alasan penolakan oleh Pak Singgih (wajib):', '');
    if (reason === null) return false;
    reason = (reason || '').trim();
    if (reason === '') {
      window.alert('Alasan penolakan wajib diisi.');
      return false;
    }
    form.querySelector('#rejectedReasonSinggih').value = reason;
    return window.confirm('Tolak pengajuan ini?');
  }

  function confirmRejectHendri(form) {
    var reason = window.prompt('Alasan penolakan final oleh Pak Hendri (wajib):', '');
    if (reason === null) return false;
    reason = (reason || '').trim();
    if (reason === '') {
      window.alert('Alasan penolakan wajib diisi.');
      return false;
    }
    form.querySelector('#rejectedReasonHendri').value = reason;
    return window.confirm('Tolak peminjaman pada tahap final?');
  }
</script>
@endsection

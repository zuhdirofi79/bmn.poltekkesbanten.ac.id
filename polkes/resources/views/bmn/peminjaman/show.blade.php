@extends('layouts.thema')

@php
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
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-semibold mb-0">
      <i class="ti ti-clipboard-check"></i> Detail Peminjaman
      <code class="ms-2 small">{{ $peminjaman->kode_peminjaman }}</code>
    </h5>
    <a href="{{ route('bmn.peminjaman-aset.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left"></i> Kembali
    </a>
  </div>

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

            <div class="col-md-4 text-muted">Peminjam</div>
            <div class="col-md-8">
              {{ optional($peminjaman->user)->name ?? '-' }}
              <span class="text-muted">
                ({{ optional($peminjaman->user)->jurusan ? ucfirst(optional($peminjaman->user)->jurusan) : '-' }})
              </span>
            </div>

            <div class="col-md-4 text-muted">Tujuan</div>
            <div class="col-md-8">{{ $peminjaman->tujuan_peminjaman }}</div>

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

            <div class="col-md-4 text-muted">Approver</div>
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
      <div class="card border h-100">
        <div class="card-body">
          <h6 class="fw-semibold mb-2">Aksi</h6>

          @if($peminjaman->status === 'pending' && $canApprove)
            <form method="post" action="{{ route('bmn.peminjaman-aset.approve', $peminjaman->id) }}"
                  class="mb-2" onsubmit="return confirm('Setujui peminjaman ini?');">
              @csrf
              <input type="hidden" name="aksi" value="approve">
              <button type="submit" class="btn btn-success w-100 btn-sm">
                <i class="ti ti-check"></i> Approve
              </button>
            </form>
            <form method="post" action="{{ route('bmn.peminjaman-aset.approve', $peminjaman->id) }}"
                  onsubmit="return confirmReject(this);">
              @csrf
              <input type="hidden" name="aksi" value="reject">
              <input type="hidden" name="alasan_reject" id="alasanReject">
              <button type="submit" class="btn btn-danger w-100 btn-sm">
                <i class="ti ti-x"></i> Reject
              </button>
            </form>
          @endif

          @if($peminjaman->status === 'approved' && $canMarkDipinjam)
            <form method="post" action="{{ route('bmn.peminjaman-aset.mark-dipinjam', $peminjaman->id) }}"
                  onsubmit="return confirm('Tandai sebagai DIPINJAM? Status aset akan dikunci.');">
              @csrf
              <button type="submit" class="btn btn-primary w-100 btn-sm">
                <i class="ti ti-arrow-right"></i> Tandai Dipinjam
              </button>
            </form>
          @endif

          @if($canCreatePengembalian)
            <a href="{{ route('bmn.pengembalian-aset.create', $peminjaman->id) }}" class="btn btn-warning w-100 btn-sm">
              <i class="ti ti-rotate-2"></i> Catat Pengembalian
            </a>
          @endif

          @if($peminjaman->status === 'pending' && !$canApprove)
            <p class="text-muted small mb-0">
              <i class="ti ti-info-circle"></i> Menunggu persetujuan approval officer BMN.
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

  @if(($canApprove || auth()->user()->hasRole('admin')) && $userHistory->count() > 0)
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
</script>
@endsection

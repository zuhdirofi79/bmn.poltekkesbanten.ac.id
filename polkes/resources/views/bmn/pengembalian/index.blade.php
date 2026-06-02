@extends('layouts.bmn_admin')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-semibold mb-0">
      <i class="ti ti-rotate-2"></i> Pengembalian Aset BMN
    </h5>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="card mb-3">
    <div class="card-body">
      <h6 class="fw-semibold mb-2">Belum Dikembalikan
        <small class="text-muted">({{ number_format($belumKembali->total(), 0, ',', '.') }})</small>
      </h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th width="40">#</th>
              <th>Kode</th>
              <th>Peminjam</th>
              <th>Jurusan</th>
              <th>Tgl Pinjam</th>
              <th>Rencana Kembali</th>
              <th>Approver</th>
              <th>Jml Aset</th>
              <th width="170">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($belumKembali as $row)
              @php
                // Admin/approver bisa proses semua; peminjam-owner bisa proses
                // transaksi miliknya sendiri (upload bukti pengembalian).
                $rowCanProcess = $canProcess || ((int) $row->user_id === (int) ($currentUserId ?? 0));
              @endphp
              <tr>
                <td>{{ $belumKembali->firstItem() + $loop->index }}</td>
                <td><code>{{ $row->kode_peminjaman }}</code></td>
                <td>{{ $row->contactName() }}</td>
                <td>{{ optional($row->user)->jurusan ? ucfirst($row->user->jurusan) : ($row->jurusan ? ucfirst($row->jurusan) : '-') }}</td>
                <td>{{ optional($row->tanggal_pinjam)->format('Y-m-d') }}</td>
                <td>{{ optional($row->rencana_kembali)->format('Y-m-d') }}</td>
                <td>{{ optional($row->approver)->name ?? '-' }}</td>
                <td class="text-center">{{ $row->details_count }}</td>
                <td>
                  <a href="{{ route('bmn.peminjaman-aset.show', $row->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-eye"></i>
                  </a>
                  @if($rowCanProcess)
                    <a href="{{ route('bmn.pengembalian-aset.create', $row->id) }}" class="btn btn-sm btn-warning">
                      <i class="ti ti-rotate-2"></i> Kembalikan
                    </a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="text-center text-muted">Tidak ada peminjaman yang belum dikembalikan.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="mt-3 bmn-pagination">
        {{ $belumKembali->appends(request()->query())->links('pagination::bootstrap-5') }}
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h6 class="fw-semibold mb-2">Riwayat Pengembalian
        <small class="text-muted">({{ number_format($riwayat->total(), 0, ',', '.') }})</small>
      </h6>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th width="40">#</th>
              <th>Kode Transaksi</th>
              <th>Peminjam</th>
              <th>Sumber</th>
              <th>Verifikasi Kontak</th>
              <th>Email</th>
              <th>No WA</th>
              <th>Tgl Pinjam</th>
              <th>Tgl Kembali</th>
              <th>Diterima Oleh</th>
              <th>Admin</th>
              <th>Catatan</th>
              <th width="80" class="text-center">Bukti Foto</th>
            </tr>
          </thead>
          <tbody>
            @forelse($riwayat as $row)
              <tr>
                <td>{{ $riwayat->firstItem() + $loop->index }}</td>
                <td>
                  @if($row->peminjaman)
                    <a href="{{ route('bmn.peminjaman-aset.show', $row->peminjaman->id) }}">
                      <code>{{ $row->peminjaman->kode_peminjaman }}</code>
                    </a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ $row->contactName() }}</td>
                <td><span class="badge bg-light text-dark border">{{ $row->sourceLabel() }}</span></td>
                <td>
                  @if($row->isPublicSource())
                    <span class="badge {{ ($row->verification_status ?? '') === 'verified' ? 'bg-success' : 'bg-warning text-dark' }}">
                      {{ $row->verificationStatusLabel() }}
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="small">{{ $row->contactEmail() }}</td>
                <td class="small">{{ $row->contactWa() }}</td>
                <td>{{ optional(optional($row->peminjaman)->tanggal_pinjam)->format('Y-m-d') }}</td>
                <td>{{ optional($row->tanggal_kembali)->format('Y-m-d') }}</td>
                <td>{{ optional($row->penerima)->name ?? ($row->isPublicSource() ? '—' : '-') }}</td>
                <td>
                  @if($canApprove && $row->isPendingAdminVerification())
                    <form method="post" action="{{ route('bmn.pengembalian-aset.verify', $row->id) }}" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success"
                              onclick="return confirm('Verifikasi pengembalian publik ini?');">
                        Verify
                      </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#rejectModal{{ $row->id }}">
                      Reject
                    </button>
                    <div class="modal fade" id="rejectModal{{ $row->id }}" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="post" action="{{ route('bmn.pengembalian-aset.reject', $row->id) }}">
                            @csrf
                            <div class="modal-header">
                              <h6 class="modal-title">Tolak Pengembalian Publik</h6>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <label class="form-label">Alasan</label>
                              <textarea name="admin_verification_note" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                              <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  @elseif($row->admin_status === \App\Models\BMN\PengembalianAsetBmn::ADMIN_STATUS_APPROVED)
                    <span class="badge bg-success">Approved</span>
                  @elseif($row->isPublicSource() && ($row->verification_status ?? '') !== 'verified')
                    <span class="badge bg-warning text-dark" title="Pengaju belum menyelesaikan verifikasi email">Menunggu Verifikasi Email</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ \Illuminate\Support\Str::limit((string) ($row->catatan_pengembalian ?? '-'), 80) }}</td>
                <td class="text-center">
                  @if($row->bukti_foto_path)
                    <a href="{{ asset('storage/'.$row->bukti_foto_path) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       title="Lihat bukti pengembalian">
                      <img src="{{ asset('storage/'.$row->bukti_foto_path) }}"
                           alt="Bukti"
                           style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e9ecef;"
                           loading="lazy">
                    </a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="12" class="text-center text-muted">Belum ada riwayat pengembalian.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="mt-3 bmn-pagination">
        {{ $riwayat->appends(request()->query())->links('pagination::bootstrap-5') }}
      </div>
    </div>
  </div>
</div>
@endsection

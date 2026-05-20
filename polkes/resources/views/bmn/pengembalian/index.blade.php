@extends('layouts.thema')

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
                <td>{{ optional($row->user)->name ?? '-' }}</td>
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
              <th>Tgl Pinjam</th>
              <th>Tgl Kembali</th>
              <th>Diterima Oleh</th>
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
                <td>{{ optional(optional($row->peminjaman)->user)->name ?? '-' }}</td>
                <td>{{ optional(optional($row->peminjaman)->tanggal_pinjam)->format('Y-m-d') }}</td>
                <td>{{ optional($row->tanggal_kembali)->format('Y-m-d') }}</td>
                <td>{{ optional($row->penerima)->name ?? '-' }}</td>
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
              <tr><td colspan="8" class="text-center text-muted">Belum ada riwayat pengembalian.</td></tr>
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

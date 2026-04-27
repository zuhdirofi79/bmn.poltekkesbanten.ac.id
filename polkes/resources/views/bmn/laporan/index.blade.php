@extends('layouts.thema')

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      <h5 class="card-title fw-semibold mb-3">Laporan Daftar Aset BMN</h5>
      <hr>

      <form method="get" action="{{ route('bmn.laporan.index') }}" class="row g-2 mb-4">
        <div class="col-md-2">
          <label class="form-label">Lokasi (master)</label>
          <select name="lokasi_id" class="form-control form-control-sm">
            <option value="">— Semua —</option>
            @foreach($lokasiList as $l)
              <option value="{{ $l->id }}" {{ (string)($filters['lokasi_id'] ?? '') === (string)$l->id ? 'selected' : '' }}>
                {{ $l->nama_lokasi }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Cari lokasi (teks)</label>
          <input type="text" name="lokasi" class="form-control form-control-sm" value="{{ $filters['lokasi'] ?? '' }}" placeholder="Nama / ruang">
        </div>
        <div class="col-md-2">
          <label class="form-label">Kondisi</label>
          <select name="kondisi" class="form-control form-control-sm">
            <option value="">— Semua —</option>
            @foreach(['baik', 'rusak_ringan', 'rusak_berat'] as $k)
              <option value="{{ $k }}" {{ ($filters['kondisi'] ?? '') === $k ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $k)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-control form-control-sm">
            <option value="">— Semua —</option>
            @foreach(['aktif', 'dipindahtangankan'] as $s)
              <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-outline-primary btn-sm me-2">Terapkan</button>
          <a href="{{ route('bmn.laporan.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </form>

      <p class="mb-3">
        <strong>Total nilai perolehan (filter saat ini):</strong>
        Rp {{ number_format((float) $totalNilai, 2, ',', '.') }}
      </p>

      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
          <thead>
            <tr>
              <th width="40">No</th>
              <th>Kode</th>
              <th>Nama Barang</th>
              <th>Kategori</th>
              <th>Nilai Perolehan</th>
              <th>Tgl Perolehan</th>
              <th>Kondisi</th>
              <th>Lokasi</th>
              <th>Status</th>
              <th width="100">Foto</th>
            </tr>
          </thead>
          <tbody>
            @forelse($aset as $row)
              <tr>
                <td>{{ $aset->firstItem() + $loop->index }}</td>
                <td>{{ $row->kode_barang }}</td>
                <td>{{ $row->nama_barang }}</td>
                <td>{{ optional($row->kategori)->nama_kategori ?? '-' }}</td>
                <td class="text-end">{{ $row->nilai_perolehan !== null ? number_format((float) $row->nilai_perolehan, 2, ',', '.') : '-' }}</td>
                <td>{{ optional($row->tanggal_perolehan)->format('Y-m-d') ?? '-' }}</td>
                <td>{{ $row->kondisi ? ucfirst(str_replace('_', ' ', $row->kondisi)) : '-' }}</td>
                <td>{{ $row->labelLokasi() }}</td>
                <td>{{ $row->status ? ucfirst(str_replace('_', ' ', $row->status)) : '-' }}</td>
                <td>
                  @php
                    $fLap = (is_array($row->foto) && $row->foto !== [])
                      ? $row->foto
                      : ((($row->foto_url ?? null) && $row->foto_url !== '') ? [['thumb' => $row->foto_url, 'full' => $row->foto_url]] : []);
                  @endphp
                  @if($fLap !== [])
                    <div style="display:flex;flex-wrap:wrap;gap:4px;max-width:160px;">
                      @foreach($fLap as $im)
                        @php
                          $uv = is_array($im) ? ($im['thumb'] ?? $im['full'] ?? '') : '';
                          $sv = (str_starts_with((string) $uv, 'http://') || str_starts_with((string) $uv, 'https://')) ? $uv : url($uv);
                        @endphp
                        @if($sv !== '')
                          <img src="{{ $sv }}" width="40" height="40" loading="lazy" alt="" style="object-fit:cover;border-radius:4px;">
                        @endif
                      @endforeach
                    </div>
                  @else
                    &mdash;
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="text-center text-muted">Tidak ada data untuk filter ini.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-2">
        {{ $aset->links() }}
      </div>
    </div>
  </div>
</div>
@endsection

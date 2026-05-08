@extends('layouts.thema')

@section('content')
<div class="container-fluid">
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="card-title fw-semibold mb-0">Aset BMN</h5>
        <div class="d-flex flex-wrap gap-2">
          <a href="{{ route('bmn.aset.template') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-download"></i> Unduh template Excel
          </a>
          <a href="{{ route('bmn.aset.create') }}" class="btn btn-primary btn-sm">
            <i class="ti ti-plus"></i> Tambah Aset
          </a>
        </div>
      </div>
      <hr>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif
      @if(session('import_summary') && is_array(session('import_summary')))
        @php($sum = session('import_summary'))
        <div class="alert alert-info py-2 small mb-2">
          Ringkasan: <strong>{{ $sum['imported'] ?? 0 }}</strong> tersimpan;
          <strong>{{ $sum['skipped_count'] ?? 0 }}</strong> baris dilewati.
          @if(!empty($sum['skipped_rows']))
            <span class="text-muted">(baris: {{ implode(', ', $sum['skipped_rows']) }})</span>
          @endif
        </div>
      @endif
      @if(session('import_errors') && is_array(session('import_errors')) && count(session('import_errors')) !== 0)
        <div class="alert {{ session('success') ? 'alert-warning' : 'alert-danger' }}">
          <strong>{{ session('success') ? 'Peringatan import (baris dilewati):' : 'Validasi import:' }}</strong>
          <ul class="mb-0 mt-2 small">
            @foreach(session('import_errors') as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card border mb-4">
        <div class="card-body py-3">
          <h6 class="fw-semibold mb-2">Import multi-item (Excel)</h6>
          <p class="small text-muted mb-2">Gunakan template resmi. Baris valid akan disimpan; baris bermasalah akan dilaporkan dan dilewati.</p>
          <form method="post" action="{{ route('bmn.aset.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-8">
              <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.csv" required>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-outline-primary btn-sm">Unggah & import</button>
            </div>
          </form>
        </div>
      </div>

      <form method="get" action="{{ route('bmn.aset.index') }}" class="row g-2 mb-4">
        <div class="col-md-3">
          <label class="form-label">Lokasi</label>
          <input type="text" name="lokasi" class="form-control form-control-sm" value="{{ $filters['lokasi'] ?? '' }}" placeholder="Cari alamat / ruang (teks)">
        </div>
        <div class="col-md-3">
          <label class="form-label">Satker</label>
          <select name="satker_id" class="form-control form-control-sm">
            <option value="">— Semua —</option>
            @foreach($satkers as $satker)
              <option value="{{ $satker->id }}" {{ (string)($filters['satker_id'] ?? '') === (string)$satker->id ? 'selected' : '' }}>
                {{ $satker->nama }}
              </option>
            @endforeach
          </select>
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
              <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-outline-primary btn-sm me-2">Terapkan</button>
          <a href="{{ route('bmn.aset.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle mb-0">
          <thead>
            <tr>
              <th width="40">No</th>
              <th>Kode Barang</th>
              <th>NUP</th>
              <th>Nama Barang</th>
              <th>Kategori</th>
              <th>Nilai Perolehan</th>
              <th>Tgl Perolehan</th>
              <th>Kondisi</th>
              <th>Lokasi</th>
              <th>Status</th>
              <th>Pengguna</th>
              <th>Satker</th>
              <th width="100">Foto</th>
              <th width="120">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($aset as $row)
              <tr>
                <td>{{ $aset->firstItem() + $loop->index }}</td>
                <td>{{ $row->kode_barang }}</td>
                <td>{{ $row->nup ?? '-' }}</td>
                <td>{{ $row->nama_barang ?? '-' }}</td>
                <td>{{ optional($row->kategori)->nama_kategori ?? '-' }}</td>
                <td class="text-end">{{ $row->nilai_perolehan !== null ? number_format((float) $row->nilai_perolehan, 2, ',', '.') : '-' }}</td>
                <td>{{ optional($row->tanggal_perolehan)->format('Y-m-d') ?? '-' }}</td>
                <td>{{ $row->kondisi ? ucfirst(str_replace('_', ' ', $row->kondisi)) : '-' }}</td>
                <td>{{ $row->labelLokasi() }}</td>
                <td>{{ $row->status ? ucfirst(str_replace('_', ' ', $row->status)) : '-' }}</td>
                <td>{{ $row->pengguna_ruangan ?? '-' }}</td>
                <td>{{ $row->satker ?? '-' }}</td>
                <td>
                  @php
                    $fotoList = [];

                    if (is_array($row->foto) && count($row->foto) !== 0) {
                        $fotoList = $row->foto;
                    } elseif (!empty($row->foto_url)) {
                        $fotoList = [
                            [
                                'thumb' => $row->foto_url,
                                'full' => $row->foto_url,
                            ],
                        ];
                    }
                  @endphp

                  @if(!empty($fotoList))
                    <div style="display:flex;flex-wrap:wrap;gap:5px;max-width:200px;">
                      @foreach($fotoList as $img)
                        @php
                          $thumbUrl = is_array($img) ? ($img['thumb'] ?? $img['full'] ?? null) : null;
                          $fullUrl = is_array($img) ? ($img['full'] ?? $thumbUrl) : $thumbUrl;

                          $src = $thumbUrl
                              ? ((str_starts_with((string) $thumbUrl, 'http://') || str_starts_with((string) $thumbUrl, 'https://')) ? $thumbUrl : url($thumbUrl))
                              : null;

                          $href = $fullUrl
                              ? ((str_starts_with((string) $fullUrl, 'http://') || str_starts_with((string) $fullUrl, 'https://')) ? $fullUrl : url($fullUrl))
                              : null;
                        @endphp

                        @if(!empty($src))
                          <a href="{{ $href }}" target="_blank" rel="noopener noreferrer">
                            <img src="{{ $src }}" width="50" height="50" loading="lazy" alt="Foto aset" style="object-fit:cover;border-radius:4px;border:1px solid #e9ecef;">
                          </a>
                        @endif
                      @endforeach
                    </div>
                  @else
                    &mdash;
                  @endif
                </td>
                <td>
                  <a href="{{ route('bmn.aset.edit', $row) }}" class="btn btn-sm btn-primary"><i class="ti ti-edit"></i></a>
                  <form action="{{ route('bmn.aset.destroy', $row) }}" method="post" class="d-inline" onsubmit="return confirm('Hapus aset dari daftar? Data tetap tersimpan di basis data (penghapusan lunak).');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger"><i class="ti ti-trash"></i></button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="14" class="text-center text-muted">Belum ada data aset.</td>
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

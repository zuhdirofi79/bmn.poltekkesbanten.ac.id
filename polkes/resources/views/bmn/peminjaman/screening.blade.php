@extends('layouts.bmn_admin')

@section('page_title', 'Screening Rumah Tangga')
@section('breadcrumbs')
  <a href="{{ route('bmn.dashboard.index') }}">Dashboard</a> / <a href="{{ route('bmn.peminjaman-aset.index') }}">Peminjaman BMN</a> / Screening / {{ $peminjaman->kode_peminjaman }}
@endsection

@section('content')

  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <div class="row g-3 mb-3">
    <div class="col-lg-5">
      <div class="card border h-100">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">Data Peminjam</h6>
          <div class="small">
            <div class="mb-1"><span class="text-muted">Nama:</span> {{ $peminjaman->nama_peminjam }}</div>
            <div class="mb-1"><span class="text-muted">WA:</span> {{ $peminjaman->wa_peminjam }}</div>
            <div class="mb-1"><span class="text-muted">Email:</span> {{ $peminjaman->email_peminjam }}</div>
            <div class="mb-1"><span class="text-muted">Tujuan:</span> {{ $peminjaman->tujuan_peminjaman }}</div>
            <div class="mb-1"><span class="text-muted">Tgl Pinjam:</span> {{ optional($peminjaman->tanggal_pinjam)->format('Y-m-d') }}</div>
            <div class="mb-3"><span class="text-muted">Rencana Kembali:</span> {{ optional($peminjaman->rencana_kembali)->format('Y-m-d') }}</div>
            <div class="fw-semibold mb-1">Barang yang Diajukan</div>
            <div class="p-2 bg-light rounded border">{{ $peminjaman->requested_barang ?: '-' }}</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card border mb-3">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">Pilih Aset Internal</h6>
          <form method="get" action="{{ route('bmn.peminjaman-aset.screening', $peminjaman->id) }}" class="row g-2 mb-3">
            <div class="col-md-5">
              <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari aset..." value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-md-5">
              <select name="kategori_id" class="form-select form-select-sm">
                <option value="">Semua kategori</option>
                @foreach($kategoriList as $kat)
                  <option value="{{ $kat->id }}" @selected(($filters['kategori_id'] ?? '') == $kat->id)>{{ $kat->nama_kategori }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-outline-primary btn-sm w-100">Filter</button>
            </div>
          </form>

      <form method="post" action="{{ route('bmn.peminjaman-aset.screening.store', $peminjaman->id) }}" enctype="multipart/form-data">
        @csrf
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light sticky-top">
                  <tr>
                    <th width="36"></th>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Kondisi</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($asetList as $aset)
                    <tr>
                      <td><input type="checkbox" name="aset_ids[]" value="{{ $aset->id }}" class="form-check-input"></td>
                      <td><code class="small">{{ $aset->kode_barang }}</code></td>
                      <td>{{ $aset->nama_barang }}</td>
                      <td>{{ optional($aset->kategori)->nama_kategori ?? '-' }}</td>
                      <td>{{ ucfirst(str_replace('_', ' ', $aset->kondisi ?? '-')) }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="5" class="text-center text-muted">Tidak ada aset tersedia.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            <div class="mt-2">{{ $asetList->links() }}</div>
          </div>
        </div>

        <div class="card border mb-3">
          <div class="card-body">
            <h6 class="fw-semibold mb-3">Hasil Screening</h6>
            <div class="mb-3">
              <label class="form-label">Upload Foto Kondisi Aset</label>
              <input type="file" name="kondisi_foto" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="mb-3">
              <label class="form-label">Catatan Kondisi / Screening</label>
              <textarea name="screening_note" rows="2" class="form-control form-control-sm" maxlength="2000">{{ old('screening_note') }}</textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Catatan Kondisi Aset (detail item)</label>
              <textarea name="kondisi_catatan" rows="2" class="form-control form-control-sm" maxlength="2000">{{ old('kondisi_catatan') }}</textarea>
            </div>
            <div class="d-grid gap-2">
              <button type="submit" name="screening_result" value="tersedia" class="btn btn-success btn-sm"
                      onclick="return confirm('Barang tersedia — teruskan ke Pak Hendri?');">
                <i class="ti ti-check"></i> Tersedia — Ajukan ke Tim BMN / Pak Hendri
              </button>
              <button type="submit" name="screening_result" value="perlu_konfirmasi" class="btn btn-warning btn-sm">
                Perlu Konfirmasi
              </button>
              <button type="submit" name="screening_result" value="tidak_tersedia" class="btn btn-danger btn-sm"
                      onclick="return confirm('Tolak karena tidak tersedia?');">
                Tidak Tersedia
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

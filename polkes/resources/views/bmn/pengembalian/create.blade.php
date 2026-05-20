@extends('layouts.thema')

@section('content')
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="fw-semibold mb-0">
      <i class="ti ti-rotate-2"></i> Catat Pengembalian Aset BMN
      <code class="ms-2 small">{{ $peminjaman->kode_peminjaman }}</code>
    </h5>
    <a href="{{ route('bmn.peminjaman-aset.show', $peminjaman->id) }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left"></i> Kembali
    </a>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-md-7">
      <div class="card border h-100">
        <div class="card-body">
          <h6 class="fw-semibold mb-2">Detail Peminjaman</h6>
          <div class="row g-2 small">
            <div class="col-md-4 text-muted">Peminjam</div>
            <div class="col-md-8">
              {{ optional($peminjaman->user)->name ?? '-' }}
              <span class="text-muted">({{ optional($peminjaman->user)->jurusan ? ucfirst(optional($peminjaman->user)->jurusan) : '-' }})</span>
            </div>
            <div class="col-md-4 text-muted">Tujuan</div>
            <div class="col-md-8">{{ $peminjaman->tujuan_peminjaman }}</div>
            <div class="col-md-4 text-muted">Tanggal Pinjam</div>
            <div class="col-md-8">{{ optional($peminjaman->tanggal_pinjam)->format('Y-m-d') }}</div>
            <div class="col-md-4 text-muted">Rencana Kembali</div>
            <div class="col-md-8">{{ optional($peminjaman->rencana_kembali)->format('Y-m-d') }}</div>
            <div class="col-md-4 text-muted">Approver</div>
            <div class="col-md-8">{{ optional($peminjaman->approver)->name ?? '-' }}</div>
          </div>

          <hr>

          <h6 class="fw-semibold mb-2">Aset yang Akan Dikembalikan</h6>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th width="40">#</th>
                  <th width="60">Foto</th>
                  <th>Kode</th>
                  <th>Nama</th>
                  <th>NUP</th>
                  <th>Kondisi Awal</th>
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
                        <img src="{{ $thumb }}" alt="Foto" width="40" height="40"
                             style="object-fit:cover;border-radius:4px;border:1px solid #e9ecef;" loading="lazy">
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td>{{ optional($a)->kode_barang ?? '-' }}</td>
                    <td>{{ optional($a)->nama_barang ?? '-' }}</td>
                    <td>{{ optional($a)->nup ?? '-' }}</td>
                    <td>
                      {{ $det->kondisi_awal
                          ? ucfirst(str_replace('_',' ', (string) $det->kondisi_awal))
                          : (optional($a)->kondisi ? ucfirst(str_replace('_',' ', (string) $a->kondisi)) : '-') }}
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-5">
      <div class="card border h-100">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">Form Pengembalian</h6>
          <form method="post" action="{{ route('bmn.pengembalian-aset.store') }}"
                enctype="multipart/form-data"
                onsubmit="return confirm('Catat pengembalian ini? Status aset akan kembali aktif.');">
            @csrf
            <input type="hidden" name="peminjaman_aset_bmn_id" value="{{ $peminjaman->id }}">

            <div class="mb-3">
              <label class="form-label">Tanggal Kembali <span class="text-danger">*</span></label>
              <input type="date" name="tanggal_kembali" required
                     value="{{ old('tanggal_kembali', now()->toDateString()) }}"
                     min="{{ optional($peminjaman->tanggal_pinjam)->format('Y-m-d') }}"
                     class="form-control form-control-sm">
            </div>

            <div class="mb-3">
              <label class="form-label">Kondisi Kembali <span class="text-danger">*</span></label>
              <select name="kondisi_kembali" required class="form-control form-control-sm">
                @foreach(['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat'] as $val => $label)
                  <option value="{{ $val }}" {{ old('kondisi_kembali', 'baik') === $val ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                @endforeach
              </select>
              <small class="text-muted">
                Jika kondisi berubah, kondisi aset di master akan otomatis diperbarui.
              </small>
            </div>

            <div class="mb-3">
              <label class="form-label">Catatan Pengembalian</label>
              <textarea name="catatan_pengembalian" rows="3" maxlength="2000"
                        class="form-control form-control-sm">{{ old('catatan_pengembalian') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="bukti_foto" class="form-label">Foto Bukti Kondisi Aset</label>
              <input type="file"
                     name="bukti_foto"
                     id="bukti_foto"
                     accept="image/jpeg,image/png,image/jpg,image/webp"
                     class="form-control form-control-sm @error('bukti_foto') is-invalid @enderror">
              <small class="text-muted d-block mt-1">
                Format JPG, JPEG, PNG, WEBP. Maksimal 4 MB. Opsional.
              </small>
              @error('bukti_foto')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <button type="submit" class="btn btn-success btn-sm w-100">
              <i class="ti ti-check"></i> Simpan Pengembalian
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

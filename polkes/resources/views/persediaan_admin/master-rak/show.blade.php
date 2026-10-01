@extends('layouts.persediaan_admin')

@section('page_title', 'Rak ' . $rak->kode_rak)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <a href="{{ route('persediaan.admin.master-rak.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="ti ti-arrow-left"></i> Kembali
  </a>
  <div class="d-flex flex-wrap gap-2">
    <form method="POST" action="{{ route('persediaan.admin.master-rak.generate-qr', $rak->id) }}">
      @csrf
      <button class="btn btn-prs-blue btn-sm"><i class="ti ti-qrcode"></i> Generate QR</button>
    </form>
    <a href="{{ route('persediaan.admin.master-rak.download-qr', $rak->id) }}" class="btn btn-outline-secondary btn-sm">Download QR</a>
    <a href="{{ route('persediaan.admin.master-rak.print', ['ids' => [$rak->id]]) }}" target="_blank" class="btn btn-outline-secondary btn-sm">Print QR</a>
    <form method="POST" action="{{ route('persediaan.admin.master-rak.rotate-token', $rak->id) }}"
          onsubmit="return confirm('Rotasi token membuat QR rak yang sudah dicetak tidak berlaku. Lanjutkan?');">
      @csrf
      <button class="btn btn-outline-danger btn-sm">Rotasi Token QR</button>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="pa-card mt-0">
      <h2>Edit Rak</h2>
      <form method="POST" action="{{ route('persediaan.admin.master-rak.update', $rak->id) }}" class="row g-2 mt-1">
        @csrf @method('PUT')
        <input type="hidden" name="unit_kerja" value="{{ $rak->unit_kerja }}">
        <div class="col-md-3">
          <label class="form-label">Kode</label>
          <input type="text" name="kode_rak" value="{{ $rak->kode_rak }}" class="form-control" required>
        </div>
        <div class="col-md-5">
          <label class="form-label">Nama</label>
          <input type="text" name="nama_rak" value="{{ $rak->nama_rak }}" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="aktif" @selected($rak->status === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected($rak->status === 'nonaktif')>Nonaktif</option>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Lokasi</label>
          <input type="text" name="lokasi_detail" value="{{ $rak->lokasi_detail }}" class="form-control">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button class="btn btn-prs-blue w-100">Simpan Perubahan</button>
        </div>
      </form>
    </div>

    <div class="pa-card">
      <h2>Barang Dalam Rak</h2>
      <div class="table-responsive mt-2">
        <table class="pa-table">
          <thead>
            <tr>
              <th>Kode</th><th>Nama</th><th>Satuan</th>
              <th class="text-end">Stok</th><th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $row)
              <tr>
                <td><code>{{ $row['barang']->kode_barang }}</code></td>
                <td>{{ $row['barang']->nama_barang }}</td>
                <td>{{ $row['barang']->satuan }}</td>
                <td class="text-end">{{ number_format($row['stok'], 0, ',', '.') }}</td>
                <td class="text-end">
                  @if($otherRaks->isNotEmpty())
                    <form method="POST" action="{{ route('persediaan.admin.master-rak.mutasi') }}" class="d-inline-flex gap-1">
                      @csrf
                      <input type="hidden" name="barang_id" value="{{ $row['barang']->id }}">
                      <select name="to_rak_id" class="form-select form-select-sm" style="width:120px;">
                        @foreach($otherRaks as $or)
                          <option value="{{ $or->id }}">{{ $or->kode_rak }}</option>
                        @endforeach
                      </select>
                      <button class="btn btn-outline-primary btn-sm">Pindah</button>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('persediaan.admin.master-rak.barang.destroy', $rak->id) }}" class="d-inline"
                        onsubmit="return confirm('Keluarkan barang dari rak ini?');">
                    @csrf @method('DELETE')
                    <input type="hidden" name="barang_id" value="{{ $row['barang']->id }}">
                    <button class="btn btn-outline-danger btn-sm">Keluarkan</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-3">Belum ada barang di rak ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="pa-card mt-0">
      <h2>QR Rak</h2>
      @if($qrUrl)
        <img src="{{ $qrUrl }}" alt="QR {{ $rak->kode_rak }}" class="img-fluid" style="max-width:220px;border:1px solid #e3e9f2;border-radius:8px;padding:8px;background:#fff;">
      @else
        <p class="text-muted">Belum ada gambar QR. Generate terlebih dahulu.</p>
      @endif
      <div class="small text-break mt-2"><code>{{ $publicUrl }}</code></div>
    </div>

    <div class="pa-card">
      <h2>Tambah Barang Direktorat</h2>
      <form method="GET" class="mb-2">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Cari kode / nama">
      </form>
      <form method="POST" action="{{ route('persediaan.admin.master-rak.barang.store', $rak->id) }}">
        @csrf
        <div style="max-height:320px;overflow:auto;">
          @forelse($candidates as $b)
            <label class="d-flex align-items-center gap-2 py-1 border-bottom">
              <input type="checkbox" name="barang_ids[]" value="{{ $b->id }}"
                     @checked(in_array($b->id, $occupied, true))>
              <span><code>{{ $b->kode_barang }}</code> {{ $b->nama_barang }}</span>
            </label>
          @empty
            <p class="text-muted small mb-0">Tidak ada barang Direktorat yang cocok.</p>
          @endforelse
        </div>
        <button class="btn btn-prs-green btn-sm w-100 mt-2">Simpan ke Rak</button>
      </form>
    </div>
  </div>
</div>
@endsection

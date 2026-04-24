@extends('layouts.thema')

@section('content')

    @include('petugas_lab.lap_menu')

    <div class="col-lg-9">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Laporan Bahan Medis <span class="text-muted fw-normal">(stok aktif)</span></h5>

            <form action="{{ url('petugas/laporan/bahan_bhp') }}" method="get" class="mb-4">
              <input type="hidden" name="jurusan" value="{{ $jurusan }}">
              <label for="status_expired" class="form-label">Status Expired</label>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <select name="status_expired" id="status_expired" class="form-select" style="width:auto; max-width:220px;">
                  <option value="all" {{ ($statusExpired ?? 'all') === 'all' ? 'selected' : '' }}>Semua</option>
                  <option value="expired" {{ ($statusExpired ?? '') === 'expired' ? 'selected' : '' }}>Sudah Expired</option>
                  <option value="not_expired" {{ ($statusExpired ?? '') === 'not_expired' ? 'selected' : '' }}>Belum Expired</option>
                </select>
                <button type="submit" class="btn btn-outline-primary">Filter</button>
              </div>
            </form>

            @if(($statusExpired ?? '') === 'expired')
            <form id="form-bulk-tindak-lanjut" method="post" action="{{ url('petugas/laporan/bahan_bhp/tindak_lanjut_bulk') }}">
              @csrf
              <input type="hidden" name="jurusan" value="{{ $jurusan }}">
              <input type="hidden" name="status_expired" value="expired">
            </form>

            <div class="card border mb-3 bg-light">
              <div class="card-body py-3">
                <span class="fw-semibold mr-2">Aksi massal:</span>
                <label class="sr-only" for="bulk-tindak-select">Tindak lanjut massal</label>
                <select name="tindak_lanjut" id="bulk-tindak-select" class="form-select form-select-sm d-inline-block" style="width:auto; max-width:280px;" form="form-bulk-tindak-lanjut">
                  <option value="">-- Pilih tindak lanjut --</option>
                  <option value="dimusnahkan">dimusnahkan</option>
                  <option value="digunakan kembali">digunakan kembali</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary ml-1" form="form-bulk-tindak-lanjut">Terapkan ke terpilih</button>
                <small class="text-muted d-block mt-2 mb-0">Centang baris di tabel, pilih tindak lanjut, lalu klik terapkan.</small>
              </div>
            </div>

            <table id="lapBahanBhpTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="36"><input type="checkbox" id="lap-bhp-select-all" title="Pilih semua"></th>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Kode Barang</th>
                        <th>Merk</th>
                        <th>Stok</th>
                        <th>tgl Expired</th>
                        <th style="min-width:200px;">Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>
                      <input type="checkbox" name="ids[]" value="{{ $a->id }}" form="form-bulk-tindak-lanjut" class="lap-bhp-row-check">
                    </td>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $a->nama }}</td>
                    <td>{{ $a->kode_barang }}</td>
                    <td>{{ $a->kimia }}</td>
                    <td>{{ $a->stok }} {{ $a->satuan }}</td>
                    <td>{{ $a->tgl_expired }}</td>
                    <td>
                      <form method="post" action="{{ url('petugas/laporan/bahan_bhp/tindak_lanjut') }}" id="form-tindak-{{ $a->id }}" class="mb-0">
                        @csrf
                        <input type="hidden" name="id" value="{{ $a->id }}">
                        <input type="hidden" name="jurusan" value="{{ $jurusan }}">
                        <input type="hidden" name="status_expired" value="expired">
                        <select name="tindak_lanjut" class="form-select form-select-sm lap-bhp-tindak-select" title="Tindak lanjut"
                          onchange="if (this.value) { document.getElementById('form-tindak-{{ $a->id }}').submit(); }">
                          <option value="">Pilih tindak lanjut</option>
                          <option value="dimusnahkan" {{ ($a->tindak_lanjut ?? '') === 'dimusnahkan' ? 'selected' : '' }}>dimusnahkan</option>
                          <option value="digunakan kembali" {{ ($a->tindak_lanjut ?? '') === 'digunakan kembali' ? 'selected' : '' }}>digunakan kembali</option>
                        </select>
                      </form>
                    </td>
                  </tr>
                  @endforeach
                </tbody>
            </table>

            @push('scripts')
            <script>
              (function () {
                var all = document.getElementById('lap-bhp-select-all');
                if (all) {
                  all.addEventListener('change', function () {
                    document.querySelectorAll('.lap-bhp-row-check').forEach(function (cb) {
                      cb.checked = all.checked;
                    });
                  });
                }
                var formBulk = document.getElementById('form-bulk-tindak-lanjut');
                if (formBulk) {
                  formBulk.addEventListener('submit', function (e) {
                    var sel = document.getElementById('bulk-tindak-select');
                    var n = document.querySelectorAll('.lap-bhp-row-check:checked').length;
                    if (!sel || !sel.value) {
                      e.preventDefault();
                      alert('Pilih tindak lanjut untuk aksi massal.');
                      return false;
                    }
                    if (n === 0) {
                      e.preventDefault();
                      alert('Centang minimal satu baris.');
                      return false;
                    }
                  });
                }
              })();
            </script>
            <script>
              $(document).ready(function () {
                if (!document.getElementById('lapBahanBhpTable')) return;
                $('#lapBahanBhpTable').DataTable({
                  dom: 'Bfrtip',
                  order: [[2, 'asc']],
                  pageLength: 25,
                  columnDefs: [
                    { orderable: false, searchable: false, targets: [0, 7] }
                  ],
                  buttons: [
                    {
                      extend: 'excel',
                      exportOptions: {
                        columns: [1, 2, 3, 4, 5, 6, 7],
                        format: {
                          body: function (data, row, column, node) {
                            return window.lapBahanBhpExportCell(node);
                          }
                        }
                      }
                    },
                    {
                      extend: 'pdf',
                      exportOptions: {
                        columns: [1, 2, 3, 4, 5, 6, 7],
                        format: {
                          body: function (data, row, column, node) {
                            return window.lapBahanBhpExportCell(node);
                          }
                        }
                      }
                    },
                    {
                      extend: 'print',
                      exportOptions: {
                        columns: [1, 2, 3, 4, 5, 6, 7],
                        format: {
                          body: function (data, row, column, node) {
                            return window.lapBahanBhpExportCell(node);
                          }
                        }
                      }
                    }
                  ]
                });
              });
              window.lapBahanBhpExportCell = function (node) {
                if (!node) return '';
                var sel = node.querySelector ? node.querySelector('select') : null;
                if (sel) {
                  var v = sel.value;
                  if (!v) return '-';
                  var opt = sel.options[sel.selectedIndex];
                  return opt ? opt.text.trim() : v;
                }
                return node.textContent ? node.textContent.trim() : '';
              };
            </script>
            @endpush

            @else

            <table id="exampleButton" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="25">No</th>
                        <th>Nama</th>
                        <th>Kode Barang</th>
                        <th>Merk</th>
                        <th>Stok</th>
                        <th>tgl Expired</th>
                        <th>Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($data as $a)
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->nama}}</td>
                    <td>{{$a->kode_barang}}</td>
                    <td>{{$a->kimia}}</td>
                    <td>{{$a->stok}} {{$a->satuan}}</td>
                    <td>{{$a->tgl_expired}}</td>
                    <td>{{ $a->tindak_lanjut ?? '-' }}</td>
                  </tr>
                  @endforeach
                </tbody>
            </table>

            @endif
          </div>
        </div>
      </div>
    </div>

@endsection

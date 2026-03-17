@extends('layouts.thema')

@section('content')
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Request Peminjaman Peralatan {{$jurusan}}</h5>
            <table id="myTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th width="30">No</th>
                        <th>Nama Peminjam</th>
                        <th>Jenis Alat</th>
                        <th>Ruang Lab</th>
                        <th>Tingkat</th>
                        <th>Prodi</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th>Tgl Permintaan</th>
                        <th>Status</th>
                        <th>Detail</th>
                        <th>Bukti Foto</th>
                    </tr>
                </thead>
                <tbody>
                  @php $fotos = $fotos ?? collect(); @endphp
                  @foreach($data as $a)
                  @php
                    $photos = $fotos[$a->id] ?? collect();
                  @endphp
                  <tr>
                    <td>{{$loop->iteration}}</td>
                    <td>{{$a->user->name ?? '-'}}</td>
                    <td>{{$a->jenis}}</td>
                    <td>{{$a->ruangan}}</td>
                    <td>{{$a->tingkat}}</td>
                    <td>{{ $a->prodi->nama_prodi ?? '-' }}</td>
                    <td>{{ $a->jam_mulai ?? '-' }}</td>
                    <td>{{ $a->jam_selesai ?? '-' }}</td>
                    <td>{{$a->tgl_pinjaman}}</td>
                    <td><span class="badge bg-dark rounded-3 fw-semibold">{{$a->status}}</span></td>
                    <td><a href="{{url('petugas/request_detail/'.$a->id)}}" target="_blank" class="btn btn-outline-primary btn-sm">Detail</a></td>
                    <td>
                      @if($photos->count() > 0)
                        <button type="button" class="btn btn-info btn-sm btn-view-foto" data-id="{{$a->id}}" data-toggle="modal" data-target="#modalFotoGlobal">View</button>
                      @else
                        -
                      @endif
                    </td>
                  </tr>
                  @endforeach
                </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Single modal for foto preview (filled via AJAX) --}}
      <div class="modal fade" id="modalFotoGlobal" tabindex="-1" aria-labelledby="modalFotoGlobalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalFotoGlobalLabel">Bukti Foto - Peminjaman</h5>
              <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
              <div class="text-center py-3 text-muted" id="modalFotoLoading">Memuat...</div>
              <div class="row" id="modalFotoGrid" style="display:none;"></div>
              <div class="text-center py-3 text-muted" id="modalFotoEmpty" style="display:none;">Tidak ada foto.</div>
            </div>
          </div>
        </div>
      </div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script>
$(function () {
  var baseUrl = "{{ url('petugas/peminjaman-foto') }}";
  var storageUrl = "{{ asset('storage') }}";

  $(document).on('click', '.btn-view-foto', function () {
    var id = $(this).data('id');
    var $modal = $('#modalFotoGlobal');
    var $loading = $('#modalFotoLoading');
    var $grid = $('#modalFotoGrid');
    var $empty = $('#modalFotoEmpty');

    $modal.find('.modal-title').text('Bukti Foto - Peminjaman #' + id);
    $loading.show();
    $grid.hide().empty();
    $empty.hide();

    $.get(baseUrl + '/' + id)
      .done(function (photos) {
        $loading.hide();
        if (photos && photos.length > 0) {
          $.each(photos, function (i, foto) {
            var src = storageUrl + '/' + (foto.foto_path || foto);
            $grid.append('<div class="col-md-3 mb-2"><img src="' + src + '" class="img-fluid img-thumbnail" alt="Bukti foto"></div>');
          });
          $grid.show();
        } else {
          $empty.show();
        }
      })
      .fail(function () {
        $loading.hide();
        $empty.text('Gagal memuat foto.').show();
      });
  });
});
</script>
@endsection

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

      {{-- Modal: URL gambar dari server (foto_url), bukan path server --}}
      <div class="modal fade" id="modalFotoGlobal" tabindex="-1" aria-labelledby="modalFotoGlobalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalFotoGlobalLabel">Bukti Foto - Peminjaman</h5>
              <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">×</button>
            </div>
            <div class="modal-body" id="modalBuktiFoto">
              <div class="text-center py-3 text-muted loading" id="modalFotoLoading">Memuat...</div>
              <div class="row" id="modalFotoGrid" style="display:none;"></div>
              <div class="text-center py-3" id="modalFotoEmpty" style="display:none;">Tidak ada foto.</div>
              <img id="buktiFotoImg" src="" alt="" style="max-width:100%;display:none;">
            </div>
          </div>
        </div>
      </div>

<script>
(function () {
  var baseUrl = "{{ url('petugas/peminjaman-foto') }}";

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.btn-view-foto');
    if (!btn) return;
    var id = btn.getAttribute('data-id');
    var modal = document.getElementById('modalFotoGlobal');
    var loading = document.getElementById('modalFotoLoading');
    var grid = document.getElementById('modalFotoGrid');
    var empty = document.getElementById('modalFotoEmpty');
    var singleImg = document.getElementById('buktiFotoImg');
    var title = modal.querySelector('.modal-title');

    if (title) title.textContent = 'Bukti Foto - Peminjaman #' + id;
    loading.style.display = 'block';
    loading.textContent = 'Memuat...';
    grid.style.display = 'none';
    grid.innerHTML = '';
    empty.style.display = 'none';
    singleImg.style.display = 'none';
    singleImg.src = '';

    fetch(baseUrl + '/' + id, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (photos) {
        loading.style.display = 'none';
        if (!photos || !photos.length) {
          empty.style.display = 'block';
          return;
        }
        if (photos.length === 1 && photos[0].foto_url) {
          singleImg.src = photos[0].foto_url;
          singleImg.style.display = 'block';
          return;
        }
        photos.forEach(function (foto) {
          var url = foto.foto_url || '';
          if (!url) return;
          var col = document.createElement('div');
          col.className = 'col-md-3 mb-2';
          var img = document.createElement('img');
          img.src = url;
          img.className = 'img-fluid img-thumbnail';
          img.alt = 'Bukti foto';
          img.style.maxWidth = '100%';
          col.appendChild(img);
          grid.appendChild(col);
        });
        grid.style.display = 'flex';
        grid.className = 'row';
      })
      .catch(function () {
        loading.style.display = 'block';
        loading.textContent = 'Gagal memuat gambar.';
      });
  });
})();
</script>
@endsection

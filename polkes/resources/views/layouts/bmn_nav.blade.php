@php
  $authUser = auth()->user();
  $isAdmin = $authUser && $authUser->hasRole('admin');
  $isPlp = $authUser && $authUser->hasRole('plp');
  $canRequestBmn = $authUser && method_exists($authUser, 'canRequestBmn') && $authUser->canRequestBmn();
  $canApproveBmn = $authUser && method_exists($authUser, 'canApproveBmn') && $authUser->canApproveBmn();
  // Menu peminjaman/pengembalian terlihat oleh admin, requester (jurusan whitelist), atau approver officer.
  $showPeminjamanMenu = $isAdmin || $canRequestBmn || $canApproveBmn;
  $seg1 = request()->segment(1);
  $seg2 = request()->segment(2);
@endphp

@if ( $isAdmin || $isPlp )
<li class="nav-small-cap">
  <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
  <span class="hide-menu">BMN</span>
</li>
<li class="sidebar-item {{ $seg1 === 'bmn' && $seg2 === 'aset' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.aset.index') }}" aria-expanded="false">
    <span><i class="ti ti-building-warehouse"></i></span>
    <span class="hide-menu">Aset BMN</span>
  </a>
</li>
<li class="sidebar-item {{ $seg1 === 'bmn' && $seg2 === 'mutasi' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.mutasi.index') }}" aria-expanded="false">
    <span><i class="ti ti-arrows-right-left"></i></span>
    <span class="hide-menu">Mutasi Lokasi</span>
  </a>
</li>
<li class="sidebar-item {{ $seg1 === 'bmn' && $seg2 === 'laporan' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.laporan.index') }}" aria-expanded="false">
    <span><i class="ti ti-report-analytics"></i></span>
    <span class="hide-menu">Laporan BMN</span>
  </a>
</li>
@endif

@if ( $showPeminjamanMenu )
<li class="sidebar-item {{ $seg1 === 'bmn' && $seg2 === 'peminjaman-aset' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.peminjaman-aset.index') }}" aria-expanded="false">
    <span><i class="ti ti-clipboard-check"></i></span>
    <span class="hide-menu">Peminjaman Aset BMN</span>
    @if($canApproveBmn)
      @php
        $pendingCount = \App\Models\BMN\PeminjamanAsetBmn::where('status', 'pending')->count();
      @endphp
      @if($pendingCount > 0)
        <span class="badge bg-danger rounded-3 fw-semibold"><small>{{ $pendingCount }}</small></span>
      @endif
    @endif
  </a>
</li>
<li class="sidebar-item {{ $seg1 === 'bmn' && $seg2 === 'pengembalian-aset' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.pengembalian-aset.index') }}" aria-expanded="false">
    <span><i class="ti ti-rotate-2"></i></span>
    <span class="hide-menu">Pengembalian Aset BMN</span>
  </a>
</li>
@endif

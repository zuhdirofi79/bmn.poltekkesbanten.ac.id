@if ( auth()->user()->hasRole('admin') || auth()->user()->hasRole('plp') )
<li class="nav-small-cap">
  <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
  <span class="hide-menu">BMN</span>
</li>
<li class="sidebar-item {{ request()->segment(1) === 'bmn' && request()->segment(2) === 'aset' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.aset.index') }}" aria-expanded="false">
    <span><i class="ti ti-building-warehouse"></i></span>
    <span class="hide-menu">Aset BMN</span>
  </a>
</li>
<li class="sidebar-item {{ request()->segment(1) === 'bmn' && request()->segment(2) === 'mutasi' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.mutasi.index') }}" aria-expanded="false">
    <span><i class="ti ti-arrows-right-left"></i></span>
    <span class="hide-menu">Mutasi Lokasi</span>
  </a>
</li>
<li class="sidebar-item {{ request()->segment(1) === 'bmn' && request()->segment(2) === 'laporan' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.laporan.index') }}" aria-expanded="false">
    <span><i class="ti ti-report-analytics"></i></span>
    <span class="hide-menu">Laporan BMN</span>
  </a>
</li>
@endif

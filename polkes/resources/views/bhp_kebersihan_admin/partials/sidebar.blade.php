@php
  $r = Route::currentRouteName();
  $role = \App\Support\BhpKebersihan\BhpAdminSession::role(request());
  $menus = \App\Support\BhpKebersihan\BhpAccess::allowedMenus($role);
  $has = fn(string $m) => in_array($m, $menus, true) || $role === 'superadmin_bhp';
@endphp

<aside class="pa-sidebar">
  <div class="pa-brand">
    <span class="logo"><i class="ti ti-spray"></i></span>
    <div>
      <b>SIM-BMN</b><br>
      <small>BHP KEBERSIHAN</small>
    </div>
  </div>

  <ul class="pa-nav">
    @if($has('dashboard'))
    <li><a href="{{ route('bhp.admin.dashboard') }}" class="{{ $r === 'bhp.admin.dashboard' ? 'active' : '' }}">
      <i class="ti ti-layout-dashboard"></i> Dashboard</a></li>
    @endif

    @if($has('permintaan'))
    <li><a href="{{ route('bhp.admin.permintaan.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'bhp.admin.permintaan') ? 'active' : '' }}">
      <i class="ti ti-clipboard-list"></i> Permintaan BHP</a></li>
    @endif

    @if($has('pengambilan'))
    <li><a href="{{ route('bhp.admin.pengambilan.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'bhp.admin.pengambilan') ? 'active' : '' }}">
      <i class="ti ti-package-export"></i> Pengambilan Barang</a></li>
    @endif

    @if($has('stok-akhir'))
    <li><a href="{{ route('bhp.admin.stok-akhir.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'bhp.admin.stok-akhir') ? 'active' : '' }}">
      <i class="ti ti-stack-2"></i> Stok Akhir</a></li>
    @endif

    @if($has('opname'))
    <li><a href="{{ route('bhp.admin.opname.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'bhp.admin.opname') ? 'active' : '' }}">
      <i class="ti ti-clipboard-check"></i> Stock Opname</a></li>
    @endif

    @if($has('laporan'))
    <li><a href="{{ route('bhp.admin.laporan.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'bhp.admin.laporan') ? 'active' : '' }}">
      <i class="ti ti-report"></i> Laporan</a></li>
    @endif

    @if($has('master-barang'))
    <li><a href="{{ route('bhp.admin.master-barang.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'bhp.admin.master-barang') ? 'active' : '' }}">
      <i class="ti ti-box"></i> Master Data</a></li>
    @endif

    @if($has('pengaturan'))
    <li><a href="{{ route('bhp.admin.pengaturan.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'bhp.admin.pengaturan') ? 'active' : '' }}">
      <i class="ti ti-settings"></i> Pengaturan</a></li>
    @endif

    <li>
      <form action="{{ route('bhp.admin.logout') }}" method="POST">
        @csrf
        <a href="#" onclick="this.closest('form').submit();return false;"><i class="ti ti-logout"></i> Logout</a>
      </form>
    </li>
  </ul>
</aside>

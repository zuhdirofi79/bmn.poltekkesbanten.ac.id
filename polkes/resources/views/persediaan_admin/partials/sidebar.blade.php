@php $r = Route::currentRouteName(); @endphp

<aside class="pa-sidebar">

  <div class="pa-brand">

    <span class="logo"><i class="ti ti-building-warehouse"></i></span>

    <div>

      <b>SIM-BMN</b><br>

      <small>PERSEDIAAN</small>

    </div>

  </div>



  <ul class="pa-nav">

    <li><a href="{{ route('persediaan.admin.dashboard') }}" class="{{ $r === 'persediaan.admin.dashboard' ? 'active' : '' }}">

      <i class="ti ti-layout-dashboard"></i> Dashboard</a></li>

    <li><a href="{{ route('persediaan.admin.permintaan.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.permintaan') ? 'active' : '' }}">

      <i class="ti ti-clipboard-list"></i> Permintaan</a></li>

    <li><a href="{{ route('persediaan.admin.pembelian.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.pembelian') ? 'active' : '' }}">

      <i class="ti ti-shopping-cart"></i> Pembelian</a></li>

    <li><a href="{{ route('persediaan.admin.stok-akhir.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.stok-akhir') ? 'active' : '' }}">

      <i class="ti ti-stack-2"></i> Stok Akhir</a></li>

    <li><a href="{{ route('persediaan.admin.opname.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.opname') ? 'active' : '' }}">

      <i class="ti ti-clipboard-check"></i> Stock Opname</a></li>

    <li><a href="{{ route('persediaan.admin.laporan.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.laporan') ? 'active' : '' }}">

      <i class="ti ti-report"></i> Laporan</a></li>

    <li><a href="{{ route('persediaan.admin.master-barang.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.master-barang') && ! \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.master-barang.trash') ? 'active' : '' }}">

      <i class="ti ti-box"></i> Master Data</a></li>

    @if(\App\Support\Persediaan\RackAccess::adminCanManage(request()))
    <li><a href="{{ route('persediaan.admin.master-rak.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.master-rak') ? 'active' : '' }}">

      <i class="ti ti-layout-grid"></i> Master Rak</a></li>
    @endif

    <li><a href="{{ route('persediaan.admin.master-barang.trash') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.master-barang.trash') ? 'active' : '' }}">

      <i class="ti ti-trash"></i> Sampah</a></li>

    <li><a href="{{ route('persediaan.admin.pengaturan.index') }}" class="{{ \Illuminate\Support\Str::startsWith($r, 'persediaan.admin.pengaturan') ? 'active' : '' }}">

      <i class="ti ti-settings"></i> Pengaturan</a></li>

    <li>

      <form action="{{ route('persediaan.admin.logout') }}" method="POST">

        @csrf

        <a href="#" onclick="this.closest('form').submit();return false;"><i class="ti ti-logout"></i> Logout</a>

      </form>

    </li>

  </ul>

</aside>


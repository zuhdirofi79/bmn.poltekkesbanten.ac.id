@php
  $authUser = auth()->user();
  $seg1 = request()->segment(1);
  $seg2 = request()->segment(2);
  $routeName = request()->route()?->getName() ?? '';

  $isDashboard = $routeName === 'bmn.dashboard.index';
  $isAsetSection = $seg1 === 'bmn' && in_array($seg2, ['aset', 'mutasi', 'laporan'], true);
  $isPeminjamanSection = $seg1 === 'bmn' && in_array($seg2, ['peminjaman-aset', 'pengembalian-aset'], true);
  $isPenghapusanSection = $seg1 === 'bmn' && $seg2 === 'penghapusan-aset';
  $isPemeliharaanSection = $seg1 === 'bmn' && $seg2 === 'pemeliharaan-aset';
  $isSewaSection = $seg1 === 'bmn' && $seg2 === 'sewa';

  $canApproveBmn = $authUser && method_exists($authUser, 'canApproveBmn') && $authUser->canApproveBmn();
  // Modul Sewa BMN sekarang dipindahkan ke /sewa-admin (login terpisah).
  // Tetap tampilkan link "Sewa BMN" di sidebar BMN hanya untuk user yang
  // memang Admin Sewa (Bu Indah / Bu Retno / Pak Singgih) sebagai shortcut.
  $canManageSewa = $authUser && method_exists($authUser, 'canManageSewaBmn') && $authUser->canManageSewaBmn();
  $showSewaMenu = (bool) $canManageSewa;
@endphp

<aside class="bmn-sidebar" id="bmnSidebar">
  <div class="bmn-sidebar-brand">
    <img src="{{ asset('img/logo.png') }}" alt="Logo Poltekkes">
    <div class="bmn-sidebar-brand-text">
      Sistem Informasi Barang Milik Negara (BMN)
      <small>Poltekkes Kemenkes Banten</small>
    </div>
  </div>

  <nav class="bmn-sidebar-nav">
    <div class="bmn-nav-item {{ $isDashboard ? 'active' : '' }}">
      <a href="{{ route('bmn.dashboard.index') }}" class="bmn-nav-link {{ $isDashboard ? 'active' : '' }}">
        <i class="ti ti-home"></i>
        <span>Dashboard</span>
      </a>
    </div>

    <div class="bmn-nav-item has-sub {{ $isAsetSection ? 'open active' : '' }}">
      <a href="javascript:void(0)" class="bmn-nav-link bmn-sub-toggle">
        <i class="ti ti-database"></i>
        <span>1. Daftar Aset</span>
        <i class="ti ti-chevron-right bmn-chevron"></i>
      </a>
      <ul class="bmn-nav-sub">
        <li><a href="{{ route('bmn.aset.index') }}" class="bmn-nav-link {{ $seg2 === 'aset' ? 'active' : '' }}">Data Aset BMN</a></li>
        <li><a href="{{ route('bmn.mutasi.index') }}" class="bmn-nav-link {{ $seg2 === 'mutasi' ? 'active' : '' }}">Mutasi Lokasi</a></li>
        <li><a href="{{ route('bmn.laporan.index') }}" class="bmn-nav-link {{ $seg2 === 'laporan' ? 'active' : '' }}">Laporan BMN</a></li>
      </ul>
    </div>

    <div class="bmn-nav-item has-sub {{ $isPeminjamanSection ? 'open active' : '' }}">
      <a href="javascript:void(0)" class="bmn-nav-link bmn-sub-toggle">
        <i class="ti ti-clipboard-check"></i>
        <span>2. Peminjaman BMN</span>
        <i class="ti ti-chevron-right bmn-chevron"></i>
      </a>
      <ul class="bmn-nav-sub">
        <li><a href="{{ route('bmn.peminjaman-aset.index') }}" class="bmn-nav-link {{ $seg2 === 'peminjaman-aset' ? 'active' : '' }}">Data Peminjaman</a></li>
        <li><a href="{{ route('bmn.pengembalian-aset.index') }}" class="bmn-nav-link {{ $seg2 === 'pengembalian-aset' ? 'active' : '' }}">Pengembalian Barang</a></li>
      </ul>
    </div>

    <div class="bmn-nav-item has-sub {{ $isPenghapusanSection ? 'open active' : '' }}">
      <a href="javascript:void(0)" class="bmn-nav-link bmn-sub-toggle">
        <i class="ti ti-trash"></i>
        <span>3. Penghapusan BMN</span>
        <i class="ti ti-chevron-right bmn-chevron"></i>
      </a>
      <ul class="bmn-nav-sub">
        <li><a href="{{ route('bmn.penghapusan-aset.index') }}" class="bmn-nav-link {{ $isPenghapusanSection ? 'active' : '' }}">Dashboard Penghapusan</a></li>
      </ul>
    </div>

    <div class="bmn-nav-item has-sub {{ $isPemeliharaanSection ? 'open active' : '' }}">
      <a href="javascript:void(0)" class="bmn-nav-link bmn-sub-toggle">
        <i class="ti ti-tools"></i>
        <span>4. Pemeliharaan BMN</span>
        <i class="ti ti-chevron-right bmn-chevron"></i>
      </a>
      <ul class="bmn-nav-sub">
        <li><a href="{{ route('bmn.pemeliharaan-aset.index') }}" class="bmn-nav-link {{ $isPemeliharaanSection ? 'active' : '' }}">Dashboard Pemeliharaan</a></li>
      </ul>
    </div>

    @if($showSewaMenu)
      <div class="bmn-nav-item {{ $isSewaSection ? 'active' : '' }}">
        <a href="{{ route('sewa-admin.dashboard') }}" class="bmn-nav-link">
          <i class="ti ti-receipt"></i>
          <span>Admin Sewa BMN</span>
        </a>
      </div>
    @endif

    @if($authUser && $authUser->hasRole('admin'))
      <div class="bmn-nav-section">Pengelolaan</div>
      <div class="bmn-nav-item">
        <a href="{{ url('admin/users') }}" class="bmn-nav-link">
          <i class="ti ti-users"></i>
          <span>Manajemen User</span>
        </a>
      </div>
      <div class="bmn-nav-item">
        <a href="{{ url('admin/master_data') }}" class="bmn-nav-link">
          <i class="ti ti-settings"></i>
          <span>Pengaturan</span>
        </a>
      </div>
    @endif
  </nav>

  <div class="bmn-sidebar-footer">
    <strong>Informasi Instansi</strong>
    Poltekkes Kemenkes Banten<br>
    
    Jl. Syech Nawawi Al Bantani No.12, Cipocok Jaya, Kota Serang
    0858-2146-6172
    direktorat@poltekkesbanten.ac.id
    @polkes_banten
    <div class="mt-2 opacity-75">© {{ date('Y') }} Poltekkes Kemenkes Banten</div>
  </div>
</aside>

@php
  $user = auth()->user();
  $foto = $user->foto_profile ?? 'default.png';

  $roleLabel = 'Admin Sewa BMN';
  if ($user) {
    if (method_exists($user, 'canVerifySewaPayment') && $user->canVerifySewaPayment()) {
      $roleLabel = 'Verifikator Pembayaran';
    } elseif (method_exists($user, 'isRetnoSewaCoordinator') && $user->isRetnoSewaCoordinator()) {
      $roleLabel = 'Koordinator Penyewa';
    } elseif (method_exists($user, 'isSinggihSewaCoordinator') && $user->isSinggihSewaCoordinator()) {
      $roleLabel = 'Koordinator Teknis';
    }
  }
@endphp

<header class="bmn-header">
  <div class="bmn-header-left">
    <button type="button" class="bmn-header-toggle" id="bmnSidebarToggle" aria-label="Toggle menu">
      <i class="ti ti-menu-2"></i>
    </button>
    <div class="bmn-header-title">
      <h1>@yield('page_title', 'Admin Sewa BMN')</h1>
      @hasSection('breadcrumbs')
        <p class="bmn-breadcrumb">@yield('breadcrumbs')</p>
      @endif
    </div>
  </div>
  <div class="bmn-header-right">
    <div class="bmn-user-profile dropdown">
      <a href="#" class="d-flex align-items-center gap-2 text-decoration-none text-dark" data-bs-toggle="dropdown">
        <img src="{{ asset('img/profile/'.$foto) }}" alt="Profile" onerror="this.src='{{ asset('img/logo.png') }}'">
        <div class="bmn-user-info">
          <div class="name">{{ $user->name ?? 'Admin Sewa' }}</div>
          <div class="role">{{ $roleLabel }}</div>
        </div>
        <i class="ti ti-chevron-down text-muted" style="font-size:0.75rem;"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li>
          <a class="dropdown-item text-danger" href="#"
             onclick="event.preventDefault(); document.getElementById('sewa-admin-logout-form').submit();">
            <i class="ti ti-logout me-2"></i> Logout
          </a>
        </li>
      </ul>
      <form id="sewa-admin-logout-form" action="{{ route('sewa-admin.logout') }}" method="POST" class="d-none">@csrf</form>
    </div>
  </div>
</header>

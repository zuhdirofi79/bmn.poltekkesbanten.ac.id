@php
  $user = auth()->user();
  $foto = $user->foto_profile ?? 'default.png';
  $notifCount = 0;
  if ($user && method_exists($user, 'canApproveBmn') && ($user->canApproveBmn() || $user->isRumahTanggaBmn() || $user->isBmnApprovalParticipant())) {
    $q = \App\Models\BMN\PeminjamanAsetBmn::query()->where('status', 'pending');
    $notifCount = $q->count();
  }
@endphp

<header class="bmn-header">
  <div class="bmn-header-left">
    <button type="button" class="bmn-header-toggle" id="bmnSidebarToggle" aria-label="Toggle menu">
      <i class="ti ti-menu-2"></i>
    </button>
    <div class="bmn-header-title">
      <h1>@yield('page_title', 'Dashboard BMN')</h1>
      @hasSection('breadcrumbs')
        <p class="bmn-breadcrumb">@yield('breadcrumbs')</p>
      @endif
    </div>
  </div>
  <div class="bmn-header-right">
    <a href="{{ route('bmn.peminjaman-aset.index', ['status' => 'pending']) }}" class="bmn-notif-btn text-decoration-none">
      <i class="ti ti-bell-ringing"></i>
      @if($notifCount > 0)
        <span class="bmn-notif-badge">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>
      @endif
    </a>
    <div class="bmn-user-profile dropdown">
      <a href="#" class="d-flex align-items-center gap-2 text-decoration-none text-dark" data-bs-toggle="dropdown">
        <img src="{{ asset('img/profile/'.$foto) }}" alt="Profile" onerror="this.src='{{ asset('img/logo.png') }}'">
        <div class="bmn-user-info">
          <div class="name">{{ $user->name ?? 'Admin' }}</div>
          <div class="role">Administrator</div>
        </div>
        <i class="ti ti-chevron-down text-muted" style="font-size:0.75rem;"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><a class="dropdown-item" href="{{ url('profile') }}"><i class="ti ti-user me-2"></i> Profil</a></li>
        <li><a class="dropdown-item" href="{{ route('changePassword') }}"><i class="ti ti-lock me-2"></i> Ganti Password</a></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item text-danger" href="{{ route('logout') }}"
             onclick="event.preventDefault(); document.getElementById('bmn-logout-form').submit();">
            <i class="ti ti-logout me-2"></i> Logout
          </a>
        </li>
      </ul>
      <form id="bmn-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
    </div>
  </div>
</header>

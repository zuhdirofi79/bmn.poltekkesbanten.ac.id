@php
  $authUser = auth()->user();
  $isAdmin = $authUser && $authUser->hasRole('admin');
  $isPlp = $authUser && $authUser->hasRole('plp');
  $canRequestBmn = $authUser && method_exists($authUser, 'canRequestBmn') && $authUser->canRequestBmn();
  $canApproveBmn = $authUser && method_exists($authUser, 'canApproveBmn') && $authUser->canApproveBmn();
  $isBmnVerifier = $authUser && method_exists($authUser, 'isBmnApprovalParticipant') && $authUser->isBmnApprovalParticipant();
  $isRumahTangga = $authUser && method_exists($authUser, 'isRumahTanggaBmn') && $authUser->isRumahTanggaBmn();
  // Menu peminjaman/pengembalian terlihat oleh admin, requester (jurusan whitelist), approver officer, verifikator BMN, atau Rumah Tangga.
  $showPeminjamanMenu = $isAdmin || $canRequestBmn || $canApproveBmn || $isBmnVerifier || $isRumahTangga
    || ($authUser && method_exists($authUser, 'isAdminBmnSuperadminUser') && $authUser->isAdminBmnSuperadminUser());
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
    @if($canApproveBmn || $isBmnVerifier || $isRumahTangga)
      @php
        // Workflow baru: Pak Singgih -> Pak Hendri.
        //   - Pak Singgih (id 3240) lihat: screening_rumah_tangga + legacy verifikator_1
        //   - Pak Hendri  (id 3235) lihat: final_hendri + legacy verifikator_2 / final_report
        // Admin lain dengan can_approve_bmn (selain Hendri) tidak diberi badge spesifik.
        $pendingCountQuery = \App\Models\BMN\PeminjamanAsetBmn::query()->where('status', 'pending');
        if ($authUser && $authUser->isBmnVerifier2()) {
          // Pak Singgih = BMN_VERIFIER_2_ID = 3240 (juga termasuk Rumah Tangga IDs)
          $pendingCountQuery->whereIn('approval_stage', [
            \App\Models\BMN\PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA,
            \App\Models\BMN\PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_1,
          ]);
        } elseif ($authUser && $authUser->isBmnVerifier1()) {
          // Pak Hendri = BMN_VERIFIER_1_ID = 3235 (final approver)
          $pendingCountQuery->whereIn('approval_stage', [
            \App\Models\BMN\PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_HENDRI,
            \App\Models\BMN\PeminjamanAsetBmn::APPROVAL_STAGE_VERIFIKATOR_2,
            \App\Models\BMN\PeminjamanAsetBmn::APPROVAL_STAGE_FINAL_REPORT,
          ]);
        } elseif ($authUser && $authUser->isRumahTanggaBmn()) {
          // Rumah Tangga lain (mis. Pak Munawir) — tetap lihat tahap screening.
          $pendingCountQuery->where('approval_stage', \App\Models\BMN\PeminjamanAsetBmn::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA);
        }
        $pendingCount = $pendingCountQuery->count();
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

@if ( $isAdmin || $canApproveBmn )
<li class="sidebar-item {{ $seg1 === 'bmn' && $seg2 === 'sewa' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.sewa.index') }}" aria-expanded="false">
    <span><i class="ti ti-receipt"></i></span>
    <span class="hide-menu">Sewa BMN</span>
  </a>
</li>
<li class="sidebar-item {{ $seg1 === 'bmn' && $seg2 === 'pemeliharaan-aset' ? 'selected' : '' }}">
  <a class="sidebar-link" href="{{ route('bmn.pemeliharaan-aset.index') }}" aria-expanded="false">
    <span><i class="ti ti-tools"></i></span>
    <span class="hide-menu">Pemeliharaan Aset</span>
  </a>
</li>
@endif

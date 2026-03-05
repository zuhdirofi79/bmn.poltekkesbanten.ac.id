<?php 
  $notif_keperawatan = DB::select("select * from peminjaman where jurusan='keperawatan' and status='Menunggu Konfirmasi'");
  $notif_kebidanan = DB::select("select * from peminjaman where jurusan='kebidanan' and status='Menunggu Konfirmasi'");
  $notif_tlm = DB::select("select * from peminjaman where jurusan='tlm' and status='Menunggu Konfirmasi'");
  $notif_all = count($notif_keperawatan)+count($notif_kebidanan)+count($notif_tlm);

  $req_keperawatan = DB::select("select * from req_jadwal_praktikum where jurusan='keperawatan' and status='Menunggu Konfirmasi'");
  $req_kebidanan = DB::select("select * from req_jadwal_praktikum where jurusan='kebidanan' and status='Menunggu Konfirmasi'");
  $req_tlm = DB::select("select * from req_jadwal_praktikum where jurusan='tlm' and status='Menunggu Konfirmasi'");
  $req_all = count($req_keperawatan)+count($req_kebidanan)+count($req_tlm);
?>

    <aside class="left-sidebar">
      <!-- Sidebar scroll-->
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <a href="./index.html" class="text-nowrap logo-img">
            <img src="{{asset('img')}}/fav.png" height="35" alt="" />&nbsp;&nbsp;&nbsp;<strong>POLKES BANTEN</strong>
          </a>
          <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
            <i class="ti ti-x fs-8"></i>
          </div>
        </div>
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
          <ul id="sidebarnav">

            @if ( auth()->user()->hasRole('admin') )
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">Admin</span>
            </li>
            <li class="sidebar-item {{(request()->segment(2) == 'users')? 'selected':'' }}">
              <a class="sidebar-link" href="{{url('admin/users')}}" aria-expanded="false">
                <span>
                  <i class="ti ti-users"></i>
                </span>
                <span class="hide-menu">Users</span>
              </a>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'user_manage' or request()->segment(2) == 'user_edit')? 'selected':'' }}">
              <a class="sidebar-link" href="{{url('admin/user_manage')}}" aria-expanded="false">
                <span>
                  <i class="ti ti-user-search"></i>
                </span>
                <span class="hide-menu">Manage User</span>
              </a>
            </li>

            <li class="sidebar-item {{request()->segment(2) == 'master_data'? 'selected':'' }}">
              <a class="sidebar-link" href="{{url('admin/master_data')}}" aria-expanded="false">
                <span>
                  <i class="ti ti-database"></i>
                </span>
                <span class="hide-menu">Master Data</span>
              </a>
            </li>
            @endif

            @if ( auth()->user()->hasRole('plp') )
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">PLP</span>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'barang' or request()->segment(2) == 'barangbhp')? 'selected':'' }}"> 
                <a class="sidebar-link" data-toggle="collapse" href="#submenu2" aria-expanded="false">
                  <span>
                    <i class="ti ti-report-medical"></i>
                  </span>
                <span class="hide-menu">Daftar Barang</span> 
                </a>
                
                <div id="submenu2" class="collapse">
                    <ul class="nav flex-column pl-3">
                        <li class="nav-item">
                            <a class="sidebar-link sidebar-item" href="{{url('petugas/barang?jurusan=keperawatan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Keperawatan</span> 
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/barang?jurusan=kebidanan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Kebidanan</span> 
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/barang?jurusan=tlm')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">TLM</span> 
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'ruang')? 'selected':'' }}"> 
                <a class="sidebar-link" data-toggle="collapse" href="#submenu3" aria-expanded="false">
                  <span>
                    <i class="ti ti-building-hospital"></i>
                  </span>
                <span class="hide-menu">Jadwal Praktikum</span> 
                </a>
                
                <div id="submenu3" class="collapse">
                    <ul class="nav flex-column pl-3">
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/ruang?jurusan=keperawatan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Keperawatan</span> 
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/ruang?jurusan=kebidanan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Kebidanan</span> 
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/ruang?jurusan=tlm')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">TLM</span> 
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'request')? 'selected':'' }}"> 
                <a class="sidebar-link" data-toggle="collapse" href="#submenu5" aria-expanded="false">
                  <span>
                    <i class="ti ti-checkup-list"></i>
                  </span>
                <span class="hide-menu">Request Peralatan</span> 
                @if($notif_all)
                  <span class="badge bg-danger rounded-3 fw-semibold"><small>{{$notif_all}}</small></span>
                @endif
                </a>
                
                <div id="submenu5" class="collapse">
                    <ul class="nav flex-column pl-3">
                        <li class="nav-item">
                            <a class="sidebar-link sidebar-item" href="{{url('petugas/request?jurusan=keperawatan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Keperawatan</span> 
                              @if($notif_keperawatan)
                                <span class="badge bg-danger rounded-3 fw-semibold"><small>{{count($notif_keperawatan)}}</small></span>
                              @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/request?jurusan=kebidanan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Kebidanan</span> 
                              @if($notif_kebidanan)
                                <span class="badge bg-danger rounded-3 fw-semibold"><small>{{count($notif_kebidanan)}}</small></span>
                              @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/request?jurusan=tlm')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">TLM</span> 
                              @if($notif_tlm)
                                <span class="badge bg-danger rounded-3 fw-semibold"><small>{{count($notif_tlm)}}</small></span>
                              @endif
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'request_jadwal')? 'selected':'' }}"> 
                <a class="sidebar-link" data-toggle="collapse" href="#submenu6" aria-expanded="false">
                  <span>
                    <i class="ti ti-building-bank"></i>
                  </span>
                <span class="hide-menu">Request Jadwal Praktek</span> 
                @if($req_all)
                  <span class="badge bg-danger rounded-3 fw-semibold"><small>{{$req_all}}</small></span>
                @endif
                </a>
                
                <div id="submenu6" class="collapse">
                    <ul class="nav flex-column pl-3">
                        <li class="nav-item">
                            <a class="sidebar-link sidebar-item" href="{{url('petugas/request_jadwal?jurusan=keperawatan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Keperawatan</span> 
                              @if($req_keperawatan)
                                <span class="badge bg-danger rounded-3 fw-semibold"><small>{{count($req_keperawatan)}}</small></span>
                              @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/request_jadwal?jurusan=kebidanan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Kebidanan</span> 
                              @if($req_kebidanan)
                                <span class="badge bg-danger rounded-3 fw-semibold"><small>{{count($req_kebidanan)}}</small></span>
                              @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/request_jadwal?jurusan=tlm')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">TLM</span> 
                              @if($req_tlm)
                                <span class="badge bg-danger rounded-3 fw-semibold"><small>{{count($req_tlm)}}</small></span>
                              @endif
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'Pengembalian')? 'selected':'' }}"> 
                <a class="sidebar-link" data-toggle="collapse" href="#submenu7" aria-expanded="false">
                  <span>
                    <i class="ti ti-arrows-left-right"></i>
                  </span>
                <span class="hide-menu">Pinjaman & Pengembalian</span> 
                </a>
                
                <div id="submenu7" class="collapse">
                    <ul class="nav flex-column pl-3">
                        <li class="nav-item">
                            <a class="sidebar-link sidebar-item" href="{{url('petugas/pengembalian?jurusan=keperawatan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Keperawatan</span> 
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/pengembalian?jurusan=kebidanan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Kebidanan</span> 
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/pengembalian?jurusan=tlm')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">TLM</span> 
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'laporan')? 'selected':'' }}"> 
                <a class="sidebar-link" data-toggle="collapse" href="#submenu4" aria-expanded="false">
                  <span>
                    <i class="ti ti-file-lambda"></i>
                  </span>
                <span class="hide-menu">Laporan</span> 
                </a>
                
                <div id="submenu4" class="collapse">
                    <ul class="nav flex-column pl-3">
                        <li class="nav-item">
                            <a class="sidebar-link sidebar-item" href="{{url('petugas/laporan?jurusan=keperawatan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Keperawatan</span> 
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/laporan?jurusan=kebidanan')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">Kebidanan</span> 
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="sidebar-link" href="{{url('petugas/laporan?jurusan=tlm')}}">
                              <span></span>
                              <span>
                                <i class="ti ti-player-record"></i>
                              </span>
                              <span class="hide-menu">TLM</span> 
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif

            @if ( auth()->user()->hasRole('mahasiswa') or auth()->user()->hasRole('dosen') )
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">User</span>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'daftar_barang')? 'selected':'' }}">
              <a class="sidebar-link" href="{{url('user/peminjaman')}}" aria-expanded="false">
                <span>
                  <i class="ti ti-report-medical"></i>
                </span>
                <span class="hide-menu">Request Peralatan</span>
              </a>
            </li>

            <li class="sidebar-item {{(request()->segment(2) == 'jadwal_praktikum')? 'selected':'' }}">
              <a class="sidebar-link" href="{{url('user/jadwal_praktikum')}}" aria-expanded="false">
                <span>
                  <i class="ti ti-building-hospital"></i>
                </span>
                <span class="hide-menu">Jadwal Praktikum</span>
              </a>
            </li>


            <li class="sidebar-item {{(request()->segment(3) == 'kunjungan')? 'selected':'' }}">
              <a class="sidebar-link" href="{{url('user/kunjungan')}}" aria-expanded="false">
                <span>
                  <i class="ti ti-vaccine"></i>
                </span>
                <span class="hide-menu">Kunjungan Lab</span>
              </a>
            </li>
            @endif

          </ul>
        </nav>
        <!-- End Sidebar navigation -->
      </div>
      <!-- End Sidebar scroll-->
    </aside>
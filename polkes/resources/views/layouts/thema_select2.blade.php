<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 
  <title>{{ENV('APP_NAME')}}</title>
  <link rel="shortcut icon" type="image/png" href="{{asset('img')}}/fav.png" />
  <link rel="stylesheet" href="{{asset('thema')}}/assets/css/styles.min.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.3/css/all.min.css">
  <!-- css untuk select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>

<body>

  <?php 
    $date = date('Y-m-d');
    $notif_harian = DB::select("select * from aktifitas_harian where created_at like '%$date%' order by created_at desc");

  ?>
  <!--  Body Wrapper -->
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
    <!-- Sidebar Start -->
    @include('layouts.thema_nav')
    <!--  Sidebar End -->
    <!--  Main wrapper -->
    <div class="body-wrapper">
      <!--  Header Start -->
      <header class="app-header">
        <nav class="navbar navbar-expand-lg navbar-light">
          <ul class="navbar-nav">
            <li class="nav-item dropdown">
              <a class="nav-link nav-icon-hover" href="javascript:void(0)" data-toggle="modal" data-target="#exampleModalNotif">
                <i class="ti ti-bell-ringing"></i>
                <div class="notification bg-primary rounded-circle"></div>
              </a>
            </li>
          </ul>
          <div class="navbar-collapse justify-content-end px-0" id="navbarNav">
            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end">
              <p class="mt-3">{{auth()->user()->name}}</p>
              <li class="nav-item dropdown">
                <a class="nav-link nav-icon-hover" href="javascript:void(0)" id="drop2" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  <img src="{{asset('img/profile/'.auth()->user()->foto_profile)}}" alt="" width="35" height="35" class="rounded-circle">
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
                  <div class="message-body">
                    <a href="{{url('profile')}}" class="d-flex align-items-center gap-2 dropdown-item">
                      <i class="ti ti-user fs-6"></i>
                      <p class="mb-0 fs-3">My Profile</p>
                    </a>
                    <a href="{{ route('changePassword') }}" class="d-flex align-items-center gap-2 dropdown-item">
                      <i class="ti ti-settings fs-6"></i>
                      <p class="mb-0 fs-3">Change Password</p>
                    </a>
                    <a class="btn btn-outline-primary mx-3 mt-2 d-block" href="{{ route('logout') }}"
                       onclick="event.preventDefault();
                                     document.getElementById('logout-form').submit();">
                        {{ __('Logout') }}
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </nav>
      </header>
      <!--  Header End -->
      <div class="container-fluid row">
        <div class="col-sm-12 col-lg-12">
                 @if (session('message'))
                       <div class='alert alert-success'>
                           {{ session('message') }}
                       </div>
                 @endif
                 @if (session('success'))
                       <div class='alert alert-success'>
                           {{ session('success') }}
                       </div>
                 @endif
                 @if (session('error'))
                        <div class="alert alert-danger">
                        {{ session('error') }}
                        </div>
                 @endif
                 @if (session('danger'))
                       <div class='alert alert-danger'>
                           {{ session('danger') }}
                       </div>
                 @endif
                 @if (count($errors))
                         <div class="alert alert-danger">
                             <ul>
                                 @foreach ($errors->all() as $error)
                                     <li>{{ $error }}</li>
                                 @endforeach
                             </ul>
                         </div>
                 @endif
               </div>
      @yield('content')
      </div>
    </div>
  </div>

  <!-- Modal add request-->
  <div class="modal fade" id="exampleModalNotif" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Aktifitas Hari Ini</h5>
          <button type="button" class="close btn btn-dark btn-sm" data-dismiss="modal" aria-label="Close">x</button>
        </div>
        <div class="modal-body">
          @foreach($notif_harian as $z)
          <b>{{$z->created_at}}</b> User <b>{{$z->username}}</b> dengan nama <b>{{$z->name}}</b> telah melakukan <b>{{$z->aktifitas}}</b>.<hr>
          @endforeach
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="{{asset('thema')}}/assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="{{asset('thema')}}/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="{{asset('thema')}}/assets/js/sidebarmenu.js"></script>
  <script src="{{asset('thema')}}/assets/js/app.min.js"></script>
  <script src="{{asset('thema')}}/assets/libs/apexcharts/dist/apexcharts.min.js"></script>
  <script src="{{asset('thema')}}/assets/libs/simplebar/dist/simplebar.js"></script>
  <script src="{{asset('thema')}}/assets/js/dashboard.js"></script>

  <!-- Option 1: jQuery and Bootstrap Bundle (includes Popper) -->
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous"></script>

  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.js"></script>

  <!-- wajib jquery  -->
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
      integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
      crossorigin="anonymous"></script>
  <!-- js untuk select2  -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> 

  <script type="text/javascript">
    $(document).ready( function () {
        $('#myTable').DataTable();
    } );

    $(document).ready( function () {
        $('#myTable2').DataTable();
    } );

    $("#example1").DataTable({
    "responsive": true, "lengthChange": false, "autoWidth": false,
    "buttons": ["excel", "pdf", "print"]
  }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
  </script>

  <script>
  $(document).ready(function () {
      $("#kelas").select2({
          placeholder: "-- Pilih --"
      });
      $("#tingkat").select2({
          placeholder: "-- Pilih --"
      });
      $("#ruang").select2({
          placeholder: "-- Pilih --"
      });
      $("#hari").select2({
          placeholder: "-- Pilih --"
      });
  });
  </script>

</body>

</html>
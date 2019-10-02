<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title')</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
     
    <!-- Bootstrap 3.3.7 -->
    <link rel="stylesheet" href="{{ URL::asset('/template/bower_components/bootstrap/dist/css/bootstrap.min.css') }}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ URL::asset('/template/bower_components/font-awesome/css/font-awesome.min.css')}}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="{{ URL::asset('/template/bower_components/Ionicons/css/ionicons.min.css')}}">
    <!-- jvectormap -->
    <link rel="stylesheet" href="{{ URL::asset('/template/bower_components/jvectormap/jquery-jvectormap.css')}}">
    <link rel="stylesheet" href="{{ URL::asset('/template/bower_components/bootstrap-daterangepicker/daterangepicker.css')}}">
    <link rel="stylesheet" href="{{ URL::asset('/template/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css')}}">
    <link rel="stylesheet" href="{{ URL::asset('/template/bower_components/select2/dist/css/select2.min.css')}}">
    <link rel="stylesheet" href="{{ URL::asset('/template/plugins/iCheck/skins/all.css')}}">
    
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ URL::asset('/template/dist/css/AdminLTE.min.css')}}">
    <!-- AdminLTE Skins. Choose a skin from the css/skins folder instead of downloading all of them to reduce the load. -->
    <link rel="stylesheet" href="{{ URL::asset('/template/dist/css/skins/_all-skins.min.css')}}">
    <!-- Grid Table -->
    <link rel="stylesheet" href="{{  URL::asset('/vendor/bootstrap-table/dist/bootstrap-table.min.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/bootstrap-table/dist/extensions/filter-control/bootstrap-table-filter-control.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/bootstrap-table/dist/extensions/fixed-columns/bootstrap-table-fixed-columns.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/bootstrap-table/dist/extensions/multiple-select/multiple-select.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/bootstrap-table/dist/extensions/editable/bootstrap-editable.css') }}">
    
    <link rel="stylesheet" href="{{  URL::asset('/base/core.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/fineUploader/fine-uploader-new.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/jquery-confirm/dist/jquery-confirm.min.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/jquery-confirm/dist/jquery-confirm.min.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/bootstrap-toggle/css/bootstrap-toggle.min.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/tooltipster/dist/css/tooltipster.bundle.min.css') }}">
    
    <!--Bootstrap Touchspin -->
    <link rel="stylesheet" href="{{  URL::asset('/vendor/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.min.css') }}">
    <!--Upload-->
		<script src="{{ URL::asset('/vendor/fineUploader/fine-uploader.min.js') }}"></script>
		<!--@End Upload-->
    
    <link rel="stylesheet" href="{{  URL::asset('/css/app') }}{{ $jsCss }}.css">
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Aguafina+Script" />
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Cedarville+Cursive" />
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=League+Script" />
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Meddon" />
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Miss+Fajardose" />
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Miss+Saint+Delafield" />
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Mr+De+Haviland" />
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Pinyon+Script" />
    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Yellowtail" />
  </head>
  <body class="hold-transition skin-blue sidebar-mini fixed <?= isset($_COOKIE['nav']) ? $_COOKIE['nav'] : '' ?>" id="mainBody">
  <!--<body class="hold-transition skin-blue sidebar-mini fixed sidebar-collapse" id="mainBody">-->
    <div class="wrapper">
      <header class="main-header">
        <!-- Logo -->
        <a href="/" class="logo">
          <!-- mini logo for sidebar mini 50x50 pixels -->
          <span class="logo-mini"><b>D</b>W</span>
          <!-- logo for regular state and mobile devices -->
          <span class="logo-lg"><b>Data</b>Workers</span>
        </a>

        <!-- Header Navbar: style can be found in header.less -->
        <nav class="navbar navbar-static-top">
          <!-- Sidebar toggle button-->
          <a href="#" class="sidebar-toggle" id="openNav" data-toggle="push-menu" role="button">
            <span class="sr-only">Toggle navigation</span>
          </a>
          <!-- Navbar Right Menu -->
          <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">
              <!-- User Account: style can be found in dropdown.less -->
              <li class="dropdown user user-menu">
                @yield('account')
              </li>
              <!-- Control Sidebar Toggle Button -->
<!--              <li>
                <a href="#" data-toggle="control-sidebar"><i class="fa fa-gears"></i></a>
              </li>-->
            </ul>
          </div>

        </nav>
      </header>
      <!-- Left side column. contains the logo and sidebar -->
      <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <section class="sidebar">
          <!-- /.search form -->
          <!-- sidebar menu: : style can be found in sidebar.less -->
          <ul class="sidebar-menu" data-widget="tree">
            <!--<li class="header">MAIN NAVIGATION</li>-->
            @yield('nav')
          </ul>
        </section>
        <!-- /.sidebar -->
      </aside>

      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
            @yield('content-header')
          </h1>
          <ol class="breadcrumb">
<!--            <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active">Dashboard</li>-->
          </ol>
        </section>

        <!-- Main content -->
        <section class="content">
          <div class="row">
            <div class="col-xs-12">
              <div id="mainMsg" class="box box-solid" style="display: none;">
                <div class="box-body text-center">Loading ....</div>
                <div class="overlay"><i class="fa fa-refresh fa-spin"></i></div> 
                <div class="overlayCover"><i></i></div> 
              </div>
            </div>
          </div>
          @yield('content')
        </section>
        <!-- /.content -->
      </div>
      <!-- /.content-wrapper -->
<!--      <footer class="main-footer">
        <div class="pull-right hidden-xs">
          <b>Version</b> 2.4.0
        </div>
        <strong>Copyright &copy; 2014-2016 <a href="https://adminlte.io">Almsaeed Studio</a>.</strong> All rights
        reserved.
      </footer>-->

      <!-- Control Sidebar -->
      <aside class="control-sidebar control-sidebar-dark">
        <!-- Create the tabs -->
        <ul class="nav nav-tabs nav-justified control-sidebar-tabs">
          <li><a href="#control-sidebar-home-tab" data-toggle="tab"><i class="fa fa-home"></i></a></li>
          <li><a href="#control-sidebar-settings-tab" data-toggle="tab"><i class="fa fa-gears"></i></a></li>
        </ul>
        <!-- Tab panes -->
      </aside>
      <!-- /.control-sidebar -->
      <!-- Add the sidebar's background. This div must be placed
           immediately after the control sidebar -->
      <div class="control-sidebar-bg"></div>
    </div>
    <!-- ./wrapper -->

    <!-- jQuery 3 -->
    <script src="{{ URL::asset('/template/bower_components/jquery/dist/jquery.min.js') }}"></script>
    <!-- Bootstrap 3.3.7 -->
    <script src="{{ URL::asset('/template/bower_components/bootstrap/dist/js/bootstrap.min.js') }}"></script>
  
    <!-- date-range-picker -->
    <script src="{{ URL::asset('/template/bower_components/moment/min/moment.min.js') }}"></script>
    <script src="{{ URL::asset('/template/bower_components/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
    <!-- bootstrap datepicker -->
    <script src="{{ URL::asset('/template/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
    <!--SELECT 2-->
    <script src="{{ URL::asset('/template/bower_components/select2/dist/js/select2.full.min.js') }}"></script>
    <!--JQUERY SORTABLE-->
    <script src="{{ URL::asset('/template/bower_components/sortablejs/sortable.min.js') }}"></script>
    <script src="{{ URL::asset('/template/bower_components/jquery-sortablejs/jquery-sortable.js') }}"></script>
    <!--ICLICK-->
    <script src="{{ URL::asset('/template/plugins/iCheck/icheck.min.js') }}"></script>
    
    <!-- FastClick -->
    <script src="{{ URL::asset('/template/bower_components/fastclick/lib/fastclick.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ URL::asset('/template/dist/js/adminlte.js') }}"></script>
    <!-- Sparkline -->
    <script src="{{ URL::asset('/template/bower_components/jquery-sparkline/dist/jquery.sparkline.min.js') }}"></script>
    <!-- jvectormap  -->
    <script src="{{ URL::asset('/template/bower_components/jvectormap/jquery-jvectormap.js') }}"></script>
    <!--<script src="{{ URL::asset('/template/bower_components/jvectormap/jquery-jvectormap-world-mill-en.js') }}"></script>-->
    <!-- SlimScroll -->
    <script src="{{ URL::asset('/template/bower_components/jquery-slimscroll/jquery.slimscroll.min.js') }}"></script>
    <!-- ChartJS -->
    <script src="{{ URL::asset('/template/bower_components/chart.js/Chart.js') }}"></script>
    <!-- COOKIE -->
    <script src="{{ URL::asset('/vendor/js.cookie/js.cookie.js') }}"></script>
     
    <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
    <!--<script src="{{ URL::asset('/template/dist/js/pages/dashboard2.js') }}"></script>-->
    <!-- AdminLTE for demo purposes -->
    <script src="{{ URL::asset('/template/dist/js/demo.js') }}"></script>
    
    <!-- Grid Table -->
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/bootstrap-table.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/filter-control/bootstrap-table-filter-control.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/fixed-columns/bootstrap-table-fixed-columns.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/multiple-select/multiple-select.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/export/jsPDF/jspdf.min.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/export/jsPDF-AutoTable/jspdf.plugin.autotable.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/export/bootstrap-table-export.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/export/tableExport.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/lodash.min.js') }}"></script>
    
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/editable/bootstrap-editable.js') }}"></script>
    <script src="{{ URL::asset('/vendor/bootstrap-table/dist/extensions/editable/bootstrap-table-editable.min.js') }}"></script>
    
    <!--Chart JS -->
    <script src="{{ URL::asset('/vendor/chart.js/dist/Chart.bundle.min.js') }}"></script>
    <script src="{{ URL::asset('/vendor/chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js') }}"></script>
    
    <!--Autocomplete OR Typeahead-->
    <script src="{{ URL::asset('/vendor/Autocomplete/dist/jquery.autocomplete.min.js') }}"></script>

    <!-- InputMask -->
    <script src="{{ URL::asset('/template/bower_components/inputmask/dist/min/jquery.inputmask.bundle.min.js') }}"></script>
    
    <!-- JS URL -->
    <script src="{{ URL::asset('/vendor/js-url/url.min.js') }}"></script>
    
    <!--CONFIRM-->
    <script src="{{ URL::asset('/vendor/jquery-confirm/dist/jquery-confirm.min.js') }}"></script>
    
    <!--BOOTSTRAP TOGGLE-->
    <script src="{{ URL::asset('/vendor/bootstrap-toggle/js/bootstrap-toggle.min.js') }}"></script>
    
    <!--TOOLTIP-->
    <script src="{{ URL::asset('/vendor/tooltipster/dist/js/tooltipster.bundle.min.js') }}"></script>
    
    <!--PDF VIEW-->
    <script src="{{ URL::asset('/vendor/PDFObject/pdfobject.js') }}"></script>
    
    <!--PRINT -->
    <script src="{{ URL::asset('/vendor/JqueryPrint/jQuery.print.min.js') }}"></script>
    

    <!-- JSIGNATURE -->
    <script src="{{ URL::asset('/vendor/jSignature/flashcanvas.js') }}"></script>
    <script src="{{ URL::asset('/vendor/jSignature/jSignature.min.js') }}"></script>
            
    <!-- BOOTSTRAP TOUCHSPIN -->
    <script src="{{ URL::asset('/vendor/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.min.js') }}"></script>
    
    <!--NON VENDOR SECTION-->
    <script src="{{ URL::asset('/base/core.js') }}?v=<?= env('APP_VERSION') ?>"></script>
    <script src="{{ URL::asset('/js/lib/Html.js') }}?v=<?= env('APP_VERSION') ?>"></script>
    <script src="{{ URL::asset('/js/lib/Helper.js') }}?v=<?= env('APP_VERSION') ?>"></script>
    <script src="{{ URL::asset('/js/app') }}{{ $jsCss }}.js?v=<?= env('APP_VERSION') ?>"></script>
  </body>
</html>
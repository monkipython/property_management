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
    
    <link rel="stylesheet" href="{{  URL::asset('/base/core.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/fineUploader/fine-uploader-new.css') }}">
    <link rel="stylesheet" href="{{  URL::asset('/vendor/jquery-confirm/dist/jquery-confirm.min.css') }}">
    
    <link rel="stylesheet" href="{{  URL::asset('/vendor/jquery-confirm/dist/jquery-confirm.min.css') }}">
    
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
  </head>
  <body class="hold-transition register-page">
    @yield('content')
    <!-- /.register-box -->
    <!-- jQuery 3 -->
    <script src="{{ URL::asset('/template/bower_components/jquery/dist/jquery.min.js') }}"></script>
    <!-- Bootstrap 3.3.7 -->
    <script src="{{ URL::asset('/template/bower_components/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    <!-- date-range-picker -->
    <script src="{{ URL::asset('/template/bower_components/moment/min/moment.min.js') }}"></script>
    <script src="{{ URL::asset('/template/bower_components/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
    <!--SELECT 2-->
    <script src="{{ URL::asset('/template/bower_components/select2/dist/js/select2.full.min.js') }}"></script>
    <!--ICLICK-->
    <script src="{{ URL::asset('/template/plugins/iCheck/icheck.min.js') }}"></script>
    <!-- InputMask -->
    <script src="{{ URL::asset('/template/bower_components/inputmask/dist/min/jquery.inputmask.bundle.min.js') }}"></script>
    
    <!--NON VENDOR SECTION-->
    <script src="{{ URL::asset('/base/core.js') }}"></script>
    <script src="{{ URL::asset('/js/lib/Html.js') }}"></script>
    <script src="{{ URL::asset('/js/lib/Helper.js') }}"></script>
    <script src="{{ URL::asset('/js/app') }}{{ $jsCss }}.js"></script>
  </body>
</html>
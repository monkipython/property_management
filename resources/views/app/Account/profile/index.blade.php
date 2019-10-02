@extends('template', ['jsCss'=>'/account/profile', 'data'=>$data])

@section('account')
  {!! $data['account'] !!}
@stop

@section('nav')
  {!! $data['nav'] !!}
@stop

@section('title')
  Profile
@stop

@section('content-header')
<i class="fa fa-fw fa-home"></i> {!! $data['name'] !!} Profile
@stop

@section('content')
<div class="row">
  <div class="col-md-5">
    <!-- Profile Image -->
    <div class="box box-primary">
      <div class="box-body box-profile">
        <h3 class="profile-username text-center">{!! $data['name'] !!}</h3>
        <p class="text-muted text-center">{!! $data['occupation'] !!}</p>
      </div>
    </div>
    <!-- /.box -->

    <!-- About Me Box -->
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">About Me</h3>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <strong><i class="fa fa-book margin-r-5"></i> Education</strong>
        <p class="text-muted"><p>{!! $data['education'] !!}</p></p>
        <hr>
        <strong><i class="fa fa-map-marker margin-r-5"></i> Location</strong>
        <p>{!! $data['location'] !!}</p>
        <hr>
        <strong><i class="fa fa-pencil margin-r-5"></i> Skills</strong>
        <p>{!! $data['skill'] !!}</p>
        <hr>
        <strong><i class="fa fa-file-text-o margin-r-5"></i> Notes</strong>
        <p>{!! $data['note'] !!}</p>
      </div>
      <!-- /.box-body -->
    </div>
    <!-- /.box -->
  </div>

  <div class="col-md-7">
    <div class="nav-tabs-custom">
      <ul class="nav nav-tabs">
        <li class="active"><a href="#activity" data-toggle="tab">Settings</a></li>
      </ul>
      <div class="tab-content">
        <form id="applicationForm">
          <div class="active tab-pane" id="activity">{!! $data['form'] !!}</div>
        </form>
      </div>
      <!-- /.tab-content -->
    </div>
    <!-- /.nav-tabs-custom -->
  </div>
  <!-- /.col -->
</div>
@stop
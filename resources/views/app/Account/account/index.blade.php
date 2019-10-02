@extends('template', ['jsCss'=>'/account/account', 'data'=>$data])

@section('account')
  {!! $data['account'] !!}
@stop

@section('nav')
  {!! $data['nav'] !!}
@stop


@section('title')
  Account - Role Management
@stop

@section('content-header')
  <i class="fa fa-fw fa-home"></i> Account - Role Management
@stop

@section('content')
<div class="row">
  <div class="col-xs-12">
    <div class="box">
      <div id="toolbar">
        {!! $data['initData']['button'] !!}
      </div>
      <!--table here-->
      <table id="gridTable" class="gridTable"></table>
    </div>
  </div>
</div>
@stop
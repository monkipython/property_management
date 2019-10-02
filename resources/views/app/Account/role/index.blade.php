@extends('template', ['jsCss'=>'/account/role', 'data'=>$data])

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
      <!--table here-->
      <div id="toolbar">
        <button id="create" class="btn btn-success">
          <i class="fa fa-fw fa-plus-square"></i> New
        </button>
      </div>
      <table id="gridTable" class="gridTable"></table>
    </div>
  </div>
</div>
@stop
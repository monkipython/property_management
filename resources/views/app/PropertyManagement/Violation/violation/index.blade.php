@extends('template', ['jsCss'=>'/PropertyManagement/violation/violation', 'data'=>$data])
@section('account')
  {!! $data['account'] !!}
@stop

@section('nav') 
  {!! $data['nav'] !!}
@stop
@section('title')
  Violation
@stop
 @section('content-header') 
  <i class="fa fa-fw fa-exclamation-triangle"></i> Violation
@stop
@section('content')
<div class="row">
  <div class="col-xs-12">
    <div class="box"> 
      <!--table here--> 
      <div id="toolbar">
        &nbsp;{!! $data['initData']['button'] !!}
      </div>
      <table id="gridTable" class="gridTable"></table>
    </div>
  </div>
</div>
@stop 
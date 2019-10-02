@extends('template', ['jsCss'=>'/PropertyManagement/rentRaise/rentRaise', 'data'=>$data])
@section('account')
  {!! $data['account'] !!}
@stop

@section('nav') 
  {!! $data['nav'] !!}
@stop
@section('title')
  Rent Raise
@stop
@section('content-header') 
  <i class="fa fa-fw fa-home"></i> Rent Raise - View All Rent Increases
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
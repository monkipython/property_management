@extends('template', ['jsCss'=>'/AccountPayable/maintenance/maintenance', 'data'=>$data])
@section('account')
  {!! $data['account'] !!}
@stop

@section('nav') 
  {!! $data['nav'] !!}
@stop
@section('title')
  Maintenance
@stop
 @section('content-header') 
  <i class="fa fa-fw fa-money"></i> Maintenance - View All Maintenances
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
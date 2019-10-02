@extends('template', ['jsCss'=>'/PropertyManagement/tenantMoveOutProcess/tenantMoveOutProcess', 'data'=>$data])
@section('account')
  {!! $data['account'] !!}
@stop

@section('nav') 
  {!! $data['nav'] !!}
@stop
@section('title')
  Tenant Move Out Process
@stop
 @section('content-header') 
  <i class="fa fa-fw fa-recycle"></i> Tenant Move Out Process
@stop
@section('content')
<div class="row">
  <div class="col-xs-12">
    <div class="box"> 
      <!--table here--> 
      <table id="gridTable" class="gridTable"></table>
    </div>
  </div>
</div>
@stop 
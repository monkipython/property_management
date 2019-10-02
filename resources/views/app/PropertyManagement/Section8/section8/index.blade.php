@extends('template',['jsCss'=>'/PropertyManagement/section8/section8','data'=>$data])
@section('account')
	{!! $data['account'] !!}
@stop

@section('nav')
	{!! $data['nav'] !!}
@stop

@section('title')
Section 8 Inspections
@stop

 @section('content-header') 
  <i class="fa fa-fw fa-search"></i> Section 8 - View All Inspections
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
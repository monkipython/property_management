@extends('template',['jsCss'=>'/dashboard/dashboard','data'=>$data])
@section('account'){!! $data['account'] !!}@stop
@section('nav'){!! $data['nav'] !!}@stop
@section('title')
All Charts
@stop

@section('content-header')
	<i class="fa fa-fw fa-home"></i>All Charts
@stop

@section('content')
<div class="row">
  <div class="col-md-12" id="chartList">
    {!! $data['dashboard']['checkList'] !!}
  </div>
  <div class="col-md-12" id="timerContainer">
    {!! $data['dashboard']['timerOptions'] !!}
  </div>
</div>
<div class="row">
  <div class="col-md-12" id="msg"></div>
</div>
<div class="row">
  {!! $data['dashboard']['charts'] !!}
</div>
@stop
@extends('template', ['jsCss'=>'/AccountPayable/propTax/propTax', 'data'=>$data])
@section('account')
  {!! $data['account'] !!}
@stop

@section('nav') 
  {!! $data['nav'] !!}
@stop
@section('title')
  Property Tax
@stop
 @section('content-header') 
  <i class="fa fa-fw fa-home"></i> Property Tax - View All Property Tax
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
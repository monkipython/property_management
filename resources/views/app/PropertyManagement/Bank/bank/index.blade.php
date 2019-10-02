@extends('template', ['jsCss'=>'/PropertyManagement/bank/bank', 'data'=>$data])
@section('account')
  {!! $data['account'] !!}
@stop

@section('nav') 
  {!! $data['nav'] !!}
@stop
@section('title')
  Bank
@stop
 @section('content-header') 
  <i class="fa fa-fw fa-university"></i> Bank - View All Banks
@stop
@section('content')
<div class="row">
  <div class="col-xs-12">
    <div class="box"> 
      <!--table here--> 
      <div id="toolbar">
        &nbsp;{!! $data['initData']['button'] !!}
<!--        <button id="edit" class="btn btn-success">
          <i class="fa fa-fw fa-plus-square"></i> Edit
        </button>
        <button id="compare" class="btn btn-danger">
          <i class="fa fa-fw fa-exchange"></i> Compare
        </button>-->
      </div>
      <table id="gridTable" class="gridTable"></table>
    </div>
  </div>
</div>
@stop 
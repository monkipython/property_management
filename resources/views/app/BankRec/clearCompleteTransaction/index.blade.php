@extends('template', ['jsCss'=>'/BankRec/clearCompleteTransaction/clearCompleteTransaction', 'data'=>$data])
@section('account'){!! $data['account'] !!}@stop
@section('nav'){!! $data['nav'] !!}@stop
@section('title')
  Clear Complete Transaction
@stop

@section('content-header')
  <div class="col-md-3">Clear / Complete Transaction <div class="arrow-container"><span id="arrow-btn" class="fa fa-fw fa-chevron-circle-left"></span></div></div>
@stop

@section('content')
<form id="clearTransactionForm" class="form-horizontal" autocomplete="off">
  <div class="row">
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-3" id="formContainer">
      <div class="row">
        <div class="col-md-12">
            <input id="submit" class="btn btn-info pull-right btn-sm col-md-12 margin-bottom" type="submit" value="Submit">
        </div>
      </div>
      <div class="box-footer">
        {!! $data['form'] !!}
        <div id="reportForm"></div>
      </div>

      <!-- /.box -->
    </div>
    <!-- /.col -->
    <div class="col-md-9" id="gridContainer">
      <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-file-pdf-o"></span> <span id="reportHeader">{!! $data['reportHeader'] !!}</span></h3>
          <div class="box-tools pull-right"></div>
          <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding gridTable">
          <div class="table-responsive mailbox-messages" id="reportBody">
            <!--<h1 id="gridTableDisplay">Please Fill Out The Information And Press Submit To Display The Report Result</h1>-->
            <table id="gridTable"></table>
          </div>
          <!-- /.mail-box-messages -->
        </div>
      </div>
      <!-- /. box -->
    </div>
    <!-- /.col -->
</div>
</form>

@stop
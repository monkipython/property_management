@extends('template', ['jsCss'=>'/AccountReceivable/cashRec', 'data'=>$data])
@section('account'){!! $data['account'] !!}@stop
@section('nav'){!! $data['nav'] !!}@stop
@section('title')
  Account Receivable - Cash Received
@stop

@section('content-header')
<div class="col-md-3"><i class="fa fa-fw fa-credit-card"></i> CASH RECEIVE <div class="arrow-container"><span id="arrow-btn" class="fa fa-fw fa-chevron-circle-left"></span></div></div>
@stop

@section('content')
  <div class="row">
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-3" id="formContainer">
      <form id="applicationForm" class="form-horizontal" autocomplete="off">
        <div class="row">
          <div class="col-md-12">
            <input id="submit" class="btn btn-info pull-right btn-sm col-md-12 margin-bottom" type="submit" value="Submit">
          </div>
        </div>
        <div class="box-footer">
          {!! $data['reportForm'] !!}
          <div id="ledgerCardForm"></div>
          <div id="tenantInfo"></div>
        </div>
      </form>
      <!-- /.box -->
    </div>
    <!-- /.col -->
    <div class="col-md-9" id="gridContainer">
      {!! $data['tab'] !!}
<!--      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-file-pdf-o"></span> <span id="reportHeader">{!! $data['reportHeader'] !!}</span></h3>
          <div class="box-tools pull-right"></div>
           /.box-tools 
        </div>
         /.box-header 
        <div class="box-body no-padding gridTable">
          <div class="table-responsive mailbox-messages" id="reportBody">
            <h1 id="gridTableDisplay">Please Fill Out The Information And Press Submit To Display The Report Result</h1>
            <table id="gridTable"></table>
             
          </div>
           /.mail-box-messages 
        </div>
      </div>-->
      <!-- /. box -->
    </div>
    <div class="col-md-9" id="sideMsgContainer" style="display: none;">
      <div class="arrow-container"><span id="arrow-btn" class="fa fa-fw fa-chevron-circle-left"></span></div>
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-bars"></span> <span id="reportHeader">Information Detail</span></h3>
          <div class="box-tools pull-right"></div>
          <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding gridTable" style="overflow-y: auto;">
          <div id="sideMsg"><h2 class="text-center text-muted" id="initSideMsg"></h2></div>
          <!-- /.mail-box-messages -->
        </div>
      </div>
    </div>
    <!-- /.col -->
</div>

@stop
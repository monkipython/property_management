@extends('template', ['jsCss'=>'/AccountPayable/journalEntry/journalEntry', 'data'=>$data])
@section('account'){!! $data['account'] !!}@stop
@section('nav'){!! $data['nav'] !!}@stop
@section('title')
  Account Payable - Journal Entry
@stop

@section('content-header')
<div class="col-md-3"><i class="fa fa-fw fa-file-text-o"></i> JOURNAL ENTRY </div>
@stop

@section('content')
  <div class="row">
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-8" id="formContainer">
      <form id="journalEntryForm" class="form-horizontal" autocomplete="off">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">
              {!! $data['addMoreIcon'] !!}
              Add Journal Entry
            </h3> 
          </div>
          <div class="box-body with-padding">
            <div class="table-responsive mailbox-messages col-md-12" id="journalEntry">
              {!! $data['journalEntryForm'] !!}
            </div>
          </div>
          
          <div class="box-footer center">
            <input id="submit" class="btn btn-info btn-sm col-sm-4 col-sm-offset-4" type="submit" value="Submit">
          </div>
        </div>
      </form>
    </div>
    <div class="col-md-4" id="sideMsgContainer">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-bars"></span> <span id="reportHeader">Information Detail</span></h3>
          <div class="box-tools pull-right"></div>
        </div>
        <div class="box-body no-padding gridTable" style="overflow-y: auto;">
          <div class="box-body with-border">
            <b>Total Amount: $ <span class="totalAmount text-green">0</span></b>
          </div>
          <div id="sideMsg"><h2 class="text-center text-muted" id="initSideMsg"></h2></div>
        </div>
      </div>
    </div>
    <!-- /.col -->
</div>

@stop
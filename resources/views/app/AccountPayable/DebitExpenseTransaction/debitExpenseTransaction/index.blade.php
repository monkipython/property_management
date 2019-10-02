@extends('template', ['jsCss'=>'/AccountPayable/debitExpenseTransaction/debitExpenseTransaction', 'data'=>$data])
@section('account'){!! $data['account'] !!}@stop
@section('nav'){!! $data['nav'] !!}@stop
@section('title')
  Account Payable - Debit Expense Booking
@stop

@section('content-header')
<div class="col-md-6"><i class="fa fa-fw fa-credit-card"></i> DEBIT / EXPENSE BOOKING </div>
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
          {!! $data['debitExpenseTransactionForm'] !!}
          <div id="debitExpenseTransactionForm"></div>
        </div>
      </form>
    </div>
    <div class="col-md-9" id="sideMsgContainer">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-bars"></span> <span id="reportHeader">Information Detail</span></h3>
          <div class="box-tools pull-right"></div>
        </div>
        <div class="box-body no-padding gridTable" style="overflow-y: auto;">
          <div id="sideMsg">
          </div>
        </div>
      </div>
    </div>
    <!-- /.col -->
</div>

@stop
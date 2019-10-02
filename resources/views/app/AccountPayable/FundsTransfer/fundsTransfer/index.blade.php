@extends('template', ['jsCss'=>'/AccountPayable/fundsTransfer/fundsTransfer', 'data'=>$data])
@section('account'){!! $data['account'] !!}@stop
@section('nav'){!! $data['nav'] !!}@stop
@section('title')
  Account Payable - Funds Transfer
@stop

@section('content-header')
<div class="col-md-3"><i class="fa fa-fw fa-credit-card"></i> FUNDS TRANSFER <div class="arrow-container"></div></div>
@stop

@section('content')
  <div class="row">
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-3" id="formContainer">
      <form id="fundsTransferForm" class="form-horizontal" autocomplete="off">
        <div class="row">
          <div class="col-md-12">
            <input id="submit" class="btn btn-info pull-right btn-sm col-md-12 margin-bottom" type="submit" value="Submit">
          </div>
        </div>
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header">
              <h3 class="box-title"><span class="fa fa-fw fa-money"></span> Funds Transfer From</h3>
            </div>
            {!! $data['form'] !!}
          </div>
        </div>
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header">
              <h3 class="box-title"><span class="fa fa-fw fa-money"></span> Funds Transfer To</h3>
            </div>
           {!! $data['formTo'] !!}
          </div>
        </div>
<!--        <div class="box-footer">
          {!! $data['form'] !!}
        </div>-->
      </form>
      <!-- /.box -->
    </div>
    <!-- /.col -->

    <div class="col-md-9" id="sideMsgContainer">
      <div class="arrow-container"></div>
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-bars"></span> <span id="reportHeader">Information Detail</span></h3>
          <div class="box-tools pull-right"></div>
          <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding gridTable" style="overflow-y: auto;">
          <div id="sideMsg"><br /><br /><h2 class="text-center text-muted" id="initSideMsg">Please Fill Out the Information on the Left and Submit</h2></div>
          <!-- /.mail-box-messages -->
        </div>
      </div>
    </div>
    <!-- /.col -->
</div>

@stop
@extends('template', ['jsCss'=>'/AccountPayable/voidCheck/voidCheck', 'data'=>$data])
@section('account'){!! $data['account'] !!}@stop
@section('nav'){!! $data['nav'] !!}@stop
@section('title')
 Void Check
@stop

@section('content-header')
<div class="col-md-3"><i class="fa fa-fw fa-credit-card"></i> Void Check <div class="arrow-container"><span id="arrow-btn" class="fa fa-fw fa-chevron-circle-left"></span></div></div>
@stop

@section('content')
  <div class="row">
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-3" id="formContainer">
      <form id="voidCheckForm" class="form-horizontal" autocomplete="off">
        <div class="row">
          <div class="col-md-12">
            <input id="submit" class="btn btn-info pull-right btn-sm col-md-12 margin-bottom" type="submit" value="Submit">
          </div>
        </div>
        <div class="box-footer">
          {!! $data['form'] !!}
        </div>
      </form>
    </div>
    <div class="col-md-9" id="gridContainer">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-bars"></span> <span id="reportHeader">Transaction Detail</span></h3>
          <div class="box-tools pull-right"></div>
          <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding gridTable" style="overflow-y: auto;">
          <div class="table-responsive mailbox-messages" id="reportBody" >
            <div id="toolbar"></div>
            <table id="gridTable"></table>
          </div>
          <div class="table-responsive mailbox-messages" id="sideMsgContainer" >
            <div id="sideMsg"></div>
            <h2 class="text-center text-muted">
              <br>
              To View The Transaction, Please Fill out the Information on the Left and Submit. <br><br>
              Void Check function allows user to void checks that were recorded incorrectly. <br><br><br> 
              <span class='alert alert-warning alert-dismissible'>
                <i class="icon fa fa-warning"></i> Please be careful when using this feature.
              </span>
              <br><br><br>
            </h2>
          </div>
          <!-- /.mail-box-messages -->
        </div>
      </div>
      <!-- /. box -->
    </div>
</div>
@stop

<div class="row">
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-6" id="formContainer">
        <form class="form-horizontal" id="gardenHoaApprovalForm" autocomplete="off">
            <div class="box-footer">
              <div>
                {!! $data['submitGardenHoaForm'] !!}
              </div>
            </div>    
        </form>
    </div>
    <div class="col-md-6" id="sideMsgContainer">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-bars"></span> <span id="reportHeader">Information Detail</span></h3>
          <div class="box-tools pull-right"></div>
          <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding gridTable" style="overflow-y: auto;">
          <div class="table-responsive mailbox-messages" id="sideMsg"><h2 class="text-center text-muted" id="initSideMsg"></h2></div>
          <!-- /.mail-box-messages -->
        </div>
      </div>
    </div>
</div>
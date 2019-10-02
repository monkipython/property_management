<div class="row">
  <div class="col-md-12" id="msg"></div>
  <div class="col-md-3">
    <div class="row">
      <div class="col-md-12">
        <form id="applicationForm" class="form-horizontal" autocomplete="off">
          {!! $data['form'] !!}
          <input id="submit" class="btn btn-info pull-right btn-sm col-md-12" type="submit" value="Update">
        </form>
      </div>
    </div>
    <div class="box-footer">
      {!! $data['permissionList'] !!}
    </div>
    <!-- /.box -->
  </div>
  <!-- /.col -->
  <div class="col-md-9">
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">PERMISSION</h3>

        <div class="box-tools pull-right">
          <div class="has-feedback">
            <input type="text" class="form-control input-sm" placeholder="Search Mail">
            <span class="glyphicon glyphicon-search form-control-feedback"></span>
          </div>
        </div>
        <!-- /.box-tools -->
      </div>
      <!-- /.box-header -->
      <div class="box-body no-padding">
        <div class="table-responsive mailbox-messages" id="bodyPermission"></div>
        <!-- /.mail-box-messages -->
      </div>
    </div>
    <!-- /. box -->
  </div>
  <!-- /.col -->
</div>
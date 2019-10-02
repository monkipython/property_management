<form id="depositRefundForm" class="form-horizontal" autocomplete="off">
  {!! $data['tntIdInput'] !!}
  <div class="col-md-12" id="msg"></div>
  <div class="col-md-12">
    <div class="row">
      <div class="col-md-6">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <!-- Horizontal Form -->
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Tenant Information</h3>
            </div>
            {!! $data['tenantInfo'] !!}
          </div>
        </div>
      </div>
      <!-- /.col -->
      <div class="col-md-6">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <!-- Horizontal Form -->
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-map-marker"></span> Address Information</h3>
            </div>
            {!! $data['tenantForm'] !!}
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">
             <!-- <i class="fa fa-fw fa-plus-square text-aqua tip tooltipstered pointer" title="Add More Full Billing Field" id="moreBilling"></i> -->
              ADDITIONAL BILLING
            </h3> 
            <!-- /.box-tools -->
          </div>
          <!-- /.box-header -->
          <div class="box-body no-padding">
            <div class="table-responsive mailbox-messages col-md-12" id="fullBilling">
              {!! $data['fullBillingForm'] !!}
            </div>
          </div>
        </div>
        {!! $data['submitBtn'] !!}
      </div>
    </div>
    <!-- /.col -->
  </div>
</form>
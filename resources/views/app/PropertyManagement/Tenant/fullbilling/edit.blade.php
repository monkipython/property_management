<form id="tenantBillingForm" class="form-horizontal" autocomplete="off">
  <div class="row">
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-3">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <!-- Horizontal Form -->
          <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Tenant Information</h3>
          </div>
          {!! $data['form'] !!}
        </div>
      </div>
    </div>
    <!-- /.col -->
    <div class="col-md-9">
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title">
            {!! $data['moreBillingIcon'] !!}
            FULL BILLING For "{!! $data['tenantName'] !!}"
          </h3> | 
          Rate Per Day: {!! $data['prorateAmount']['proratePerDay'] !!} | 
          <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding">
          <div class="table-responsive mailbox-messages col-md-12" id="fullBilling">
            {!! $data['fullBillingForm'] !!}
          </div>
          <!-- /.mail-box-messages -->
        </div>
      </div>
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title">TENANT ALTERNATIVE ADDRESS</h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding">
          <div class="table-responsive mailbox-messages col-md-12" id="tenantAlternative">
            {!! $data['formTenantAlternative'] !!}
          </div>
          <!-- /.mail-box-messages -->
        </div>
      </div>
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title">
            {!! $data['moreOtherMemberIcon'] !!}
            OTHER MEMBER
          </h3>
          <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding">
          <div class="table-responsive mailbox-messages col-md-12" id="otherMember">
            {!! $data['formOtherMember'] !!}
          </div>
          <!-- /.mail-box-messages -->
        </div>
      </div>
      <!-- /. box -->
    </div>
    <!-- /.col -->
  </div>
</form>
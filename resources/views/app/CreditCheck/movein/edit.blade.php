<form id="applicationForm" class="form-horizontal" autocomplete="off">
<div class="row">
  <div class="col-md-12" id="msg"></div>
  <div class="col-md-3">
    <div class="box box-primary">
      <div class="box-body box-profile">
        <!-- Horizontal Form -->
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Property/Tenant Information</h3>
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
          <i class="fa fa-fw fa-plus-square text-aqua tip tooltipstered pointer" title="Add More Full Billing Field" id="moreBilling"></i>
          <i class="fa fa-fw fa-minus-square text-danger tip tooltipstered pointer" title="Remove Full Billing Field" id="lessBilling"></i>
          FULL BILLING For "{!! $data['tenantName'] !!}"
        </h3> | 
        Rate Per Day: {!! $data['prorateAmount']['proratePerDay'] !!} | 
        <div class="box-tools pull-right">
          <div class="has-feedback">{!! $data['formProrate'] !!}</div>
        </div>
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
          <i class="fa fa-fw fa-plus-square text-aqua tip tooltipstered pointer" title="Add More Other Member Field" id="moreOtherMember"></i>
          <i class="fa fa-fw fa-minus-square text-danger tip tooltipstered pointer" title="Remove Other Member Field" id="lessOtherMember"></i>
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
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"> 
          TENANT DOCUMENTS
        </h3>
        <!-- /.box-tools -->
      </div>
      <!-- /.box-header -->
      <div class="box-body no-padding">
        <div class="table-responsive mailbox-messages row">
          <div class="col-md-6">
            <div class="box">
              <div class="box-header"><h3 class="box-title">Rental Agreement</h3></div>
              <div class="box-body">{!! $data['uploadAgreement'] !!}</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="box">
              <div class="box-header with-border"><h3 class="box-title">Application</h3></div>
              <div class="box-body">{!! $data['uploadCreditCheck'] !!}</div>
            </div>
          </div>
        </div>
        <!-- /.mail-box-messages -->
      </div>
    </div>
    <!-- /. box -->
  </div>
  <!-- /.col -->
</div>
  </form>
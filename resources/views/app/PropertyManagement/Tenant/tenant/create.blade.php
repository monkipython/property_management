<form class="form-horizontal" id="tenantForm"  autocomplete="off"> 
  <div class="row">
    <!--Tenant Information-->
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-address-card-o"></span> Tenant Information</h3>
          </div>
            {!! $data['formTenant'] !!}
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Unit Information</h3>
          </div>
            {!! $data['formUnit'] !!}
        </div>
      </div>
    </div>
  </div>
</form>
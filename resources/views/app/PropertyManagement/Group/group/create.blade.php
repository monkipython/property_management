<form class="form-horizontal" id="groupForm"  autocomplete="off"> 
  <div class="row">
    <div class="col-md-5">
      <div class="box-body">
        <div class="form-group">
          <label class="col-sm-4 control-label">Copy From Group #<span class="text-red">*</span></label>
          <div class="col-sm-8">
            {!! $data['groupOption'] !!}
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <!--Group Information-->
    <div class="col-md-12" id="msg"></div>
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-sitemap"></span> Group Detail</h3>
            </div>
              {!! $data['formGroup'] !!}
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-calculator"></span> Accounting Detail</h3>
            </div>
              {!! $data['formAcc'] !!}
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-users"></span> Management Information</h3>
            </div>
              {!! $data['formMgt'] !!}
          </div>
        </div>
      </div>
  </div>
</form>
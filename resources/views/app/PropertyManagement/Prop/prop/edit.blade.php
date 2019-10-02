<form class="form-horizontal" id="propertyForm"  autocomplete="off"> 
  <div class="row">
    <div class="col-md-5">
      <div class="box-body">
        <div class="box-body">
            {!! $data['propWarning'] !!}
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <!--Property Information-->
    <div class="col-md-12" id="msg"></div>
      <div class="col-md-3">
        <div class="box box-primary">
          <div class="row">
            <div class="col-md-12">
                <div class="box-body box-profile">
                  <div class="box-header with-border">
                    <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Property Detail</h3>
                  </div>
                    {!! $data['formProp'] !!}
                </div>
              </div>
          </div>
          <div class="row">
            <div class="col-md-12">
              <div class="box-body box-profile">
                <div class="box-header with-border">
                  <h3 class="box-title"><span class="fa fa-fw fa-usd"></span> Tax Assessment</h3>
                </div>
                  {!! $data['formTax'] !!}
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-calculator"></span> Accounting Detail</h3>
            </div>
              {!! $data['formAcc'] !!}
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-users"></span> Management Information</h3>
            </div>
              {!! $data['formMgt1'] !!}
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-users"></span> Management Information -Continue</h3>
            </div>
              {!! $data['formMgt2'] !!}
          </div>
        </div>
      </div>
  </div>
</form>
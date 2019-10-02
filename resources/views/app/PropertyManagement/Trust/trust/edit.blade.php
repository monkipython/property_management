<form class="form-horizontal" id="trustForm"  autocomplete="off"> 
  <div class="row">
    <!--Trust Information-->
    <div class="col-md-12" id="msg"></div>
      <div class="col-md-4">
        <div class="row">
          <div class="col-md-12">
            <div class="box box-primary">
              <div class="box-body box-profile">
                <div class="box-header with-border">
                  <h3 class="box-title"><span class="fa fa-fw fa-home"></span>Trust Detail</h3>
                </div>
                  {!! $data['formTrust'] !!}
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-calculator"></span>Accounting Detail</h3>
            </div>
              {!! $data['formAcc'] !!}
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-users"></span>Management Information</h3>
            </div>
              {!! $data['formMgt'] !!}
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">
            <div class="box box-primary">
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
  </div>
</form>
<form class="form-horizontal" id="insuranceForm"  autocomplete="off"> 
  <div class="row">
    <div class="col-md-12">
      <!--Insurance Information-->
      <div class="col-md-12" id="msg"></div>
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-shield"></span> Insurance Detail</h3>
            </div>
              {!! $data['formInsurance'] !!}
          </div>
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-calendar-plus-o"></span> Monthly Payment</h3>
            </div>
              {!! $data['formMonthlyPayment'] !!}
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-shield"></span> Insurance Detail (Cont'd)</h3>
            </div>
              {!! $data['formInsurance2'] !!}
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <!-- Horizontal Form -->
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-cloud-upload"></span> Upload PDF</h3>
            </div>
            <div class="row">
              <div class="col-md-12">
                {!! $data['upload']['container'] !!}
                {!! $data['upload']['hiddenForm'] !!}
              </div>
            </div>
            {!! $data['fileUploadList'] !!}
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
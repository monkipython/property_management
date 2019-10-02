<form class="form-horizontal" id="glChartForm"  autocomplete="off"> 
  <div class="row">
    <!--GL Chart Information-->
    <div class="col-md-12" id="msg"></div>
      <div class="col-md-12">
        <div class="box box-primary">
          <div class="row">
            <div class="box-body box-profile">
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-home"></span>General Ledger Account Detail</h3>
              </div>
                {!! $data['formGlChart'] !!}
            </div>
          </div>
        </div>
      </div>
  </div>
</form>
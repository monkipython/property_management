<form class="form-horizontal" id="bankForm"  autocomplete="off"> 
  <div class="row">
    <!--Bank Information-->
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-university"></span> Bank Detail</h3>
          </div>
            {!! $data['formBank'] !!}
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-calculator"></span> Accounting Detail</h3>
          </div>
            {!! $data['formAcc'] !!}
        </div>
      </div>
    </div>
  </div>
  </div>
</form>
<form class="form-horizontal" id="serviceForm"  autocomplete="off"> 
  <div class="row">
    <!--Service Information-->
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-12">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-gears"></span> Service Detail</h3>
          </div>
            {!! $data['formService'] !!}
        </div>
      </div>
    </div>
  </div>
</form>
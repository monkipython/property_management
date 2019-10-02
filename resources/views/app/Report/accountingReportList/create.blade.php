<form class="form-horizontal" id="reportListForm"  autocomplete="off"> 
  <div id="accTypeData">
  {!! $data['accTypeData'] !!}
  </div>
  <div class="row">
    <!--Report List Information-->
    <div class="col-md-12" id="msg"></div>
      <div class="col-md-12"> 
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-list"></span> Report List Detail</h3>
            </div>
              {!! $data['form'] !!}
          </div>
      </div>
    </div>
  </div>
</form>
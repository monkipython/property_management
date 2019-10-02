<form class="form-horizontal" id="unitForm" autocomplete="off">
    <div class="row">
        <div class="col-md-12" id="msg"></div>
        <div class="col-md-6">
          <div class="box box-primary">
            <div class="box-body box-profile">
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Unit Information</h3>
              </div>
                {!! $data['formUnit1'] !!}
            </div>
          </div>
        </div>  
        <div class="col-md-6">
          <div class="panel box box-primary">
            <div class="box-body box-profile">
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Building Information</h3>
              </div>
                {!! $data['formUnit2'] !!}
            </div>
          </div>
        </div>
    </div>  
</form>






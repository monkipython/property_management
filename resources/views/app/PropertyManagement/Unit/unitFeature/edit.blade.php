<form class="form-horizontal" id="unitFeatureForm" autocomplete="off">
  <div class="row">
    <!--Property Information-->
    <div class="col-md-12" id="msg"></div>
      <div class="col-md-6">
          <div class="box box-primary">
            <div class="box-body box-profile">
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-home"></span>General Details</h3>
              </div>
                {!! $data['formUnitFeature1'] !!}
            </div>
          </div>
      </div>
      <div class="col-md-6">
          <div class="panel box box-primary">
            <div class="box-body box-profile">
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-home"></span>Furnishing Details</h3>
              </div>
                {!! $data['formUnitFeature2'] !!}
            </div>
        </div>
      </div>
  </div>
</form>





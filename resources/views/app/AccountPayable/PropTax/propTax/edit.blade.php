<form class="form-horizontal" id="propTaxForm"  autocomplete="off"> 
  <div class="row">
    <div class="col-md-12">
      <!--Prop Tax Information-->
      <div class="col-md-12" id="msg"></div>
      <div class="col-md-6">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Property Tax Detail</h3>
            </div>
              {!! $data['formPropTax'] !!}
          </div>
        </div>
      </div>
      <div class="col-md-6">
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
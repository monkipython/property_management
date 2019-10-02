<form class="form-horizontal" id="gardenHoaForm"  autocomplete="off"> 
  <div class="row">
    <div class="col-md-12">
      <!--Pending Check Information-->
      <div class="col-md-12" id="msg"></div>
      <div class="col-md-3">
        <div class="row">
          <div class="box box-primary">
            <div class="box-body box-profile">
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-money"></span> Garden HOA Detail</h3>
              </div>
                {!! $data['formGardenHoa'] !!}
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-9" id="uploadContainer">
        <div class="box box-primary">
          <div class="box-body box-profile">
            <!-- Horizontal Form -->
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-cloud-upload"></span> Upload PDF or Image</h3>
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
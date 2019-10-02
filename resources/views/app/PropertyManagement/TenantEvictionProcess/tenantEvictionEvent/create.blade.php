<form class="form-horizontal" id="tntEvictionEventForm"  autocomplete="off"> 
  <div class="row">
    <!--Tenant Information-->
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-6">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-etsy"></span> Tenant Eviction Event Information</h3>
          </div>
            {!! $data['formEvictionEvent'] !!}
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
          <div class="row">
            <div class="col-md-3">
              <ul class="nav nav-pills nav-stacked" id="uploadList"></ul>
            </div>
            <div class="col-md-9" id="uploadView"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
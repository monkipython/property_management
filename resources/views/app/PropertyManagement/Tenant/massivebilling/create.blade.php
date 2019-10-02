<form id="massiveForm" class="form-horizontal" autocomplete="off">
  <div class="row">
    <!--Massive Property Edit Information-->
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-12">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <div class="box-header with-border">
            <h3 class="box-title">You're going to generate Tenant's Massive billing.<br><br>
              <span class="text-yellow"><span class="fa fa-fw  fa-exclamation-triangle"></span>Please Note: This process can take up to 10 minutes.</span>
            </h3>
          </div>
            {!! $data['massiveForm'] !!}
        </div>
      </div>
    </div>
  </div>
</form>
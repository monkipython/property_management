<form class="form-horizontal" id="violationForm"  autocomplete="off"> 
  <div class="row">
    <!--Violation Information-->
    <div class="col-md-12" id="msg"></div>
      <div class="col-md-12"> 
        <div class="box box-primary">
          <div class="box-body box-profile">
            <div class="box-header with-border">
              <h3 class="box-title"><span class="fa fa-fw fa-exclamation-triangle"></span> Violation Detail</h3>
            </div>
              {!! $data['form'] !!}
          </div>
      </div>
    </div>
  </div>
</form>
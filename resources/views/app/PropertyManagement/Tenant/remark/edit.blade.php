<div class="row">
  <div class="col-md-12" id="msg"></div>
  <div class="col-md-3" style="display:table;">>
    {!! $data['remarkList'] !!}
  </div>
  <div class="col-md-9">
    <div class="box box-primary">
      <div class="box-body box-profile">
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-book"></span>Remark Information</span></h3>
        </div>
        <form id="tenantRemarkForm" class="form-horizontal" autocomplete="off">
          {!! $data['form'] !!}
        </form>
      </div>
    </div>
  </div>
</div>


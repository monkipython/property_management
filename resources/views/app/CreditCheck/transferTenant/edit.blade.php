<form id="applicationForm" class="form-horizontal" autocomplete="off">
<div class="row">
  <div class="col-md-12" id="msg"></div>
  <div class="col-md-6">
    <div class="box">
      <div class="box-body box-profile">
        <!-- Horizontal Form -->
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-user-times text-danger"></span> FROM OLD PROPERTY/UNIT</h3>
        </div>
        {!! $data['from'] !!}
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="box box-success">
      <div class="box-body box-profile">
        <!-- Horizontal Form -->
        <div class="box-header with-border">
          <h3 class="box-title"><span class="fa fa-fw fa-user-plus text-green"></span> TO NEW PROPERTY/UNIT</h3>
        </div>
        {!! $data['to'] !!}
      </div>
    </div>
  </div>
</div>
</form>
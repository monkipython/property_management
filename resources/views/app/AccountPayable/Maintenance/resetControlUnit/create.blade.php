<div class="row">
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-12" id="formContainer">
        <form class="form-horizontal" id="maintenanceResetForm" autocomplete="off">
            <div class="box-footer">
              <div>
                {!! $data['resetForm'] !!}
              </div>
              <div class="row">
                <div class="col-md-12">
                  <input id="submit" class="btn btn-info pull-right btn-sm col-md-12 margin-bottom" type="submit" value="Reset Maintenance(s)">
                </div>
              </div>
            </div>    
        </form>
    </div>
</div>
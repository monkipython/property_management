<form class="form-horizontal" id="unitHistForm" autocomplete="off">
    <div class="row">
        <div class="col-md-12" id="msg"></div>
        <div class="col-md-6">
            <div class="box box-primary">
              <div class="box-body box-profile">
                <div class="box-header with-border">
                  <h3 class="box-title"><span class="fa fa-fw fa-user"></span> Edit Unit's History</h3>
                </div>
                {!! $data['formUnitHist1'] !!}
              </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel box box-primary">
                <div class="box-body box-profile">
                    <div class="box-header with-border">
                        <h3 class="box-title"><span class="fa fa-fw fa-user"></span>Building Information</h3>
                    </div>
                    {!! $data['formUnitHist2'] !!}
                </div>
            </div>
        </div>
    </div>
</form>
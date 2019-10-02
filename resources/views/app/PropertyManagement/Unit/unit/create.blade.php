<form class="form-horizontal" id="unitCreateForm" autocomplete="off">
    <div class="row">
        
        <div class="col-md-4">
          <div class="box box-primary">
            <div class="box-body box-profile">
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Unit Detail</h3>
              </div>
              {!! $data['unitCreateForm1'] !!}
            </div>
          </div>
        </div>
        <div class="col-md-4">
              <div class="box box-primary">
                <div class="box-body box-profile">
                    <div class="box-header with-border">
                        <h3 class="box-title"><span class="fa fa-fw fa-home"></span>Building Information</h3>
                    </div>
                    {!! $data['unitCreateForm2'] !!}
                </div>
              </div>
        </div>
        <div class="col-md-4">
          <div class="box box-primary">
            <div class="box-body box-profile">
               <div class="box-header with-border">
                   <h3 class="box-title"><span class="fa fa-fw fa-user"></span> System Results</h3>
               </div>
            </div>
            <div id="result"></div>
          </div>
        </div>
    </div>
</form>
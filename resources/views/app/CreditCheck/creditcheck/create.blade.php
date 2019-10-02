@extends('template', ['jsCss'=>'/creditCheck/create', 'data'=>$data])
@section('account')
  {!! $data['account'] !!}
@stop
@section('nav') 
  {!! $data['nav'] !!}
@stop
@section('title')
  Credit Check
@stop

@section('content-header')
   <i class="fa fa-fw fa-home"></i> Credit Check - New Applicatant
@stop

@section('content')

{!! $data['upload']['hiddenForm'] !!}
<form class="form-horizontal" id="applicationForm" autocomplete="off"> 
  <div class="row">
    <!--Tenant Information-->
    <div class="col-md-12" id="msg"></div>
    <div class="col-md-3">
      <div class="box box-primary">
        <div class="box-body box-profile">
          <div class="box-header with-border">
            <h3 class="box-title"><span class="fa fa-fw fa-user"></span> Tenant Information</h3>
            <span class="fa fa-fw fa-user-plus pull-right text-green" data-toggle="tooltip" title="Additional Tenant" id="additionalTenant"></span>
            <span class="label label-primary pull-right" id="tenantNum">1</span>
            <span class="fa fa-fw fa-user-times pull-right text-danger" data-toggle="tooltip" title="Remove Addition Tenant" id="removeTenant"></span> 
          </div>
          <div class="panel box" id="tenantInfoWrapper">
            <div id="eachTenant0">
              <div class="box-header with-border">
                <h4 class="box-title">
                  <a data-toggle="collapse" data-parent="#accordion" href="#collapse0">
                    Tenant #1 - Each Application Costs $35
                  </a>
                </h4>
              </div>
              <div id="collapse0" class="panel-collapse collapse in">
                <div class="box-body"> 
                  {!! $data['formAppInfo'] !!}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-md-9" >
      <div class="row" >
        <!--Property Information-->
        <div class="col-md-4">
          <div class="box box-primary">
            <div class="box-body box-profile">
              <!-- Horizontal Form -->
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-home"></span> Property Information</h3>
              </div>
              {!! $data['formApp'] !!}
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="box box-primary">
            <div class="box-body box-profile">
              <!-- Horizontal Form -->
              <div class="box-header with-border">
                <h3 class="box-title"><span class="fa fa-fw fa-cloud-upload"></span>Upload PDF</h3>
              </div>
              <div class="row">
                <div class="col-md-12">
                  {!! $data['upload']['container'] !!}
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
    </div>
  </div>
</form>
@stop
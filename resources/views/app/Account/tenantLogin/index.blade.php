@extends('templateAccount', ['jsCss'=>'/account/tenantLogin', 'data'=>$data])

@section('content')
<div class="row">
  <div class="col-md-4"></div>
  <div class="col-md-4">
    <div class="col-md-12" id="mainMsg"></div>
    <div class="register-logo">
      <a href="/tenantLogin"><b>Tenant Login</b></a>
    </div>
    <div class="register-box-body">
      <p class="login-box-msg">Tenant Login</p>
      <form id="applicationForm" class="form-horizontal">
        {!! $data['form'] !!}
      </form>
    </div>
  </div>
  <div class="col-md-4"></div>
  <!-- /.form-box -->
</div>
@stop
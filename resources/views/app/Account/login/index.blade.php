@extends('templateAccount', ['jsCss'=>'/account/login', 'data'=>$data])

@section('content')
<div class="row">
  <div class="col-md-4"></div>
  <div class="col-md-4">
    <div class="col-md-12" id="msg"></div>
    <div class="register-logo">
      <a href="/login"><b>Dataworkers</b></a>
    </div>
    <div class="register-box-body">
      <p class="login-box-msg">Login</p>
      <form id="applicationForm">
        {!! $data['form'] !!}
      </form>
      <a href="register" class="text-center">Not yet a member? Click here to register</a> | 
      <a href="passwordReset" class="text-center">Forget Password?</a>
    </div>
  </div>
  <div class="col-md-4"></div>
  <!-- /.form-box -->
</div>
@stop
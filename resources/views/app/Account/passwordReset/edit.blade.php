@extends('templateAccount', ['jsCss'=>'/account/passwordReset', 'data'=>$data])

@section('content')
<div class="row">
  <div class="col-xs-4"></div>
  <div class="col-xs-4">
    <div class="col-md-12" id="msg"></div>
    <div class="register-logo">
      <a href="/login"><b>Dataworkers</b></a>
    </div>
    <div class="register-box-body">
      <p class="login-box-msg">Password Reset</p>
      <form id="applicationFormPasswordReset">
        {!! $data['form'] !!}
      </form>
      {!! $data['msg'] !!}
    </div>
  </div>
  <div class="col-xs-4"></div>
  <!-- /.form-box -->
</div>
@stop
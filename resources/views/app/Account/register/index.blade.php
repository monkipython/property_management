@extends('templateAccount', ['jsCss'=>'/account/register', 'data'=>$data])

@section('content')
<div class="row">
  <div class="col-xs-4"></div>
  <div class="col-xs-4">
    <div class="col-md-12" id="msg"></div>
    <div class="register-logo">
      <a href="/login"><b>Dataworkers</b></a>
    </div>
    <div class="register-box-body">
      <p class="login-box-msg">Register a new membership</p>
      <form id="applicationForm" class="form-horizontal" autocomplete="off">
        {!! $data['form'] !!}
      </form>
      <a href="/login" class="text-center">I already have a membership</a>
    </div>

  </div>
  <div class="col-xs-4"></div>
  <!-- /.form-box -->
</div>
@stop
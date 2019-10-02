<div class="row">
  <div class="col-md-12">
    <div class="box box-primary">
      <div class="box-body box-profile">
        <div class="box-header with-border">
          {!! $data['trust'] !!}
        </div>
        <div class="box-header with-border">
          {!! $data['bank'] !!}
        </div>
        <div class="box-header with-border">
          {!! $data['bankName'] !!}
        </div>
        <div class="box-header with-border">
          {!! $data['street'] !!}
        </div>
        <div class="box-body">
          <div class="alert alert-warning">{!! $data['title'] !!}</div>
          {!! $data['table'] !!}
        </div>
         {!! $data['jsonData'] !!}
      </div>
    </div>
  </div>
</div>
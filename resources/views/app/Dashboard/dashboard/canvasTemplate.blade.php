<div id="{{ $data['tabId'] }}" class="{{ $data['tabClass'] }}"> 
  <div class="col-md-12">
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">{!! $data['plotTitle'] !!}</h3>
        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
          <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <div class="box-body">
        {!! $data['canvas'] !!}
        <form class="form-horizontal" id="{{ $data['formId'] }}" autocomplete="off">
          {!! $data['form'] !!}
          
        </form>
      </div>
    </div>
  </div>
</div>


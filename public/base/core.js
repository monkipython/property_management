/*******************************************************************************
 ************************ DEBUG FUNCTION **************************************
 ******************************************************************************/
function dd(dataObject){
  console.log(dataObject);
  console.log('-------------------------');
}
/*******************************************************************************
 ************************  AJAX SECTION   **************************************
 ******************************************************************************/
function _GETDEFAULT(defaultData, modifyData){
  for(var key in modifyData){
    defaultData[key] = modifyData[key];
  }
  return defaultData;
}
function DISABLEDSUBMIT(id, isDisabled){
//  var $disaledId = $(id + ' input,' + id + ' select,' + id + ' textarea');
//  var $submitId  = $(id + ' #submit');
//  if(isDisabled){
//    $submitId.button('loading');
//  } else{
//    $submitId.button('reset');
//  }
//  $disaledId.prop('disabled', isDisabled);
}
function AJAX(data){
  $('#mainMsg').show();
  
  var errorFunc = function (jqXhr,  textStatus){
    $('#mainMsg,#msg').hide();
    switch(jqXhr.status){
      case 404: alert('page not found'); break;
      case 419: alert('Your session is expired. Please refresh the page and try it again.'); break;
      case 403: alert('Access Denied.'); break;
      case 500: alert('Please contact sean.hayes@siemens to report the bug.'); break;
      default: break;
    }
  };
  var _hideMainMsg = function(time){
    setTimeout(function(){ 
      var defaultMainMsg = '<div class="box-body text-center">Loading ....</div><div class="overlay"><i class="fa fa-refresh fa-spin"></i></div>';
      $('#mainMsg').fadeOut(1000).hide().html(defaultMainMsg);
    }, time);
  };
  data.type      = data.type || 'GET';
  data.async     = data.async || true;
  data.cache     = data.cache || false;
  data.dataType  = data.dataType || 'JSON';
  data.beforeSend= data.beforeSend || function(){};
  data.complete  = data.complete || function(){};
  data.error     = data.error || errorFunc;
  data.successSubmit = data.success; // We need to move this successSubmit otherwise it will keep looping
  $.ajax({
    type: data.type,
    data: data.data,
    url:  data.url,
    async: data.async,
    cache: data.cache,
    dataType: data.dataType,
    beforeSend: data.beforeSend, 
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    },
    success: function(ajaxData){
      $('#mainMsg,#msg').hide();
      
      if(ajaxData.error !== undefined && ajaxData.error.mainMsg !== undefined){
        $('#mainMsg').show().html(ajaxData.error.mainMsg);
        _hideMainMsg(3000);
      }
      if(ajaxData.mainMsg !== undefined){
        $('#mainMsg').show().html(ajaxData.mainMsg);
        _hideMainMsg(1000);
      }
      if(ajaxData.msg !== undefined){
        $('#msg').show().html(ajaxData.msg);
      }
      
//      if(ajaxData.popupMsg !== undefined){
//        ALERT({content: ajaxData.popupMsg});
//      }
      data.successSubmit(ajaxData);
    },
    error: data.error,
    complete: data.complete
  });
};

function AJAXSUBMIT(id, data){
  $('#mainMsg').show();
  var errorFunc = function (jqXhr,  textStatus){
    switch(jqXhr.status){
      case 404: alert('page not found'); break;
      case 419: alert('Your session is expired. Please refresh the page and try it again.'); break;
      case 403: alert('Access Denied.'); break;
      case 500: alert('Please contact sean.hayes@siemens to report the bug.'); break;
      default: break;
    }
  };
  var _hideMainMsg = function(time){
    setTimeout(function(){ 
      var defaultMainMsg = '<div class="box-body text-center">Loading ....</div><div class="overlay"><i class="fa fa-refresh fa-spin"></i></div>';
      $('#mainMsg').fadeOut(1000).hide().html(defaultMainMsg);
    }, time);
  };

  
  data.type      = data.type || 'GET';
  data.async     = data.async || true;
  data.cache     = data.cache || false;
  data.dataType  = data.dataType || 'JSON';
  data.beforeSend= data.beforeSend || function(){};
  data.complete  = data.complete || function(){};
  data.error     = data.error || errorFunc;
  data.successSubmit = data.success; // We need to move this successSubmit otherwise it will keep looping
  data.data = (data.data) ? data.data : JSON.parse(JSON.stringify($(id).serialize())),
          
  Helper.removeDisplayError();
  DISABLEDSUBMIT(id, true);
  
  $.ajax({
    type: data.type,
    data: data.data,
    url:  data.url,
    async: data.async,
    cache: data.cache,
    dataType: data.dataType,
    beforeSend: data.beforeSend, 
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    },
    success: function(ajaxData){
      $('#mainMsg,#msg').hide();
      DISABLEDSUBMIT(id, false);
      if(!Helper.displayError(ajaxData)){
       
      } else{
        if(ajaxData.error !== undefined && ajaxData.error.mainMsg !== undefined){
          $('#mainMsg').show().html(ajaxData.error.mainMsg);
          _hideMainMsg(3000);
        }
        if(ajaxData.mainMsg !== undefined){
          $('#mainMsg').show().html(ajaxData.mainMsg);
          _hideMainMsg(1000);
        }
        
        if(ajaxData.msg !== undefined){
          $('#msg').show().html(ajaxData.msg);
        }
        
        if(ajaxData.popupMsg !== undefined){
          ALERT({content: ajaxData.popupMsg});
        }
        data.successSubmit(ajaxData);
      }
    },
    error: data.error,
    complete: data.complete
  });        
};
/*******************************************************************************
 ************************ CONFIRM SECTION **************************************
 ******************************************************************************/
function CONFIRM(data){
  var defaultData = {
    title   :'Confirm',
    closeIcon: true,
    escapeKey: 'cancel',
    content :'Are you sure?',
    boxWidth:'500px',
    useBootstrap:false,
    animation:'RotateYR',
    type:'blue',
    closeIcon:false,
    theme:'bootstrap',
    onContentReady:function(){},
    backgroundDismiss:false
  };
  
  return $.confirm(_GETDEFAULT(defaultData, data));
};
function ALERT(data){
  var defaultData = {
    title: 'ERROR!',
    closeIcon: true,
    escapeKey: 'cancel',
    content: 'Are you sure?',
    boxWidth: '500px',
    useBootstrap: false,
    animation: 'RotateYR',
    type: 'red',
    theme: 'bootstrap',
    buttons: {
      ok:{
        text: 'OK',
        btnClass:'btn btn-red'
      }
    }
  };
  $.alert(_GETDEFAULT(defaultData, data));
};
/*******************************************************************************
 ************************ GRID TABLE SECTION  **********************************
 ******************************************************************************/
function GRID(gridData){
  var _getTableHeight = function(heightButtonMargin){
    heightButtonMargin = heightButtonMargin || 100;
    const minHeight = 300;
    var rawVal = $(window).height() - $('.main-header').outerHeight() - heightButtonMargin;
    return (rawVal > minHeight) ? rawVal : minHeight;
  };
  var _getSuccessDownloadEvent = function(ajaxData){
    if(ajaxData.error !== undefined){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-times-circle"></i> ERROR</b>',
        content:ajaxData.error.msg,
        buttons: {
          cancel: {text: 'Close'}
        }
      }); 
    }else{
      var jc = CONFIRM({ 
        title  : (ajaxData.title !== undefined) ? ajaxData.title  : '<span class="text-red"><i class="fa fa-download"></i> DOWNLOAD</span>',
        content: (ajaxData.popupMsg !== undefined) ? ajaxData.popupMsg : ajaxData.msg,
        buttons: {
          cancel: {text: 'Close'}
        },
        onContentReady: function () {
          $('.downloadLink').unbind('click').on('click', function(){
            jc.close();
          });
        }
      }); 
    }
  };
  var selections = [];
  var reportList = '';
  
  // Add noeditFormatter to disable editable on certain cells 
  var gridColumns = gridData.columns;
  for(var i = 0; i < gridColumns.length; i++) {
    
    if(gridColumns[i].hasOwnProperty('editable') && gridColumns[i].editable.hasOwnProperty('isEditableField')) {
      // isEditableField is the field that's used to check if the cell should be editable or not
      var checkField = gridColumns[i].editable.isEditableField;
      delete gridColumns[i].editable.isEditableField;
      gridColumns[i].editable.noeditFormatter = function(value, row, index) {
        if(row[checkField]) {
          return false; // Return false if you want the field editable.
        }else {
          return value; // Return value if you want the field disabled.
        }
      };
    }
    
    if(gridColumns[i].hasOwnProperty('isCheckableField')){
      var checkField = gridColumns[i].isCheckableField;
      delete gridColumns[i].isCheckableField;
      gridColumns[i].formatter = function(value,row,index){
        var disabled = (checkField in row && row[checkField] == false) ? true : false;
        return {disabled:disabled};
      };
    }
  }

  var defaultData = {
    showRefresh : true,
    toolbar     : '#toolbar',
    height      : _getTableHeight(gridData.heightButtonMargin),
    cache       : false,
    sortable    : true,
    editable    : true,
    search      : false,
    showColumns : true,
    pagination  : true,
    disableControlWhenSearch: true,
    searchOnEnterKey: true,
//    striped     :true,
    maintainSelected: true, // Need to check here https://github.com/wenzhixin/bootstrap-table-examples/blob/master/issues/917.html
    rememberOrder: true,
    iconSize    : 'sm',
//    exportTypes : ['csv', 'excel'],
//    showExport  : true,
    exportOptions : {
      htmlContent: true
    },
    searchTimeOut : 0,
    filterControl : true,
//    fixedColumns: true,
//    fixedNumber : 1,
    queryParams : function(params){
      params.op = 'show';
      var defaultUrl = url('?');
      if(defaultUrl !== undefined){
        params.defaultFilter = JSON.stringify(defaultUrl);
      }
      return params;
    },
    dataType:'json',
    responseHandler:  function (res) {
      $.each(res.rows, function (i, row) {
        row.checkbox = $.inArray(row.id, selections) !== -1;
      });
      return res;
    },
    icons:{
      refresh: 'fa fa-fw fa-refresh',
      detailOpen: 'fa fa-fw fa-cog',
      detailClose: 'fa fa-fw fa-cog',
      paginationSwitchDown: 'glyphicon-collapse-down icon-chevron-down',
      paginationSwitchUp: 'glyphicon-collapse-up icon-chevron-up',
      toggle: 'glyphicon-list-alt icon-list-alt',
      columns: 'fa fa-fw fa-columns',
      export: 'glyphicon glyphicon-export'
    },
    pageSize    : 25,
    pageList    : [25, 100, 200, 300, 500],
    sidePagination  : 'server'
  };
  defaultData.id = gridData.id || '#gridTable';
  var $table = $(defaultData.id);
  // RESIZE THE TABLE WHEN THE BROWSER MOVE
//  $(window).resize(function(){
//    $table.bootstrapTable('refreshOptions', {height:_getTableHeight()});
//  });
  
  var defaultDatepickerOptions = {'format':'yyyy-mm-dd','autoclose':true,'immediate-updates':true,'clear-btn':true,'keyboard-navigation':false,'today-highlight':true};
  var dateColumns = gridData.dateColumns || [];
  var datepickerOptions= gridData.datepickerOptions || defaultDatepickerOptions;
  delete gridData['datepickerOptions'];
  delete gridData['dateColumns'];
  
  
  
  
  
  
  //########## START TO CREATE GRID TABLE ##########//
  $table.bootstrapTable(_GETDEFAULT(defaultData, gridData));

  //########## KEEP TRACK THE CHECK BOX WHEN PAGINATION ##########//
  $table.on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table', function (e, rows) {
    var ids = $.map(!$.isArray(rows) ? [rows] : rows, function (row) {
      return row.id;
    });
    var func = $.inArray(e.type, ['check', 'check-all']) > -1 ? 'union' : 'difference';
    selections = _[func](selections, ids);
  });
  
  $table.on('click-row.bs.table', function (e, row, $element) {
    $('.selectRow').removeClass('selectRow');
    $($element).addClass('selectRow');
  });
  $table.on('post-body.bs.table',function(e,data,value,row,$element){
    for(var i = 0; i < dateColumns.length; i++){ 
      
//      $('th[data-field="' + dateColumns[i] + '"] input').attr('data-provide','datepicker');
//      for(var key in datepickerOptions){
//        $('th[data-field="' + dateColumns[i] + '"] input').attr('data-date-' + key,datepickerOptions[key]);
//      }
      
      $('th[data-field="' + dateColumns[i] + '"] input').on('change',function(e){$(this).focus();});
    }
    
    
    // THIS IS SPECIFICALLY FOR LINK ICON
    $('.iconLinkClick').unbind('click').on('click', function(){
      var href = $(this).attr('href');
      AJAX({
        url:  href,
        type: 'GET',
        success : function(ajaxData){
          _getSuccessDownloadEvent(ajaxData);
        }
      });      
      return false;
    });
    TOOLTIPSTER('.tip');
  });

  //########## BUILD REPORT PDF LIST ##########//
  if(!jQuery.isEmptyObject(gridData.reportList)){
    for(var k in gridData.reportList){
      var clsReportList = gridData.clsReportList && !gridData.reportList[k].match(/^Export/) ? gridData.clsReportList : 'reportList';
      reportList += Html.tag('li', '<a>' + gridData.reportList[k] + '</a>', {role:'menuitem', class: 'pointer ' + clsReportList, 'data-type':k});
    }
    reportList = Html.tag('ul', reportList, {class:'dropdown-menu', role:'menu'});
    
    var icon   = Html.tag('i', Html.span('', {class:'caret'}), {class:'glyphicon fa fa-fw fa-download'});
    var button = Html.tag('button', icon, {class:'btn btn-default btn-sm dropdown-toggle', 'aria-label': 'export type', title:'Export data', 'data-toggle':'dropdown', type: 'button'});
    var div    = Html.tag('div', button + reportList, {class:'export btn-group'});
    
    $('.columns.columns-right.btn-group.pull-right').append(div);
    $('.reportList').unbind('click').on('click', function(){
      var op = $(this).attr('data-type');
      var dataOptions = $table.bootstrapTable('getOptions', []);
      var data = {
        op: $(this).attr('data-type'),
        sort: dataOptions.sortName,
        order: dataOptions.sortOrder,
        limit: -1,
        filter: function(){
          var filter = {};
          for(var i in dataOptions.valuesFilterControl){
            var v = dataOptions.valuesFilterControl[i];
            if(v.value != '' && v.field != 'checkbox'){
              filter[v.field] = v.value;
            }
          }
          return JSON.stringify(filter);
        }, 
        selectedField: function(){
//          dd(defaultData);
          //The fields that will be used for generating the report file will only be the 
          //visible columns in the grid table
          var columnData = $(defaultData.id).bootstrapTable('getVisibleColumns');
          //Separate columns with '-' and separate the fields and titles of an 
          //individual column with a '~' character
          var requestStr = columnData.reduce(function(a,b){
            return a + '-' + b.field + '~' + b.title;
          },'');
          
          return op.match(/^Export/) ? requestStr : '';
        }
      };
      
      gridData.dataReport  = typeof(gridData.dataReport) === 'function' ? gridData.dataReport() : gridData.dataReport;
      AJAX({
        url:  gridData.urlReport,
        data: (gridData.dataReport !== undefined) ?  gridData.dataReport + '&sort=' + dataOptions.sortName + '&order=' + dataOptions.sortOrder + (gridData.isOpInUrlReport ? '&op=' + op  : '') : data,
        type: (gridData.typeReport !== undefined) ? gridData.typeReport : 'GET',
        success : function(ajaxData){
          _getSuccessDownloadEvent(ajaxData);
//          if(ajaxData.error !== undefined){
//            CONFIRM({
//              title: '<b class="text-red"><i class="fa fa-times-circle"></i> ERROR</b>',
//              content:ajaxData.error.msg,
//              buttons: {
//                cancel: {text: 'Close'}
//              }
//            }); 
//          }else{
//            var jc = CONFIRM({ 
//              title  : (ajaxData.title !== undefined) ? ajaxData.title  : '<span class="text-red"><i class="fa fa-download"></i> DOWNLOAD</span>',
//              content: (ajaxData.popupMsg !== undefined) ? ajaxData.popupMsg : ajaxData.msg,
//              buttons: {
//                cancel: {text: 'Close'}
//              },
//              onContentReady: function () {
//                $('.downloadLink').unbind('click').on('click', function(){
//                  jc.close();
//                });
//              }
//            }); 
//          }
        }
      });
    });
  }
};
//function GRIDREPORT(gridData){
//  var _getTableHeight = function(){
//    const minHeight = 300;
//    var rawVal = $(window).height() - $('.main-header').outerHeight() - 95;
//    return (rawVal > minHeight) ? rawVal : minHeight;
//  };
//  
//  var selections = [];
//  var reportList = '';
//  
//  var defaultData = {
//    showRefresh : true,
//    toolbar     : '#toolbar',
//    height      : _getTableHeight(),
//    cache       : false,
//    sortable    : true,
//    editable    : true,
//    search      : false,
//    showColumns : true,
//    pagination  : true,
//    disableControlWhenSearch: true,
//    searchOnEnterKey: true,
////    striped     :true,
//    maintainSelected: true, // Need to check here https://github.com/wenzhixin/bootstrap-table-examples/blob/master/issues/917.html
//    rememberOrder: true,
//    iconSize    : 'sm',
////    exportTypes : ['csv', 'excel'],
////    showExport  : true,
//    exportOptions : {
//      htmlContent: true
//    },
//    searchTimeOut : 0,
//    filterControl : true,
////    fixedColumns: true,
////    fixedNumber : 1,
//    queryParams : function(params){
//      params.op = 'show';
//      var defaultUrl = url('?');
//      if(defaultUrl !== undefined){
//        params.defaultFilter = JSON.stringify(defaultUrl);
//      }
//      return params;
//    },
//    responseHandler:  function (res) {
//      $.each(res.rows, function (i, row) {
//        row.checkbox = $.inArray(row.id, selections) !== -1;
//      });
//      return res;
//    },
//    icons:{
//      refresh: 'fa fa-fw fa-refresh',
//      detailOpen: 'fa fa-fw fa-cog',
//      detailClose: 'fa fa-fw fa-cog',
//      paginationSwitchDown: 'glyphicon-collapse-down icon-chevron-down',
//      paginationSwitchUp: 'glyphicon-collapse-up icon-chevron-up',
//      toggle: 'glyphicon-list-alt icon-list-alt',
//      columns: 'fa fa-fw fa-columns',
//      export: 'glyphicon glyphicon-export'
//    },
//    pageSize    : 25,
//    pageList    : [25, 100, 200, 300, 500],
//    sidePagination  : 'server'
//  };
//  
//  defaultData.id = defaultData.id || '#gridTable';
//  var $table = $(defaultData.id);
//  
//  var defaultDatepickerOptions = {'format':'yyyy-mm-dd','autoclose':true,'immediate-updates':true,'clear-btn':true};
//  var dateColumns = gridData.dateColumns || [];
//  var datepickerOptions= gridData.datepickerOptions || defaultDatepickerOptions;
//  delete gridData['datepickerOptions'];
//  delete gridData['dateColumns'];
//  //########## START TO CREATE GRID TABLE ##########//
//  $table.bootstrapTable(_GETDEFAULT(defaultData, gridData));
//  
//  //########## KEEP TRACK THE CHECK BOX WHEN PAGINATION ##########//
//  $table.on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table', function (e, rows) {
//    var ids = $.map(!$.isArray(rows) ? [rows] : rows, function (row) {
//      return row.id;
//    });
//    var func = $.inArray(e.type, ['check', 'check-all']) > -1 ? 'union' : 'difference';
//    selections = _[func](selections, ids);
//  });
//  
//  $table.on('click-row.bs.table', function (e, row, $element) {
//    $('.selectRow').removeClass('selectRow');
//    $($element).addClass('selectRow');
//  });
//  $table.on('post-body.bs.table',function(e,data,value,row,$element){
//    for(var i = 0; i < dateColumns.length; i++){ 
//      
//      $('th[data-field="' + dateColumns[i] + '"] input').attr('data-provide','datepicker');
//      for(var key in datepickerOptions){
//        $('th[data-field="' + dateColumns[i] + '"] input').attr('data-date-' + key,datepickerOptions[key]);
//      }
//      
//      $('th[data-field="' + dateColumns[i] + '"] input').on('change',function(e){$(this).focus();});
//    }
//  });
//  
//
//  //########## BUILD REPORT PDF LIST ##########//
//  if(!jQuery.isEmptyObject(gridData.reportList)){
//    var clsReportList = 'reportList';
//    for(var k in gridData.reportList){
//       reportList += Html.tag('li', '<a>' + gridData.reportList[k] + '</a>', {role:'menuitem', class:clsReportList, 'data-type':k});
//    }
//    reportList = Html.tag('ul', reportList, {class:'dropdown-menu', role:'menu'});
//    
//    var icon   = Html.tag('i', Html.span('', {class:'caret'}), {class:'glyphicon fa fa-fw fa-download'});
//    var button = Html.tag('button', icon, {class:'btn btn-default btn-sm dropdown-toggle', 'aria-label': 'export type', title:'Export data', 'data-toggle':'dropdown', type: 'button'});
//    var div    = Html.tag('div', button + reportList, {class:'export btn-group'});
//    
//    $('.columns.columns-right.btn-group.pull-right').append(div);
//    $('.reportList').on('click', function(){
//      var op = $(this).attr('data-type');
//      var dataOptions = $table.bootstrapTable('getOptions', []);
//      var data = {
//        op: $(this).attr('data-type'),
//        sort: dataOptions.sortName,
//        order: dataOptions.sortOrder,
//        limit: -1,
//        filter: function(){
//          var filter = {};
//          for(var i in dataOptions.valuesFilterControl){
//            var v = dataOptions.valuesFilterControl[i];
//            if(v.value != '' && v.field != 'checkbox'){
//              filter[v.field] = v.value;
//            }
//          }
//          return JSON.stringify(filter);
//        }, 
//        selectedField: function(){
//          //The fields that will be used for generating the report file will only be the 
//          //visible columns in the grid table
//          var columnData = $(defaultData.id).bootstrapTable('getVisibleColumns');
//          //Separate columns with '-' and separate the fields and titles of an 
//          //individual column with a '~' character
//          var requestStr = columnData.reduce(function(a,b){
//            return a + '-' + b.field + '~' + b.title;
//          },'');
//          
//          return op.match(/^Export/) ? requestStr : '';
//        }
//      };
//      
//      AJAX({
//        url:  gridData.urlReport,
//        data: (gridData.dataReport !== undefined) ? gridData.dataReport : data,
//        type: (gridData.typeReport !== undefined) ? gridData.typeReport : 'GET',
//        success : function(ajaxData){
//          if(ajaxData.error !== undefined){
//            CONFIRM({
//              title: '<b class="text-red"><i class="fa fa-times-circle"></i> ERROR</b>',
//              content:ajaxData.error.msg,
//              buttons: {
//                cancel: {text: 'Close'}
//              }
//            }); 
//          }else{
//            var jc = CONFIRM({
//              title: '<span class="text-red"><i class="fa fa-download"></i> DOWNLOAD REPORT</span>',
//              content: ajaxData.msg,
//              buttons: {
//                cancel: {text: 'Close'}
//              },
//              onContentReady: function () {
//                $('.downloadLink').unbind('click').on('click', function(){
//                  jc.close();
//                });
//              }
//            }); 
//          }
//        }
//      });
//    });
//  }
//};
function GRIDREFRESH(gridTableId){
  $(gridTableId).bootstrapTable('refresh', {silent: true});
};
/*******************************************************************************
 ************************ AUTOCOMPLETE SECTION  ********************************
 ******************************************************************************/
function AUTOCOMPLETE(option, id){
  id = id || '.autocomplete';
  var formId = (option !== undefined && option.formId !== undefined) ? 'form' + option.formId + ' ': '';
  var param =  (option !== undefined && option.param !== undefined) ? option.param : {};
  var _getEvent = function (dataSet) {
    for(var k in dataSet){
      if(k.match(/^field/)){
        var kPieces = k.match(/^field([0-9]+)/);
        var index = (kPieces !== null && kPieces[1] !== undefined) ? '\\[' +kPieces[1]+ '\\]' : '';
        var key = k.replace(/^field([0-9]*)_/, '');
        key = formId + '#' + key + index;
        $(key).val(dataSet[k]);
      } else if(k.match(/^append_/)){
        var labelId = 'lable' . key + index; 
        $(formId + '#' + labelId).remove();
        var key = k.replace(/^append_/, '');
        key = formId + '#' + key;
        $(key).after('<div class="autocompleteAfter" id="' + labelId + '">' + dataSet[k] + '</div>');
      } else if(k.match(/^list_/)){
        if(dataSet[k] !== null){
          var keyOpt   = k.replace(/^list_/, '');
          var pList = dataSet[k].split('|').sort();
          var opt   = '<option value="">Select</option>';
          for(var i in pList){
            var keyVal = pList[i].split('~');
            var key    = keyVal[0];
            var value  = keyVal[1];
            opt += '<option value="'+key+'">'+value+'</option>';
          }
          $(formId +  '#' + keyOpt).html(opt);
        }
      }
    }
    return dataSet;
  };
  
  $(formId + id).unbind('focus').on('focus', function(){
    option = option || {};
    var num    = $(this).attr('id').match( /\d+/g);
    var prop   = $('#prop');
    var arrNum = num && prop.length < 1 ? '\\['+ num +'\\]' : '';
    var defaultData = {
      serviceUrl: '/autocomplete/' +  $(this).attr('id') + '?prop=' + $(formId + '#prop' + arrNum).val(),
      onSelect: function (suggestion) {
//        console.log('You selected: ' + suggestion.value + ', ' + suggestion.data);
        var dataSet = _getEvent(suggestion);
        if(num) {
          dataSet.num = num;
        }
        $(this).val(suggestion.data);
//        dd(dataSet);
        if(option.callback !== undefined){
          
          option.callback(dataSet);
//          delete option['callback'];
        }
      },
      params: param, 
      autoSelectFirst: true,
      tabDisabled: false,
      preserveInput: true, 
      preventBadQueries: false,
      zIndex: 999999999,
      width: 500
    };
    
    if($(this).attr('id') == 'unit'){
      defaultData['params'] = {prop: $(formId + '#prop').val()};
    }
    
    $(id).unbind('devbridgeAutocomplete').devbridgeAutocomplete(_GETDEFAULT(defaultData, option));
  });
};
//------------------------------------------------------------------------------
function AUTOCOMPLETEV2(option, id){
  id = id || '.autocomplete';
  var formId = (option !== undefined && option.formId !== undefined) ? 'form' + option.formId + ' ': '';
  var param =  (option !== undefined && option.param !== undefined) ? option.param : {};
  var _getEvent = function (dataSet, option) {
    for(var k in dataSet){
      if(k.match(/^field/)){
        var kPieces = k.match(/^field([0-9]+)/);
        var index = (kPieces !== null && kPieces[1] !== undefined) ? '\\[' +kPieces[1]+ '\\]' : '';
        var key = k.replace(/^field([0-9]*)_/, '');
        key = formId + '#' + key + index;
        $(key).val(dataSet[k]);
      } else if(k.match(/^append_/)){
        var labelId = 'lable' . key + index; 
        $(formId + '#' + labelId).remove();
        var key = k.replace(/^append_/, '');
        key = formId + '#' + key;
        $(key).after('<div class="autocompleteAfter" id="' + labelId + '">' + dataSet[k] + '</div>');
      } else if(k.match(/^list_/)){
        if(dataSet[k] !== null){
          var keyOpt   = k.replace(/^list_/, '');
          var pList = dataSet[k];
          var opt   = '<option value="">Select</option>';
          for(var key in pList){
            var value  = pList[key];
            opt += '<option value="'+key+'">'+value+'</option>';
          }
          $(formId +  '#' + keyOpt).html(opt);
        }
      }
    }
    return dataSet;
  };
  
  $(formId + id).unbind('focus').on('focus', function(){
    option = option || {};
    var defaultData = {
      serviceUrl: '/autocompletev2/' +  $(this).attr('id'),
      onSelect: function (suggestion) {
//        console.log('You selected: ' + suggestion.value + ', ' + suggestion.data);
        var dataSet = _getEvent(suggestion, option);
        $(this).val(suggestion.data);
//        dd(dataSet);
        if(option.callback !== undefined){
          
          option.callback(dataSet);
//          delete option['callback'];
        }
        
      },
      params: param, 
      autoSelectFirst: true,
      tabDisabled: false,
      preserveInput: true, 
      preventBadQueries: false,
      zIndex: 999999999,
      width: 500
    };
    
    if($(this).attr('id') == 'unit'){
      defaultData['params'] = {prop: $('#prop').val()};
    }
    
    $(id).unbind('devbridgeAutocomplete').devbridgeAutocomplete(_GETDEFAULT(defaultData, option));
  });
};
/*******************************************************************************
 ************************ UPLOAD FUNCTION **************************************
 ******************************************************************************/
function UPLOADDELETE(self, uuid, endPoint,gridTable){
  // THIS USED ONLY WHEN USE CLICK THE BUTTON DELETE NEAR VIEW LARGER
  $('.deleteFile').unbind('click').on('click', function(e){
    var type = $(this).attr('data-type');
    e.preventDefault();
    e.stopImmediatePropagation();
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-trash"></i> DELETE FILE</b>',
      content: 'Are you sure want to delete this file',
      buttons:{
        delete:{
          text: '<i class="fa fa-fw fa-trash"></i> Delete',
          btnClass: 'btn-danger',
          action: function(){
            AJAX({
              url: endPoint + '/' + uuid,
              data: {type: type}, 
              type: 'DELETE',
              success: function(ajaxData){
                self.remove();
                $('#uploadView').html('');

                if(typeof(gridTable) !== undefined && gridTable.length > 0){
                  GRIDREFRESH(gridTable);
                }
              }
            });          
          }
        },
        cancel: {
          text: 'Close'
        }
      }
    });
    
  });
};
function UPLOAD(uploadData, endPoint, params){
  var header = {
    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
  };
  var _getViewList = function(id, responseJSON){
    $('.eachPdf').removeClass('active');
    return "<li class='eachPdf pointer active' key='"+id+"' data='" + JSON.stringify(responseJSON) + "' uuid='"+responseJSON.uuid+"'><a><span class='fa fa-fw fa-file-pdf-o'></span> "+responseJSON.name +"</a></li>";
  };
  endPoint = endPoint || '/upload';
  params   = params   || {op:'CreditCheckUpload'};
  
  var defaultData = {
    debug: false,
    maxConnections: 1,
    element: document.getElementById('fine-uploader'),
    request: {
      endpoint: endPoint,
      customHeaders: header,
      params: params,
    },
    deleteFile: {
      enabled: true,
      endpoint: endPoint,
      customHeaders: header,
      params: params
    },
    callbacks: {
      onComplete: function(id, name, responseJSON){
        if(params.onComplete !== undefined){
          params.onComplete(responseJSON);
        } else{
          // Add data to uuid field
          $('#uuid').val($('#uuid').val() + responseJSON.uuid + ',');
          $('#uploadList').prepend(_getViewList(id, responseJSON));
        }
      },
      onAllComplete: function(succeeded,failed){
        $('.eachPdf').unbind('click').on('click', function(){
          var uuid = $(this).attr('uuid');
          $('.eachPdf').removeClass('active');
          $(this).addClass('active');
          
          AJAX({
            url: endPoint + '/' + uuid,
            success: function(ajaxData){
              var $uploadView = $('#uploadView');
              if(ajaxData.type == 'img'){
                $uploadView.html(ajaxData.html);
              } else{
                PDFObject.embed(ajaxData.path, $uploadView);  
              }
            }
          });
        });
      },
      onDelete: function(id){
        var jsonData = JSON.parse($('.eachPdf[key=' +id+']').attr('data'));
        var find = jsonData.uuid + ',';
        var re = new RegExp(find, 'g');
        $('#uuid').val($('#uuid').val().replace(re, ''));

        $('.eachPdf[key=' +id+']').remove();
        $('#uploadView').html('');
      },
      onUpload: function(id, name){
        $('#uploadMsg').html('');
      },
      onError: function(id, name, errorReason, xhr){
        $('#uploadMsg').html(errorReason.uploadMsg);
      }
      
    },
    retry: {
      enableAuto: false
    }
  };
  // OVERRIDE THE DEFAULT VALUE
  for(var i in uploadData){
    if(i == 'element') {
      defaultData['element'] = uploadData['element'];
    }else {
      for(var j in uploadData[i]){
        defaultData[i][j] = uploadData[i][j];
      }
    }
  }
  var uploader = new qq.FineUploader(defaultData);
  return uploader;
};
/*******************************************************************************
 ************************ INPUT MASK SECTION ***********************************
 ******************************************************************************/
function INPUTMASK(option){
  option = option || {};
  var isIncludeDefaultDaterange = option !== undefined && option.isIncludeDefaultDaterange !== undefined  ? option.isIncludeDefaultDaterange : true;
  
  var start = moment().startOf('month');
  var end   =  moment().endOf('month');
  function cb(start, end) {
    $('.daterange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
  }
  var daterangeData = {
    ranges: {
      'Today'       : [moment(), moment()],
      'Yesterday'   : [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
      'Last 7 Days' : [moment().subtract(6, 'days'), moment()],
      'Last 30 Days': [moment().subtract(29, 'days'), moment()],
      'This Month'  : [moment().startOf('month'), moment().endOf('month')],
      'Last Month'  : [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
      'This Year'   : [moment().startOf('year'), moment().endOf('year')],
      'Last Year'   : [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
    }, 
    startDate:start,
    endDate:end
  };
  if(!isIncludeDefaultDaterange){
    delete daterangeData.startDate;
    delete daterangeData.endDate;
  }
  $('.daterange').unbind('daterangepicker').daterangepicker(daterangeData, cb);
  cb(start, end);

  $('.daterange').on('show.daterangepicker',function(e,picker){
    if($('.jconfirm').length > 0){
      $('.daterangepicker').css('z-index',$('.jconfirm').css('z-index'));
    } 
  });

  $('.email').inputmask({
    mask: "*{1,20}[.*{1,20}][.*{1,20}][.*{1,20}]@*{1,20}[.*{2,6}][.*{1,2}]",
    greedy: false,
    onBeforePaste: function (pastedValue, opts) {
      pastedValue = pastedValue.toLowerCase();
      return pastedValue.replace("mailto:", "");
    },
    definitions: {
      '*': {
        validator: "[0-9A-Za-z!#$%&'*+/=?^_`{|}~\-]",
        casing: "lower"
      }
    }
  });

  $('.ssn').unbind('inputmask').inputmask('999-99-9999', { placeholder: '000-00-000' });
  $('.date').unbind('inputmask').inputmask('mm/dd/yyyy', { placeholder: 'mm/dd/yyyy' });
  $('.date:not([readonly])').datepicker({
    format:'mm/dd/yyyy',
    autoclose:true,
    immediateUpdates:true,
    clearBtn:true,
    todayHighlight: true,
    keyboardNaviagtion: false,
  }).on('changeDate', function(e) {
    if(option.date !== undefined){ option.date(); }
  });
  $('.phone').inputmask('999-999-9999', { placeholder: '999-999-999' });
  $('.decimal').inputmask('decimal', {
    radixPoint: ".",
    groupSeparator: ",",
    digits: 2,
    unmaskAsNumber: true,
    autoGroup: true,
    prefix: '$ ', //Space after $, this will not truncate the first character.
    rightAlign: false,
    oncleared: function () { 
      if(self.Value !== undefined){
        self.Value('');
      }
    }
  });
   $('.percent-mask').inputmask('decimal',{
    radixPoint: '.',
    digits: 2,
    suffix: ' %',
    rightAlign: false,
    oncleared: function(){
        if(self.Value !== undefined){
            self.Value('');
        }
    }
  });
};
/*******************************************************************************
 ************************ CHECKBOC TOGGLE FUNCTION *****************************
 ******************************************************************************/
function CHECKBOXTOGGLE(id){
  $(id).bootstrapToggle({
    on: 'Allow',
    off: 'Not Allow',
    size: 'small',
    onstyle: 'success',
    offstyle: 'danger'
  });
};
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
function TOOLTIPSTER(id, data){
  var defaultData = {
    theme: 'tooltipster-light',
    delay: 10,
    debug: false
  };
  $(id).tooltipster(_GETDEFAULT(defaultData, data));
};
//------------------------------------------------------------------------------
function SELECT2(selectId, dataId, data){
  var defaultData  = {
    placeholder: 'Select Option',
  };
  var dropdownData = $(dataId).html();
  var parsedData   = JSON.parse(dropdownData);
  $(selectId).select2(
    Object.assign(_GETDEFAULT(defaultData, data), {data: parsedData})
  );
};

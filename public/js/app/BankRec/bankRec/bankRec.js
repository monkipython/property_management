var app = {
  _url: '/bankRec', 
  _urlIcon: '/groupIcon',
  //_urlReport: '/groupExport',
  //_index:     'group_view', 
  _gridTable: '#gridTable',
  _leftGridTable: '#leftGridTable',
  _rightGridTable: '#rightGridTable',
  _leftUrl: '/clearedTrans',
  _rightUrl: '/bankTrans',
  _leftTableBox: '#leftTableBox',
  _rightTableBox: '#rightTableBox',
  _isMouseDown: false,
  _modalTable: '',
  _selectedIds: {
    '#gridTable'      : {},
    '#leftGridTable'  : {},
    '#rightGridTable' : {},
  },
  main: function(){
    this.grid();
    app._bindEvent();
    //this.create();
  },
//------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(ajaxData){
        window.operateEvents = {
          'click .edit': function (e, value, row, index) { app.edit(row); }
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'trust', 
          reportList     : ajaxData.reportList,
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id = args.id;
          var sData = {};
          sData[name] = args[name];
          AJAX({
            url    : app._url + '/' + id,
            data   : sData,
            type   : 'PUT',
            success: function(updateAjaxData){
              if(updateAjaxData.error !== undefined){
                $el[0].innerHTML = oldValue;
              }
              Helper.displayGridEditErrorMessage(updateAjaxData);
            }
          });
        });
        
        $(app._gridTable).on('click-cell.bs.table', function(e, field, value, row, $element){
          if(field === 'unreconcilable_record'){
            app._viewUnrecRecord(row);
          } 
        });
        
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
        });
        $(app._gridTable).on('editable-shown.bs.table', function(field, row, $el) {
          setTimeout(function(){ 
            $('.editable-input input').select();
          });
        });
      }
    });
  },
//  create: function() {
//    $('#new').on('click', function() {
//      CONFIRM({
//        title: '<b class="text-red"><i class="fa fa-fw fa-sitemap"></i> CREATE GROUP</b>',
//        content: function(){
//          var self = this;
//          AJAX({
//            url     : app._url + '/create',
//            dataType: 'HTML',
//            success: function(createPropAjaxData){
//              self.setContent(createPropAjaxData);
//              INPUTMASK();
//              $('.groupOption').on('change', function() {
//                app._onGroupOptionChange(this.value, self);
//              });
//              var formId = '#groupForm';
//              $(formId).unbind('submit').on('submit', function(){
//                AJAXSUBMIT(formId, {
//                  url:   app._url, 
//                  type: 'POST',
//                  success: function(updateAjaxData){
//                    self.setBoxWidth('500px');
//                    self.$content.html(updateAjaxData.msg);
//                    GRIDREFRESH(app._gridTable);
//                  }
//                });
//                return false;
//              });
//            }
//          });
//        },
//        onContentReady: function () {
//          AUTOCOMPLETE();
//        },
//        boxWidth:'90%',
//        buttons: {
//          cancel: {text: 'Close'}
//        }
//      });
//      return false;
//    });
//  },
//  edit: function(row){
//    var id = row.id;
//    CONFIRM({
//      title: '<b class="text-red"><i class="fa fa-fw fa-sitemap"></i> EDIT GROUP</b>',
//      content: function(){
//        var self = this;
//        AJAX({
//          url    : app._url + '/' + id + '/edit',
//          dataType: 'HTML',
//          success: function(editUploadAjaxData){
//            self.setContent(editUploadAjaxData);
//            INPUTMASK();
//            var formId = '#groupForm';
//            $(formId).unbind('submit').on('submit', function(){
//              AJAXSUBMIT(formId, {
//                url:   app._url + '/' + id,
//                type: 'PUT',
//                success: function(updateAjaxData){
//                  GRIDREFRESH(app._gridTable);
//                }
//              });
//              return false;
//            });
//          }
//        });
//      },
//      onContentReady: function () {
//        AUTOCOMPLETE();
//      },
//      boxWidth:'90%',
//      buttons: {
//        cancel: {text: 'Close'}
//      }
//    }); 
//  },
//------------------------------------------------------------------------------
  _viewUnrecRecord: function(row){
    var id = row.id;
    if(row.unreconcilable_record){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-university"></i> COMPARE TRANSACTIONS</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._url,
            data: {op: 'modalTable'},
            dataType: 'HTML',
            success: function(modalTableAjaxData){
              self.$content.html(modalTableAjaxData);
                AJAX({
                  url    : app._leftUrl,
                  data: {op: 'column'},
                  success: function(ajaxData){
                    $(app._leftToolbar).html('');
                        
                    GRID({
                      id             : app._leftGridTable,
                      url            : app._leftUrl,
                      columns        : ajaxData.columns,
                      fixedColumns   : false,
                      fixedNumber    : 1,
                      sortName       : 'trust', 
                      reportList     : ajaxData.reportList,
                      clickToSelect  : true,
                    });

                    app._selectedIds[app._leftGridTable] = {};
                        $(app._leftTableBox + ' .bs-bars.pull-left').html('&nbsp;' + ajaxData.button);
                        $(app._leftGridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
                          TOOLTIPSTER('i.tip');
                        });
                        
                        $(app._leftGridTable).on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table',function(e, rows){
                          var type        = e.type;
                          var rows        = Array.isArray(rows) ? rows : [rows];
                          
                          var selectIds   = (app._leftGridTable) in app._selectedIds ? app._selectedIds[app._leftGridTable] : {};
//                          var sumValue    = parseFloat($(app._leftTableBox + ' button span.transSum').text().replace(/\$|\,|(|\)/g,''));
//                          sumValue        = isNaN(sumValue) ? 0.0 : parseFloat(sumValue.toFixed(2));
                          var sumValue    = Helper.extractCurrencyValue($(app._leftTableBox + ' button span.transSum').text());
                          if(type === 'check' || type === 'check-all'){
                            rows.map(function(v){
                              if(!(v['cleared_trans_id'] in selectIds)){
                                var amount      = v['amount'];
                                sumValue       += Helper.extractCurrencyValue(v['amount']);
//                                var isNegative  = amount.match(/\$\((.*)\)/g) != null;
//                                amount          = parseFloat(amount.replace(/(\$|\(|\)|\,|\s+)/g,''));
//                                amount          = isNegative ? (-1 * amount) : amount;
//                                sumValue       += parseFloat(amount.toFixed(2));
                              } 
                              
                              selectIds[v['cleared_trans_id']] = v['cleared_trans_id'];
                            });
                          } else {
                            rows.map(function(v){
                              if(v['cleared_trans_id'] in selectIds){
                                sumValue -= Helper.extractCurrencyValue(v['amount']);
                                  
                                delete selectIds[v['cleared_trans_id']];    
                              }
                              
                            });
                          }
                          
                         
                          app._selectedIds[app._leftGridTable] = selectIds;
                          if(sumValue !== undefined && sumValue !== ''){
                            $(app._leftTableBox + ' button span.transSum').text(Helper.formatUsMoney(sumValue));   
                          }
                         
                        });
                      }
                });
                
                AJAX({
                      url    : app._rightUrl,
                      data: {op: 'column'},
                      success: function(ajaxData){
                        GRID({
                          id             : app._rightGridTable,
                          url            : app._rightUrl,
                          columns        : ajaxData.columns,
                          fixedColumns   : false,
                          fixedNumber    : 1,
                          sortName       : 'trust', 
                          reportList     : ajaxData.reportList,
                          clickToSelect  : true,
                        });
                        
                        app._selectedIds[app._rightGridTable]   = {};
                        $(app._rightTableBox + ' .bs-bars.pull-left').html('&nbsp;' + ajaxData.button);
                        $(app._rightGridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
                          TOOLTIPSTER('i.tip');
                        });
                        
                        $(app._rightGridTable).on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table',function(e, rows){
                          var type        = e.type;
                          var rows        = Array.isArray(rows) ? rows : [rows];
                          
                          var selectIds   = (app._rightGridTable) in app._selectedIds ? app._selectedIds[app._rightGridTable] : {};
//                          var sumValue    = parseFloat($(app._rightTableBox + ' button span.transSum').text());
//                          sumValue        = isNaN(sumValue) ? 0.0 : parseFloat(sumValue.toFixed(2));
                          var sumValue    = Helper.extractCurrencyValue($(app._rightTableBox + ' button span.transSum').text());
                          if(type === 'check' || type === 'check-all'){
                            rows.map(function(v){
                              if(!(v['bank_trans_id'] in selectIds)){
                                var amount      = v['amount'];
                                sumValue       += Helper.extractCurrencyValue(v['amount']);
//                                var isNegative  = amount.match(/\$\((.*)\)/g) != null;
//                                amount          = parseFloat(amount.replace(/(\$|\(|\)|\,|\s+)/g,''));
//                                amount          = isNegative ? (-1 * amount) : amount;
//                                sumValue       += parseFloat(amount.toFixed(2));
                              } 
                              
                              selectIds[v['bank_trans_id']] = v['bank_trans_id'];
                            });   
                          } else {
                            rows.map(function(v){
                              if(v['bank_trans_id'] in selectIds){
                                sumValue -= Helper.extractCurrencyValue(v['amount']);
                                delete selectIds[v['bank_trans_id']];
                              } 
                            });
                          }

                         
                          app._selectedIds[app._rightGridTable] = selectIds;
                          if(sumValue !== undefined && sumValue !== ''){
                            $(app._rightTableBox + ' button span.transSum').text(Helper.formatUsMoney(sumValue));   
                          }
                         
                        });
                      }
                });
            }
          });
        },
        onContentReady: function(){
          app._bindEvent();
        },
        boxWidth: '90%',
        buttons: {
          cancel: {
            text: 'Close',
          }
        }
      });
    }
  },
//------------------------------------------------------------------------------
  _getGridTable: function(tableId,url,sortName){
    
  },
//------------------------------------------------------------------------------
  _bindEvent: function(){
    $('.jconfirm .gridTable').unbind('mousedown').on('mousedown',function(e){
      var id = $(this).attr('id');
      app._checkUncheckRow(id,e.target.parentElement);
      app._isMouseDown = true;
     
    });
    $('.jconfirm .gridTable').unbind('mouseover').on('mouseover',function(e){
      if(app._isMouseDown){
        var id = $(this).attr('id');
        var parentHtml = e.target.parentElement;
        var outerHtml  = parentHtml.outerHTML;
        var isTableRow = outerHtml.match(/\<tr(.*)data-index=\"\d+\"(.*)\>/g) != null;
        if(isTableRow){
          var isChecked = outerHtml.match(/\<tr(.*)class=\"(.*)selected(.*)\"/g) != null;
          var index     = $.parseHTML(parentHtml.outerHTML)[0].dataset.index;
          if(id !== undefined && index !== undefined){
            var checkAction = isChecked ? 'uncheck' : 'check';
            $('#' + id).bootstrapTable(checkAction,index);
          }
        }
      }

    //var xCoord = e.client
    });
    
    $('.jconfirm .gridTable').unbind('mouseup').on('mouseup',function(e){
      var id = $(this).attr('id');
      app._checkUncheckRow(id,e.target.parentElement);
      app._isMouseDown = false;
       //$(this).unbind('mouseover'); 
    });
   
    $('.jconfirm .gridTable').unbind('mouseleave').on('mouseleave',function(e){
      app._isMouseDown = false; 
    });
    
    $('.jconfirm .gridTable *').unbind('dragend').on('dragend',function(){
      app._isMouseDown = false;
    });
//    
//    $('.jconfirm .gridTable').unbind('click').on('click',function(e){
//      console.log($(this));
//      console.log(e);
//    });
  },
//------------------------------------------------------------------------------
  _checkUncheckRow: function(id,domHtml){
    if(domHtml === null || domHtml === undefined){
      return false;    
    }
    var outerHtml   = domHtml.outerHTML;
    var isTableRow  = outerHtml.match(/<tr(.*)data-index=\"\d+\"(.*)\>/g) != null;
    if(isTableRow){
      var isChecked = outerHtml.match(/\<tr(.*)class=\"(.*)selected(.*)\"/g) != null;
      var index     = $.parseHTML(domHtml.outerHTML)[0].dataset.index;
      if(index != undefined){
        var checkAction = isChecked ? 'uncheck' : 'check';
        $('#' + id).bootstrapTable(checkAction,index);
      }
    }
    return isTableRow;
  },
};

// Start to initial the main function
$(function(){ app.main(); });
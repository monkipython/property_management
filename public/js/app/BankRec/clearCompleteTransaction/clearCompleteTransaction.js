var app = {
  _url: '/clearCompleteTrans', 
  _urlIcon: '/groupIcon', 
  _gridTable: '#gridTable',
  _formId:'#clearTransactionForm',
  _urlTrustBankInfo: '/trustBankInfo',
  _isMouseDown: false,
  _selectedIds: {
    '#gridTable': {},
  },
  main: function(){
    this.getHeight();
    this.onArrowClick();
    this.store();
    this.unclear();
  },
//------------------------------------------------------------------------------
  getHeight: function(){
    var _insertHeight = function(){
      var minHeight = 300;
      var rawVal    = $(window).height() - 240;
      var height    =  (rawVal > minHeight) ? rawVal : minHeight;
      $('.gridTable').height(height);
    };
    _insertHeight();
    $(window).resize(function(){
      _insertHeight();
    });
    INPUTMASK();
    AUTOCOMPLETE();
  },
//------------------------------------------------------------------------------
  onArrowClick: function() {
    $('#arrow-btn').on('click', function() {
      var $arrowBtn      = $('#arrow-btn');
      var $formContainer = $('#formContainer');
      var $gridContainer = $('#gridContainer');
      var windowWidth    = window.innerWidth;
      var rotateDeg      = windowWidth > 991 ? '180' : '270';
      if(!$formContainer.hasClass('hideContainer')) {
        // Hide Form
        $formContainer.removeClass('showContainer').addClass('hideContainer');
        $gridContainer.removeClass('col-md-9').addClass('col-md-12');
        $arrowBtn.css({
          "-webkit-transform": "rotate(" + rotateDeg + "deg)",
          "-moz-transform": "rotate(" + rotateDeg + "deg)",
          "-o-transform": "rotate(" + rotateDeg + "deg)",
          "transform": "rotate(" + rotateDeg + "deg)" 
        });
      }else {
        // Show Form
        $gridContainer.removeClass('col-md-12').addClass('col-md-9');
        $formContainer.removeClass('hideContainer').addClass('showContainer');
        $arrowBtn.css({
          "-webkit-transform": "",
          "-moz-transform": "",
          "-o-transform": "",
          "transform": "" 
        });
      }
      // Reset the column header width after change in grid width
      setTimeout(function() {
        $('.table').bootstrapTable('resetView');
      }, 500);
    });
  },
//------------------------------------------------------------------------------
  store: function(){
    var formId = app._formId;
    var $reportBody = $('#reportBody');
    INPUTMASK();    
    //AUTOCOMPLETE()
    app._initAutocomplete();
    $(formId).unbind('submit').on('submit', function(){
      $reportBody.empty();
      $reportBody.append('<table id="gridTable" class="gridTable"></table>');
      
      app._isMouseDown = false;
      AJAX({
        url    : app._url,
        data: {op: 'column'},
        success: function(ajaxData){

          window.operateEvents = {
            'click .edit': function (e, value, row, index) { app.edit(row); }
          };
          GRID({
            id                  : app._gridTable,
            url                 : app._url  + '?' + $(formId).serialize(),
            columns             : ajaxData.columns,
            fixedColumns        : false,
            fixedNumber         : 1,
            sortName            : 'match_id', 
            reportList          : ajaxData.reportList,
            heightButtonMargin  : 210,
            pagination          : false,
            clickToSelect       : true,
            responseHandler     :  function (res) {
              $.each(res.rows, function (i, row) {
                row.checkbox = $.inArray(row.id, []) !== -1;
              });

              return res;
            },
          });
         
          $(app._gridTable).on('post-body.bs.table',function(e,data,value,row,$element){
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
            $('.bs-bars.pull-left').html('&nbsp;' + ajaxData.button);
            app._bindEvent();
            app.unclear();
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


          $(app._gridTable).on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table',function(e, rows){
            var type        = e.type;
            var rows        = Array.isArray(rows) ? rows : [rows];

            var selectIds   = (app._gridTable) in app._selectedIds ? app._selectedIds[app._gridTable] : {};
            var sumValue    = Helper.extractCurrencyValue($('#totalSum').text());
            if(type === 'check' || type === 'check-all'){
              rows.map(function(v){
                var idColumn = v.trans_type == 0 ? 'cleared_trans_id' : 'bank_trans_id';
                var idValue  = v.trans_type + '_' + v[idColumn];
                if(!(idValue in selectIds)){
                  sumValue       += Helper.extractCurrencyValue(v['amount']);
                } 
                selectIds[idValue] = idValue;
              });
            } else {
              rows.map(function(v){
                var idColumn = v.trans_type == 0 ? 'cleared_trans_id' : 'bank_trans_id';
                var idValue  = v.trans_type + '_' + v[idColumn];
                if(idValue in selectIds){
                  sumValue -= Helper.extractCurrencyValue(v['amount']);
                  delete selectIds[idValue];    
                }
              });
            }

            app._selectedIds[app._gridTable] = selectIds;
            if(sumValue !== undefined && sumValue !== ''){
              $('#totalSum').text(Helper.formatUsMoney(sumValue));
              app._adjustTotalColor(sumValue);
            }

            $('#unclear').prop('disabled',!(app._getSelectedIds().length > 0));
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
      return false;
    });  
  },
//------------------------------------------------------------------------------
  unclear: function(){
    $('#unclear').unbind('click').on('click',function(e){
      e.preventDefault();
      
      var selectIds = app._getSelectedIds();
      var reqData   = app._groupSelectedIds(selectIds);
      
      AJAX({
        url: app._url,
        type: 'POST',
        data: reqData,
        success: function(ajaxData){
          $(app._formId).submit();
        }
      });
    });
  },
//------------------------------------------------------------------------------
  _getSelectedIds: function(){
    return Object.values(app._selectedIds[app._gridTable]);  
  },
//------------------------------------------------------------------------------
 _groupSelectedIds: function(ids){
  var requestData     = {
    cleared_trans_id: [],
    bank_trans_id   : [],
  };
  for(var i = 0; i < ids.length; i++){
    var tokens = ids[i].split('_');
    var type   = tokens[0];
    var key    = type == 0 ? 'cleared_trans_id' : 'bank_trans_id';
    var idVal  = tokens[1];
    
    requestData[key].push(idVal);
  }
  
  return requestData;
 },
//------------------------------------------------------------------------------
  _initAutocomplete: function(){
    AUTOCOMPLETE({callback:function(dataset){
        if(dataset.value !== undefined){
          var trustValue = dataset.value.replace(/(.*)\s+\(Prop:(.*)\)/g,'$1');
          app._getBank(trustValue);
        }
      }
    });
  },
//------------------------------------------------------------------------------
  _getBank: function(trust){
    AJAX({
      url: app._urlTrustBankInfo,
      data: {trust:trust},
      success: function(ajaxData){
        $('#bank').html(ajaxData.html);
      }
    });
  },
//------------------------------------------------------------------------------
  _bindEvent: function(){
    $('#unclear').prop('disabled',true);
      $(app._gridTable).unbind('mousedown').on('mousedown',function(event){
        app._checkUncheckRow(event.target.parentElement);  
        app._isMouseDown = true;
      });  
    
      $(app._gridTable).unbind('mouseover').on('mouseover',function(e){
        e.preventDefault();
        if(app._isMouseDown){
          var parentHtml = e.target.parentElement;
          var outerHtml  = parentHtml.outerHTML;
          var isTableRow = outerHtml.match(/\<tr(.*)data-index=\"\d+\"(.*)\>/g) != null;
          if(isTableRow){
            var isChecked = outerHtml.match(/\<tr(.*)class=\"(.*)selected(.*)\"/g) != null;
            var index     = $.parseHTML(parentHtml.outerHTML)[0].dataset.index;
            
            if(index !== undefined){
              var checkAction = isChecked ? 'uncheck' : 'check';
              $(app._gridTable).bootstrapTable(checkAction,index);
            }
          }         
        }
      });
      
      $(app._gridTable + ' *').unbind('dragend').on('dragend',function(){
        app._isMouseDown = false; 
      });
      
      $(app._gridTable).unbind('mouseup').on('mouseup',function(evt){
        app._isMouseDown = false;
        app._checkUncheckRow(evt.target.parentElement);
          //$(window).unbind('mouseover'); 
      });

      $(app._gridTable).unbind('mouseleave').on('mouseleave',function(e){
        app._isMouseDown = false;
      });

  },
//------------------------------------------------------------------------------
  _checkUncheckRow: function(domHtml){
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
        $(app._gridTable).bootstrapTable(checkAction,index);
      }
    }
    return isTableRow;
  },
  _adjustTotalColor: function(value){
    var removeClass = value >= 0 ? 'text-red' : 'text-green';
    var addClass    = value >= 0 ? 'text-green' : 'text-red';
    $('#totalSum').removeClass(removeClass);
    $('#totalSum').addClass(addClass);
  }
};

// Start to initial the main function
$(function(){ app.main(); });
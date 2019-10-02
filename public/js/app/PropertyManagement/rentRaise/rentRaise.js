var app = {
  _url : '/rentRaise',
  _gridTable : '#gridTable',
  _urlReport: '/rentRaiseExport',
  _urlUpload: '/uploadRentRaise',
  _urlPastNotice: '/pastRentRaiseNotice',
  _keyCol   : 'tenant_id',
  _useUncheckModal: true,
  _callAjaxOnCheck: true,
  _numPending : 0,
  _currentUser: '',
  _onPendingView: false,
  _scrollPosition: 0,
  _selectedIds : {},
  _foreignIdMap: {},
  main: function(){
    app._bindEvent();
    this.grid();
    this.storeSubmitRentRaise();
    this.help();
  },
/*
################################################################################
########################## EVENT HANDLER FUNCTIONS    ##########################
################################################################################
*/
  _bindEvent: function(){
    var locationParams  = window.location.href.replace(/(.*)\?(.*)/g,'$2');
    var onPending       = locationParams.match(/isCheckboxChecked=1/g);
    app._onPendingView  = onPending != null;
    
    var currentBtn      = app._onPendingView ? '#pendingRentRaise' : '#viewAllRentRaise';
    var infoBtn         = app._onPendingView ? '#viewAllRentRaise' : '#pendingRentRaise';
    $(currentBtn).removeClass('btn-info');
    $(currentBtn).addClass('btn-danger');
    $(infoBtn).removeClass('btn-danger');
    $(infoBtn).addClass('btn-info');
    
    $('#pendingRentRaise').unbind('click').bind('click',function(){
      var location = window.location.href.replace(/(.*)\?(.*)/g,'$1');
      location += ('?' + $.param({isCheckboxChecked:1}));
      window.location.href = location;
    });
    
    $('#viewAllRentRaise').unbind('click').bind('click',function(){
     var location         = window.location.href.replace(/(.*)\?(.*)/g,'$1');
     window.location.href = location;
    });
    
    $('span.clickable[data-hidden-id]').unbind('click').on('click',function(){
      var rentRaiseId = $(this).attr('data-hidden-id');
      var tenantId    = $(this).attr('data-hidden-tenant-id');
      
      app._viewPastNotice(tenantId,rentRaiseId);
    });
  },
/*
################################################################################
########################## GRID TABLE FUNCTIONS    #############################
################################################################################
*/
//------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(ajaxData){
        window.operateEvents = {
          
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          urlReport      : app._urlReport,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'group1',
          uniqueId       : app._keyCol,
          reportList     : ajaxData.reportList, 
          //dataReport     : app._gatherCheckedRows,
          isOpInUrlReport: true,
          dateColumns    : ['move_in_date','submitted_date','effective_date'],
          pageSize       : 25,
          pageList       : [25,50, 100,200],
          responseHandler:  function (res) {
            app._numPending     = res.numChecks;
            app._selectedIds    = res.selectedIds;
            $('#pendingRentRaise').prop('disabled',app._numPending <= 0 ? true : false);
            $('#updateTable').css('visibility',Object.keys(app._selectedIds).length <= 0 ? 'hidden' : 'visible');
            app._adjustPendingButtonLabel();

            $.each(res.rows, function (i, row) {
              row.checkbox = $.inArray(row.id, []) !== -1;
            });
            return res;
          },
        });
        
        $(app._gridTable).on('editable-save.bs.table',function(e,name,args,oldValue,$el){
          var id       = args['rent_raise_id'];
          var reqData  = app._setValues([args],name,args[name]);
          reqData      = app._getSelectedValues(reqData,[app._keyCol,name]);
          AJAX({
            url    :  app._url + '/' + id,
            type   : 'PUT',
            data   : reqData,
            success: function(responseData){
              if(responseData.error !== undefined){
                $el[0].innerHTML = oldValue;
                if(responseData.error.popupMsg !== undefined){
                  ALERT({content:responseData.error.popupMsg});
                }
              } else {
                var position = $(app._gridTable).bootstrapTable('getScrollPosition');
                app._scrollPosition = position;
                GRIDREFRESH(app._gridTable);
              }
            }
          });
        });
        
        $(app._gridTable).on('pre-body.bs.table', function(e,data){
          for(var i = 0; i < data.length; i++){
            var checked             = data[i]['needsCheck'];
            data[i]['checkbox']     = checked;
          } 
          $('#updateTable').css('display',app._onPendingView ? 'inline-block' : 'none');
          $('#updateTable').prop('disabled',app._onPendingView ? false : true);
        });

        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {  
          TOOLTIPSTER('i.tip');
          app._bindEvent();
          $(app._gridTable).bootstrapTable('scrollTo',app._scrollPosition);
          app._scrollPosition = 0;
        });
        
        $(app._gridTable).on('click-cell.bs.table', function(e,field,value,row,$element){
          switch(field){
            case 'allFile': app._viewAllNotices(row); break;
          }    
          
        });
        
        $(app._gridTable).on('check.bs.table check-all.bs.table',function(e,rows){
          rows          = Array.isArray(rows) ? rows : [rows];
          var numPending= app._numPending;
          for(var i = 0; i < rows.length; i++){
            numPending                             = rows[i][app._keyCol] in app._selectedIds ? numPending : numPending + 1;
            app._selectedIds[rows[i][app._keyCol]] = rows[i][app._keyCol];
          }

          var reqData   = app._getSelectedValues(app._setValues(rows,'isCheckboxChecked',1),[app._keyCol,'isCheckboxChecked']);
          var id        = reqData['tenant_id'][0];
          if(app._callAjaxOnCheck){
            AJAX({
              url     : app._url + '/' +  id,
              type    : 'PUT',
              data    : reqData,
              success : function(responseData){
                if(responseData.error !== undefined && responseData.error.popupMsg !== undefined){
                  ALERT({content:responseData.error.popupMsg});
                  var ids  = app._getSelectedValues(rows,[app._keyCol])[app._keyCol];
                  for(var i = 0; i < ids.length; i++){
                    app._useUncheckModal = false;
                    $(app._gridTable).bootstrapTable('uncheckBy',{field:app._keyCol,values:[ids[i]]});
                  }
                } else {
                  $('#pendingRentRaise').prop('disabled',false);
                  $('#updateTable').prop('disabled',false);
                  $('#updateTable').css('display',app._onPendingView ? 'inline-block' : 'none');
                  if(responseData.insertIds !== undefined && typeof(responseData.insertIds) === 'object'){
                    for(var k in responseData.insertIds){
                      var value = responseData.insertIds[k];
                      app._foreignIdMap[k] = value;
                    }
                  }
                  app._numPending = numPending;
                  app._adjustPendingButtonLabel();  
                }
              
              }
            });
          } else {
            app._adjustPendingButtonLabel();
          }
          app._callAjaxOnCheck = true;
        });
        
        $(app._gridTable).on('uncheck.bs.table uncheck-all.bs.table',function(e,rows){
          rows          =  Array.isArray(rows) ? rows : [rows];
          var numPending= app._numPending;
          rows.map(function(v){numPending = v[app._keyCol] in app._selectedIds ? numPending - 1 : numPending; delete app._selectedIds[v[app._keyCol]];});
          var reqData   = app._getSelectedValues(app._setValues(rows,'isCheckboxChecked',0),[app._keyCol,'isCheckboxChecked']);
          var id        = reqData[app._keyCol][0];
          if(app._useUncheckModal){
            var self      = CONFIRM({
              title   : '<b class="text-red"><i class="fa fa-calendar-times"></i> REMOVE PROSPECTIVE RENT INCREASES</b>',
              content : 'Are you sure wish to remove these tenants from your prospective rent raise(s)',
              buttons : {
                confirm: {
                  text: 'Remove',
                  btnClass: 'btn-danger',
                  action: function(){
                    app._uncheckAjaxCall(id,reqData,numPending,self);
                  }
                },
                cancel: {
                  text: 'Close',
                  action: function(){
                    var ids = app._getSelectedValues(rows,[app._keyCol])[app._keyCol];
                    for(var i = 0; i < ids.length; i++){
                      app._callAjaxOnCheck = false;
                      $(app._gridTable).bootstrapTable('checkBy',{field:app._keyCol,values:[ids[i]]});
                    }
                  }
                },
              },
            });
          } else {
            app._uncheckAjaxCall(id,reqData);
          }
          app._useUncheckModal = true;
        });
        $(app._gridTable).on('editable-shown.bs.table', function(field, row, $el) {
          setTimeout(function(){ 
            $('.editable-input input').select();
          });
        });
      }
    });
  },
//------------------------------------------------------------------------------
  storeSubmitRentRaise: function(){
    $('#updateTable').unbind('click').on('click',function(e){
      e.preventDefault();
      var tData      = app._getIdSelections(); 
      CONFIRM({
        title    : '<b class="text-red"><i class="fa fa-fw fa-clipboard"></i> SUBMIT RENT RAISE</b>',
        content  : 'Are you sure wish to submit a rent raise for the checked item(s) ?',
        boxWidth : '500',
        buttons  : {
          confirm : {
            text   : 'Submit Rent Raise(s)',
            action : function(){
              var reqData = {tenant_id:tData.join(',')};
              AJAX({
                url    : app._url,
                type   : 'POST',
                data   : reqData,
                success: function(ajaxData){
                  if(ajaxData.error !== undefined){
                    if(ajaxData.error.popupMsg !== undefined){
                      ALERT({content:ajaxData.error.popupMsg});
                    } else {
                      CONFIRM({
                        title: '<b class="text-red"><i class="fa fa-times-circle"></i> ERROR</b>', 
                        content:ajaxData.error.msg,
                        buttons: {
                          cancel: {text: 'Close'}
                        }
                      }); 
                    }
                    
                  } else {
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
                    var position = $(app._gridTable).bootstrapTable('getScrollPosition');
                    app._scrollPosition = position;
                    GRIDREFRESH(app._gridTable);
                  }
                }
              });                    
            }
          },
          cancel  : {
            text: 'Cancel'
          },
        }
      });    
    });
  },
//------------------------------------------------------------------------------
  help: function(){
    $('#rentRaiseHelp').unbind('click').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-question-circle"></i> RENT RAISE HELP</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._url,
            data: {op:'help'},
            success: function(ajaxData){
              self.$content.html(ajaxData.html);
            }
          });
        },
        boxWidth: '90%',
        buttons: {
          cancel: {
            text: 'Close',
          }
        }
      });
    }); 
  },
/*
################################################################################
########################## HELPER FUNCTIONS    #################################
################################################################################
*/
  _uncheckAjaxCall: function(id,requestData,numberPending,confirmModal){
    if(numberPending !== undefined){
    AJAX({
      url      : app._url + '/' + id,
      type     : 'PUT',
      data     : requestData,
      success  : function(responseData){
        app._numPending = numberPending;
        app._adjustPendingButtonLabel();
        var checkedIdCount = Object.keys(app._selectedIds).length;
        $('#pendingRentRaise').prop('disabled',app._numPending <= 0 ? true : false);
        $('#updateTable').prop('disabled',checkedIdCount <= 0 ? true : false);
        $('#updateTable').css('display',app._onPendingView && checkedIdCount > 0 ? 'inline-block' : 'none');
        if(confirmModal !== undefined){
          confirmModal.close();
        }
      }
    });
    } else {
      app._adjustPendingButtonLabel();
      var checkedIdCount = Object.keys(app._selectedIds).length;
      $('#pendingRentRaise').prop('disabled',app._numPending <= 0 ? true : false);
      $('#updateTable').prop('disabled',checkedIdCount <= 0 ? true : false);
      $('#updateTable').css('display',app._onPendingView && checkedIdCount > 0 ? 'inline-block' : 'none');
    }
  },
//------------------------------------------------------------------------------
  _gatherCheckedRows: function(){
      var selections = app._getIdSelections();
      var queryStr   = '';
      var index      = 0;
      for(var i = 0; i < selections.length; i++){
        var token =  selections[i] != 0 ? 'tenant_id[' + (index++) + ']=' + selections[i] : '';
        queryStr += token;
        queryStr += (token != undefined && token.length > 0 && i < (selections.length - 1)) ? '&' : '';  
      }
      return queryStr.length > 0 ? queryStr + '&includePopup=true' : undefined;
  },
//------------------------------------------------------------------------------
  _getIdSelections: function() {
    return Object.values(app._selectedIds);
  },
//------------------------------------------------------------------------------
  _getSelectedValues: function(selected,columns){
    var columnVals   = {};
    for(var i = 0; i < selected.length; i++){
      for(var j = 0; j < columns.length; j++){
        columnVals[columns[j]] = (columns[j] in columnVals) ? columnVals[columns[j]] : [];
        if(columns[j] === 'rent_raise_id'){
          var id        = selected[i]['rent_raise_id'];
          var tenantId  = selected[i][app._keyCol];
          id            = id == 0 ? ((tenantId in app._foreignIdMap && app._foreignIdMap[tenantId] > 0) ? app._foreignIdMap[tenantId] : id): id;
          columnVals[columns[j]].push(id);
        } else {
          columnVals[columns[j]].push(selected[i][columns[j]]);
        }
      }
    }
    return columnVals;
  },
//------------------------------------------------------------------------------
  _setValues: function(selected,name,value){
    return selected.map(function(v){v[name] = value;return v;});
  },
//------------------------------------------------------------------------------
  _applyChangeToSelected : function(selected,tableId,keyCol,field,value){
    for(var i = 0; i < selected.length; i++){
        selected[i][field] = value;
        var keyId          = selected[i][keyCol];
        $(tableId).bootstrapTable('updateByUniqueId',{id:keyId,row:selected[i]});
    }
    return selected;
  },
//------------------------------------------------------------------------------
  _validateSelection: function(selected){
    var missing = selected.filter(function(item){return item['rent_raise_id'] == 0;});
    return missing.length <= 0;
  },
//------------------------------------------------------------------------------
  _adjustPendingButtonLabel: function(){
    var btnText       = $('#pendingRentRaise').text();
    var newText       = btnText.replace(/\((\d+)\)$/g,'(' + app._numPending  + ')');
    $('#pendingRentRaise').text(newText);
  },
//------------------------------------------------------------------------------
  _viewAllNotices: function(row){
    if(row.allFile){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-download"></i> VIEW ALL NOTICES</b>',
        content: function(){
          var self = this;
          AJAX({
            url      : app._urlUpload,
            data     : {id:row.tenant_id},
            dataType : 'HTML',
            success  : function(ajaxData){
              self.$content.html(ajaxData);
            }
          });
        },
        boxWidth: '90%',
        buttons: {
          cancel: {
            text: 'Close',
          }  
        },
        onContentReady: function(){
          AUTOCOMPLETE();
          INPUTMASK();
        }
      });
    }
  },
//------------------------------------------------------------------------------
  _viewPastNotice: function(tenantId,rentRaiseId){
    AJAX({
      url  : app._urlPastNotice,
      data : {tenant_id:tenantId,rent_raise_id:rentRaiseId},
      success: function(ajaxData){
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
    });
  }
};
// Start to initial the main function
$(function(){ app.main(); });

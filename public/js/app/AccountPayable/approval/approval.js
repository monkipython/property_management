var app = {
  _url: '/approval', 
  _urlBankInfo: 'accountPayableBankInfo',
  _index:     'vendor_payment_view', 
  _urlUpload: '/uploadApproval',
  _urlDropdown:'/approvalRequestDropdown',
  _gridTable: '#gridTable',
  _allButton: '#destroy,#approved,#request,#rejected,#print,#printCashierCheck,#record',
  _allCheckboxId: '', 
  main: function(){
    app._isDisabledButton(true, '#destroy');
    app._fetchRequestDropdown();
    this.grid();
    this._buttonEventHandler();
    
//    this._destroy();
//    this._storeApprove();
//    this._storeReject();
//    this._storeSend();
//    this._storePrint();
  },
//------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(ajaxData){
        GRID({
          id             : app._gridTable,
          url            : app._url,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'prop',
          dateColumns    : ['invoice_date']
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id = args.id;
          var sData = {};
          sData[name] = args[name];
          sData.prop = args.prop;
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
        $(app._gridTable).on('click-cell.bs.table', function (e, field, value, row, $element) {
          if(field == 'invoiceFile'){
            app._viewInvoice(row); 
          }
        });
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('.tip');
          if(data.approveSum !== undefined){
            $('#approveSum').text(Helper.formatUsMoney(data.approveSum));
          }
        });
        
        $(app._gridTable).on('post-body.bs.table',function(e,data){
          var tData = $(app._gridTable).bootstrapTable('getData');
          $.map(tData, function(row) {
            var bankData = [];
            var bankKeys = Object.keys(row.bankList);
            var bankValues = Object.values(row.bankList);
            for(var i = 0; i < bankKeys.length; i++) {
              bankData.push({value: bankKeys[i], text:'('+ bankKeys[i] + ') ' + bankValues[i]});
            }
            row.bankList = bankData;
            return row;
          });
          
          $.each(tData,function(i,row){
            var elem = $('tr[data-index=' + i + '] td a[data-name="bank"]');
            elem.editable({
              type: 'select',
              source: row.bankList
            });
          }); 
        });
        
        $(app._gridTable).on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function(){
          $('#destroy').prop('disabled', !$(app._gridTable).bootstrapTable('getSelections').length);
        });
      }
    });
  },
//------------------------------------------------------------------------------
  _buttonEventHandler: function(){
    $(app._allButton).on('click', function(){
      var id = $(this).attr('id');
//      app._isDisabledButton(true);
      var rowId  = app._getIdSelections();
      var data = {};
      switch (id) {
        case 'approved'           : data = app._storeApproveReject('Approved'); break;
        case 'rejected'           : data = app._storeApproveReject('Rejected'); break;
        case 'request'            : data = app._storeRequest(); break;
        case 'print'              : data = app._storePrint(); break;
        case 'printCashierCheck'  : data = app._storePrintCashierCheck(); break;
        case 'record'             : data = app._storeRecord(); break;
        case 'destroy'            : data = app._destroy(); break;
      }
      
      CONFIRM({
        title: data.title,
        boxWidth: '625px',
        content: function(){
          var self = this;
          AJAX({
            url : data.url + '/create',
            data: {num: rowId.length, approvalOrReject: id},
            success: function(createAjaxData){
              self.$content.html(createAjaxData.html);
              INPUTMASK();
            }
          });
        },
        buttons: {
          confirm: {
            text: '<i class="fa fa-check"></i> Confirm',
            action: function(){
              data.action({
                id: ($('#numTransaction').val() == 0 ? 0 : rowId), 
                remark: $('#remark').val()
              });
            },
            btnClass: 'btn-success',
          },
          cancel: {
            text: '<i class="fa fa-fw fa-close"></i> Cancel',
            action: function(){
//              app._isDisabledButton(false);
            },
            btnClass: 'btn-danger',
          }
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  _fetchRequestDropdown: function(){
    var dropdownId = '#requestDropdownList';
    AJAX({
      url: app._urlDropdown,
      success: function(ajaxData){
        var buttonHtml = ajaxData.html;
        $(dropdownId).html(buttonHtml);
      }
    });
  },
//------------------------------------------------------------------------------
  _storeApproveReject: function(approvedOrRejected){
    var ajaxUrl = '/approveOrReject';
    return {
      title: '<b class="text-red"><i class="fa fa-fw fa-check-square-o"></i> APPROVAL CONFIRMATION</b>',
      url:   ajaxUrl,
      action:   function(data, self){
        var urlHash = (url('?') !== undefined) ? url('?') : {};
        if(urlHash.batch_group){
          data.batch_group = urlHash.batch_group;
        }
        data.approvedOrRejected = approvedOrRejected;
        
        AJAX({
          url : ajaxUrl,
          type: 'POST',
          data: data,
          success: function(storeAjaxData){
            Helper.displayError(storeAjaxData);
            if(!storeAjaxData.error){
              app._fetchRequestDropdown();
              GRIDREFRESH(app._gridTable);
              CONFIRM({
                title: '<b class="text-red"><i class="fa fa-fw fa-check-square-o"></i> CONFIRMATION</b>',
                boxWidth: '625px',
                content: storeAjaxData.html,
                buttons: {
                  cancel: {
                    text: '<i class="fa fa-trash"></i> Close',
                    btnClass: 'btn-danger',
                  }
                }
              });
//              self.$content.html(storeAjaxData.html);
            }
          }
        });
      }
    };
  },
//------------------------------------------------------------------------------
  _storeRequest: function(){
    var ajaxUrl = '/requestApproval';
    return {
      title: '<b class="text-red"><i class="fa fa-fw fa-check-square-o"></i> APPROVAL REQUEST CONFIRMATION</b>',
      url:   ajaxUrl,
      action:   function(data, self){
        AJAX({
          url : ajaxUrl,
          type: 'POST',
          data: data,
          success: function(storeAjaxData){
            Helper.displayError(storeAjaxData);
            if(!storeAjaxData.error){
              app._fetchRequestDropdown();
              GRIDREFRESH(app._gridTable);
              CONFIRM({
                title: '<b class="text-red"><i class="fa fa-fw fa-check-square-o"></i> CONFIRMATION</b>',
                boxWidth: '625px',
                content: storeAjaxData.html,
                buttons: {
                  cancel: {
                    text: '<i class="fa fa-trash"></i> Close',
                    btnClass: 'btn-danger',
                  }
                }
              });
//              self.$content.html(storeAjaxData.html);
              
            }
          }
        });
      }
    };
  },
//------------------------------------------------------------------------------
  _storePrint: function(){
    var ajaxUrl = '/printCheck'; 
    return {
      title:    '<b class="text-red"><i class="fa fa-trash"></i> PRINT CHECK CONFIRMATION</b>',
      url: ajaxUrl,
      action:   function(data, self){
        data.posted_date = $('#posted_date').val(),
        data.printBy = $('#printBy').val(),
        AJAX({
          url : ajaxUrl,
          type: 'POST',
          data: data,
          success: function(storeAjaxData){
            Helper.displayError(storeAjaxData);
            if(!storeAjaxData.error){
              app._fetchRequestDropdown();
              app._displayDownload(storeAjaxData);
              GRIDREFRESH(app._gridTable);
            }
          }
        });
      }
    };
  },
//------------------------------------------------------------------------------
  _storePrintCashierCheck: function(){
    var ajaxUrl  = '/printCashierCheck';
    return {
      title: '<b class="text-red"><i class="fa fa-trash"></i> PRINT CASHIER CHECK CONFIRMATION</b>',
      url  : ajaxUrl,
      action: function(data,self){
        data.date1  = $('#date1').val();
        AJAX({
          url     : ajaxUrl,
          type    : 'POST',
          data    : data,
          success : function(storeAjaxData){
            Helper.displayError(storeAjaxData);
            if(!storeAjaxData.error){
              GRIDREFRESH(app._gridTable);
              app._fetchRequestDropdown();
            }
          }
        });
      },
    };
  },
//------------------------------------------------------------------------------
  _storeRecord: function(){
    var ajaxUrl = '/record';
    return {
      title   : '<b class="text-red"><i class="fa fa-edit"></i> RECORD CHECK CONFIRMATION</b>',
      url     : ajaxUrl,
      action  : function(data,self){
        data.date1 = $('#date1').val();
        AJAX({
          url   : ajaxUrl,
          type  : 'POST',
          data  : data,
          success: function(storeAjaxData){
            Helper.displayError(storeAjaxData);
            if(!storeAjaxData.error){
              app._fetchRequestDropdown();
              GRIDREFRESH(app._gridTable);
            }
          }
        });
      }
    };
  },
//------------------------------------------------------------------------------
  _destroy: function(rowId) {
    return {
      title:    '<b class="text-red"><i class="fa fa-trash"></i> DELETE CHECK CONFIRMATION</b>',
      url: app._url, 
      action:   function(data, self){
        AJAX({
          url : app._url + '/delete',
          type: 'DELETE',
          data: data,
          success: function(){
            app._fetchRequestDropdown();
            GRIDREFRESH(app._gridTable);
          }
        });
      }
    };
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _isDisabledButton: function(boolean, id){
    var id = id || app._allButton;
    $(id).prop('disabled', boolean);
  },
  _initAutocomplete: function(){
    AUTOCOMPLETE({callback: function(dataSet){
      if(dataSet.append_prop !== undefined){
        app._getBank();
      }
    }});
  },
//------------------------------------------------------------------------------
  _getBank: function(){
    AJAX({
      url   : app._urlBankInfo,
      data  : {prop: $('#prop').val()}, 
      success: function(createAjaxData){
        $('#bank').html(createAjaxData.html);
      }
    });
  }, 
//------------------------------------------------------------------------------
  _viewInvoice: function(row){
    var id = row.id;
    if(row.invoiceFile){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW INVOICE</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlUpload,
            data    : {id: id},
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              self.$content.html(editUploadAjaxData);
            }
          });
        },
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        }
      }); 
    }
  },
//------------------------------------------------------------------------------
  _getIdSelections: function() {
    return $.map($(app._gridTable).bootstrapTable('getSelections'), function (row) {
      return row.id;
    });
  },
//------------------------------------------------------------------------------
  _displayDownload(storeAjaxData){
    CONFIRM({
      title: '<b class="text-red">DOWNLOAD</b>',
      content: storeAjaxData.popupMsg,
      boxWidth: '500px',
      buttons: {
        cancel: {
          text: '<i class="fa fa-fw fa-close"></i> Close',
          btnClass: 'btn-red',
        }
      }
    });
  }
};

// Start to initial the main function
$(function(){ app.main(); });
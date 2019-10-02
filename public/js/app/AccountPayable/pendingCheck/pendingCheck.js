var app = {
  _url: '/pendingCheck', 
  _urlIcon: '/pendingCheckIcon',
  _urlApproval: '/approvePendingCheck',
  _urlUploadPendingCheck: '/pendingCheckUpload',
  _urlBankInfo: 'accountPayableBankInfo',
  _index:     'vendor_pending_check_view', 
  _urlUpload: '/uploadPendingCheck',
  _gridTable: '#gridTable',
  _idCol: 'vendor_pending_check_id',
  _selectedIds:{},
  main: function(){
    this.grid();
    this.create();
    this.delete();
    this.sendApproval();
    this.uploadPendingCheck();
  },
//------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(ajaxData){
        window.operateEvents = {
          'click .edit': function (e, value, row, index) { app.edit(row); },
          'click .remove': function (e, value, row, index) {
            $(app._gridTable).bootstrapTable('remove', {
              field: 'id',
              values: [row.id]
            });
          }
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'vendid',
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
              GRIDREFRESH(app._gridTable);
            }
          });
        });
        $(app._gridTable).on('click-cell.bs.table', function (e, field, value, row, $element) {
          if(field == 'invoiceFile'){
            app._viewInvoice(row); 
          }
        });
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
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
        $(app._gridTable).on('check.bs.table uncheck.bs.table ' +
          'check-all.bs.table uncheck-all.bs.table',
          function (e,rows) {
            var type      = e.type;
            rows          = Array.isArray(rows) ? rows : [rows];
            if(type === 'check' || type === 'check-all') {
              rows.map(function(v){app._selectedIds[v[app._idCol]] = v[app._idCol];});
            } else {
              rows.map(function(v){delete app._selectedIds[v[app._idCol]];});
            }
            $('#delete, #sendApproval').prop('disabled', !$(app._gridTable).bootstrapTable('getSelections').length)
            // save your data, here just save the current page
            selections = app._getIdSelections();
            // push or splice the selections if you want to save all data selections
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
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> EDIT PENDING CHECK</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            //AUTOCOMPLETE();
            
            INPUTMASK();
            UPLOAD(
              {},
              app._urlUpload, 
              {op:'PendingCheck', type:'pending_check', foreign_id: id}
            );
            var formId = '#pendingCheckForm';
            $(formId).unbind('submit').on('submit', function(){
              AJAXSUBMIT(formId, {
                url:   app._url + '/' + id,
                type: 'PUT',
                success: function(updateAjaxData){
                  GRIDREFRESH(app._gridTable);
                }
              });
              return false;
            });
          }
        });
      },
      onContentReady: function () {
//        AUTOCOMPLETE();
        app._initAutocomplete();
      },
      boxWidth:'90%',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
//------------------------------------------------------------------------------
  create: function() {
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> CREATE PENDING CHECK</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              AUTOCOMPLETE();
              INPUTMASK();
              UPLOAD(
                {},
                app._urlUpload, 
                {op:'PendingCheck', type:'pending_check'}
              );
              var formId = '#pendingCheckForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url, 
                  type: 'POST',
                  success: function(updateAjaxData){
                    self.setBoxWidth('500px');
                    self.$content.html(updateAjaxData.msg);
                    GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
          });
        },
        onContentReady: function () {
          app._initAutocomplete();
        },
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
    });
  },
//------------------------------------------------------------------------------
  delete: function() {
    var $delete = $('#delete');
    $delete.prop('disabled', true);
    $delete.on('click', function() {
      var ids = app._getIdSelections();
      var sData = {'id': ids};
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE PENDING CHECK</b>',
        content: ' Are you sure you want to delete ' + ids.length + ' Pending Check(s).',
        buttons: {
          delete: {
            text  : 'Delete',
            action: function(){
              AJAX({
                url : app._url + '/' + ids[0],
                type: 'DELETE',
                data: sData,
                success: function(data){
                  $delete.prop('disabled', true);
                  GRIDREFRESH(app._gridTable);
                }
              });
            },
            btnClass: 'btn-danger',
          },
          cancel: {
            text: 'Cancel',
          }
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  sendApproval: function(){
    var $sendApproval = $('#sendApproval');
    $sendApproval.prop('disabled',true);
    $sendApproval.on('click',function(){
      var ids    = app._getIdSelections();
      var sData  = {'vendor_pending_check_id':ids};
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-mail"></i> SUBMIT FOR APPROVAL</b>',
        content: 'Are you sure you want to submit these ' + ids.length + ' Pending Check(s) for Approval.',
        buttons: {
          approve: {
            text : 'Submit',
            action: function(){
              AJAX({
                url   :  app._urlApproval,
                type  : 'POST',
                data  : sData,
                success: function(data){
                  $sendApproval.prop('disabled',true);
                  GRIDREFRESH(app._gridTable);
                }
              });
            }
          },
          cancel: {
            text : 'Cancel',
          }
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  uploadPendingCheck: function(){
    $('#uploadPending').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-upload"></i> UPLOAD PENDING CHECK FILE(S)</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._urlUploadPendingCheck + '/create',
            success: function(ajaxData){
              self.$content.html(ajaxData.html);
              UPLOAD(
                  {},
                  app._urlUploadPendingCheck,
                  {
                    op: 'PendingCheck',
                    type: 'pending_check',
                    onComplete: function(responseJSON){
                      if(responseJSON !== undefined){
                        if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
                          ALERT({content: responseJSON.error.popupMsg});
                        } else{
                          $('#sideMsg').prepend(responseJSON.sideMsg);
                          GRIDREFRESH(app._gridTable);
                        }
                      }
                    }
                  },
              );
            }
          });
        },
        boxWidth: '80%',
        buttons: {
          cancel: {text:'Close'},
        }
      });
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _initAutocomplete: function(){
    AUTOCOMPLETE({callback: function(dataSet){
      if(dataSet.append_prop !== undefined){
//        $('#unit').val('');
//        $('#tenant,#tenantInfo').html('');
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
//    return $.map($(app._gridTable).bootstrapTable('getSelections'), function (row) {
//      return row.id;
//    });
    return Object.values(app._selectedIds);
  }
};

// Start to initial the main function
$(function(){ app.main(); });
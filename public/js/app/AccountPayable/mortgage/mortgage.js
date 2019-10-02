var app = {
  _url: '/mortgage',
  _urlUpload: '/uploadMortgage',
  _urlUploadMortgage:'/mortgageUpload',
  _urlReportList: '/mortgageExport',
  _urlApproval:'/approveMortgage',
  _urlBankInfo: 'accountPayableBankInfo',
  _gridTable: '#gridTable',
  _selectedIds: {},
//------------------------------------------------------------------------------
  main: function(){
    this.grid();
    this.create();
    this.delete();
    this.sendApproval();
    this.uploadMortgage();
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
          urlReport      : app._urlReportList,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'vendid',
          reportList     : ajaxData.reportList,
          dateColumns    : ['loan_date'],
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
          TOOLTIPSTER('i.tip');
        });

        Helper.bindTableDynamicSelect(app._gridTable,'bank','bankList');

        $(app._gridTable).on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table',
          function (e,rows) {
            var type      = e.type;
            rows          = Array.isArray(rows) ? rows : [rows];
            if(type === 'check' || type === 'check-all') {
              rows.map(function(v){app._selectedIds[v.vendor_mortgage_id] = v.vendor_mortgage_id;});
            } else {
              rows.map(function(v){delete app._selectedIds[v.vendor_mortgage_id];});
            }
            $('#delete').prop('disabled', !$(app._gridTable).bootstrapTable('getSelections').length);      
            // save your data, here just save the current page
            // push or splice the selections if you want to save all data selections
        });
        $(app._gridTable).on('editable-shown.bs.table', function(field, row, $el) {
          setTimeout(function(){ 
            $('.editable-input input').select();
          });
        });
        AUTOCOMPLETE();
        INPUTMASK();
      }
    });
  },
//------------------------------------------------------------------------------
  edit: function(row){ 
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> EDIT MORTGAGE</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            AUTOCOMPLETE();
            UPLOAD(
              {},
              app._urlUpload,
              {op:'Mortgage',type:'mortgage',foreign_id:id}
            );
            var formId = '#mortgageForm';
            $(formId).unbind('submit').on('submit', function(){
              AJAXSUBMIT(formId, {
                url:   app._url + '/' + id,
                type: 'PUT',
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
  },
//------------------------------------------------------------------------------
  create: function(){
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> CREATE MORTGAGE</b>',
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
                {op:'Mortgage',type:'mortgage'}
              );
              var formId = '#mortgageForm';
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
  delete: function(){
    var $delete = $('#delete');
    $delete.prop('disabled', true);
    $delete.on('click', function() {
      var ids = app._getIdSelections();
      var sData = {'id': ids};
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE MORTGAGE</b>',
        content: ' Are you sure you want to delete ' + ids.length + ' mortgage(s).',
        buttons: {
          delete: {
            text  : '<i class="fa fa-fw fa-trash"></i> Delete',
            btnClass : 'btn-danger',
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
          },
          cancel: {
            text: 'Close',
          }
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  sendApproval: function(){
    var $sendApproval = $('#sendApproval');
    $sendApproval.on('click',function(){
      var ids = app._getIdSelections();
      var sData  = {'vendor_mortgage_id':ids};
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-mail"></i> SUBMIT FOR APPROVAL</b>',
        boxWidth: '650px',
        content: function(){
          var self = this;
          AJAX({
            url: app._urlApproval + '/create',
            data:{
              vendor_mortgage_id : ids.length ? ids : 0,
              //gl_acct_ap : Helper.getSelectedValues($(app._gridTable).bootstrapTable('getSelections'),['gl_acct_ap'])['gl_acct_ap'],
            },
            success: function(data){
              self.$content.html(data.html);
              var submitIds = data.ids;
              var formId = '#mortgageApprovalForm';
              $(formId).unbind('submit').on('submit',function(){
                AJAXSUBMIT(formId,{
                  url: app._urlApproval,
                  data: {
                    vendor_mortgage_id : submitIds,
                    gl_acct_ap : $('#gl_acct_ap').val(),
                    note: $('#note').val(),
                    invoice_date: $('#invoice_date').val(),
                  },
                  type: 'POST',
                  success: function(approveAjaxData){
                    self.setBoxWidth('500px');
                    self.$content.html(approveAjaxData.msg);
                  }
                });
                return false;
              });
            }
          });
        },
        onContentReady: function(){
          INPUTMASK();
        },
        buttons: {
          cancel: {
            text : 'Cancel',
          }
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  uploadMortgage: function(){
    $('#mortgageUpload').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-upload"></i> UPLOAD MORTGAGE FILE(S)</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._urlUploadMortgage + '/create',
            success: function(ajaxData){
              self.$content.html(ajaxData.html);
              UPLOAD(
                  {},
                  app._urlUploadMortgage,
                  {
                    op: 'Mortgage',
                    type: 'mortgage',
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
    return Object.values(app._selectedIds);
  }
};

// Start to initial the main function
$(function(){ app.main(); });

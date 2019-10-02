var app = {
  _url: '/maintenance',
  _urlUpload: '/uploadMaintenance',
  _urlApprove: '/approveMaintenance',
  _urlReset: '/maintenanceResetControlUnit',
  _gridTable: '#gridTable',
  _idCol: 'vendor_maintenance_id',
  _selectedIds: {},
//------------------------------------------------------------------------------
  main: function(){
    this.grid();
    this.create();
    this.delete();
    this.submitToApproval();
    this.resetControlUnit();
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
          reportList     : ajaxData.reportList,
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
             
            $('#delete').prop('disabled', !$(app._gridTable).bootstrapTable('getSelections').length)
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
      title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> EDIT MAINTENANCE</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            UPLOAD(
              {},
              app._urlUpload, 
              {op:'Maintenance', type:'maintenance', foreign_id: id}
            );
            var formId = '#maintenanceForm';
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
        AUTOCOMPLETE();
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
        title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> CREATE MAINTENANCE</b>',
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
                {op:'Maintenance', type:'maintenance'}
              );
              var formId = '#maintenanceForm';
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
          AUTOCOMPLETE();
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
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE Maintenance</b>',
        content: ' Are you sure you want to delete ' + ids.length + ' Maintenance(s).',
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
  submitToApproval: function(){
    $('#generatePayment').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-folder-plus"></i> Generate Maintenance Payment</b>',
        content: function(){
          var self = this;
          AJAX({
            url : app._urlApprove + '/create',
            dataType: 'HTML',
            success: function(ajaxData){
              self.$content.html(ajaxData);
              AUTOCOMPLETE();
              INPUTMASK();
              var formId  = '#maintenanceApprovalForm';
              $(formId).unbind('submit').on('submit',function(){
                
                AJAXSUBMIT(formId,{
                  url: app._urlApprove,
                  type: 'POST',
                  success: function(responseJSON){
                    if(responseJSON !== undefined){
                      if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
                        ALERT({content: responseJSON.error.popupMsg});
                      } else{
                        if(responseJSON.sideErrMsg !== undefined){
                          $('#sideMsg').prepend(responseJSON.sideErrMsg);  
                        }
                          
                        if(responseJSON.sideMsg !== undefined){
                          $('#sideMsg').prepend(responseJSON.sideMsg);
                          app._resetForm(formId);
                        }

                        GRIDREFRESH(app._gridTable);
                      }
                    }
                  }
                });
                return false;
              });
            }
          });
        },
        onContentReady: function(){
          app._setPanelHeight('#maintenanceApprovalForm');
          AUTOCOMPLETE();
        },
        boxWidth: '800px',
        buttons: {
          cancel : {
            text: 'Close'
          }
        }
      });
    });
  },
//------------------------------------------------------------------------------
  resetControlUnit: function(){
    $('#resetControl').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-window-restore"></i> Reset Control Units</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._urlReset + '/create',
            dataType: 'HTML',
            success: function(ajaxData){
              self.$content.html(ajaxData);
              var formId = '#maintenanceResetForm';
              
              $(formId).on('submit').on('submit',function(){
                AJAXSUBMIT(formId,{
                  url: app._urlReset,
                  type: 'POST',
                  success: function(responseJSON){
                    if(responseJSON !== undefined){
                      self.setBoxWidth('500px');
                      self.$content.html(responseJSON.msg);
                      GRIDREFRESH(app._gridTable);
                    }
                  }
                });  
                return false;
              });
            }
          });
        },
        buttons: {
          cancel: {
            text: 'Close',
          }
        }
      });
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
  _setPanelHeight : function(formId){
    $('#sideMsg').height($(formId).height());  
  },
//------------------------------------------------------------------------------
  _resetForm: function(formId){
    $(formId)[0].reset();
    $('#group_by').val('vendid');
    $('#vendid').val('ALL');
    $('#group1').val('ALL');
  },
//------------------------------------------------------------------------------
  _getIdSelections: function() {
    return Object.values(app._selectedIds);
  }
};

// Start to initial the main function
$(function(){ app.main(); });

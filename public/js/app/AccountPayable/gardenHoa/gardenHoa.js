var app = {
  _url: '/gardenHoa',
  _urlUpload: '/uploadGardenHoa',
  _urlSubmission: '/submitGardenHoa',
  _urlReportList: '/gardenHoaExport',
  _gridTable: '#gridTable',
  _idCol: 'vendor_gardenHoa_id',
  _selectedIds: {},
//------------------------------------------------------------------------------
  main: function(){
    this.grid();
    this.create();
    this.delete();
    this.submitToApproval();
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
          //dateColumns    : ['invoice_date']
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id = args.id;
          var sData = {};
          if(name !== 'stop_pay' || args[name] !== 'yes'){
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
          } else {
            CONFIRM({
              title: '<b class="text-red"><i class="fa fa-book-open"></i> STOP PAYMENT</b>',
              content: function(){
                var self = this;
                AJAX({
                  url: app._url,
                  data: {
                    op: 'confirmStop',
                    vendor_gardenHoa_id : id,
                    stop_pay: args[name],
                  },
                  dataType: 'HTML',
                  success: function(responseData){
                    self.$content.html(responseData);
                    var formId = '#gardenHoaForm';
                    $(formId).unbind('submit').on('submit',function(){
                      AJAXSUBMIT(formId,{
                        url: app._url + '/' + id,
                        type: 'PUT',
                        data:  $(formId).serialize() + '&op=confirmForm',
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
              buttons:{
                cancel : {
                  text: 'Close',
                }
              }
            })
          }
          
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
      title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> EDIT GARDEN HOA</b>',
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
              {op:'GardenHoa', type:'gardenHoa', foreign_id: id}
            );
            var formId = '#gardenHoaForm';
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
        title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> CREATE GARDEN HOA</b>',
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
                {op:'GardenHoa', type:'gardenHoa'}
              );
              var formId = '#gardenHoaForm';
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
  submitToApproval: function(){
    $('#generatePayment').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-folder-plus"></i> Generate Garden HOA Payment</b>',
        content: function(){
          var self = this;
          AJAX({
            url : app._urlSubmission + '/create',
            dataType: 'HTML',
            success: function(ajaxData){
              self.$content.html(ajaxData);
              AUTOCOMPLETE();
              INPUTMASK();
              var formId  = '#gardenHoaApprovalForm';
              $(formId).unbind('submit').on('submit',function(){
                
                AJAXSUBMIT(formId,{
                  url: app._urlSubmission,
                  type: 'POST',
                  success: function(responseJSON){
                    //self.setBoxWidth('500px');
                    //self.$content.html(responseData.msg);
                    if(responseJSON !== undefined){
                      if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
                        ALERT({content: responseJSON.error.popupMsg});
                      } else{
                        if(responseJSON.sideErrMsg !== undefined){
                          $('#sideMsg').prepend(responseJSON.sideErrMsg);  
                        }
                          
                        if(responseJSON.sideMsg !== undefined){
                          $('#sideMsg').prepend(responseJSON.sideMsg);
                        }
                        //$('#sideMsg').prepend(responseJSON.sideMsg);
                        GRIDREFRESH(app._gridTable);
                      }
                    }
                    //GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
          });
        },
        onContentReady: function(){
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

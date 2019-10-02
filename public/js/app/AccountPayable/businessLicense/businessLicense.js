var app = {
  _url: '/businessLicense',
  _urlUpload: '/uploadBusinessLicense',
  _urlStorePayment: '/businessLicenseStorePayment',
  _urlUploadPaymentStore: '/uploadBusinessLicense',
  _urlReportList:'/businessLicenseExport',
  _gridTable: '#gridTable',
  _idCol:'vendor_util_payment_id',
  _selectedIds:{},
//------------------------------------------------------------------------------
  main: function(){
    this.grid();
    this.create();
    this.delete();
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
          } else if(field.match(/vendor_payment_hidden_date_field_/g) !== null){
            app._editPayment(value,field);
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
      title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> EDIT BUSINESS LICENSE</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            var formId = '#businessLicenseForm';
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
      boxWidth:'40%',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
//------------------------------------------------------------------------------
  create: function(){
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> CREATE BUSINESS LICENSE</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              AUTOCOMPLETE();
              INPUTMASK();
              var formId = '#businessLicenseForm';
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
        boxWidth:'40%',
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
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE BUSINESS LICENSE</b>',
        content: ' Are you sure you want to delete ' + ids.length + ' business license(s).',
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
  _editPayment: function(value,fieldName){
    var html = $.parseHTML(value);
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa=fw fa-money"></i> EDIT PAYMENT</b>',
      content: function(){
        var self = this;
        AJAX({
          url: app._urlStorePayment + '/create',
          data : {
            vendor_util_payment_id: $(html).attr('data-hidden-row-id'),
            vendor_payment_id: $(html).attr('data-hidden-id'),
            invoice_date: $(html).attr('data-hidden-date'),
            field: fieldName,
          },
          dataType: 'HTML',
          success: function(responseData){
            self.$content.html(responseData);
            var licenseId    = $(html).attr('data-hidden-row-id');
            INPUTMASK();
            UPLOAD(
              {},
              app._urlUploadPaymentStore,
              {op:'BusinessLicense',type:'business_license',foreign_id:licenseId,vendor_payment_id:$(html).attr('data-hidden-id')}
            );
            var formId = '#businessLicensePaymentStoreForm';
            $(formId).unbind('submit').on('submit',function(){
              AJAXSUBMIT(formId, {
                url:   app._urlStorePayment, 
                type: 'POST',
                success: function(updateAjaxData){
                  if(updateAjaxData.keepOpen !== undefined && updateAjaxData.keepOpen == 1){
                    $('#msg').show().html(updateAjaxData.msg);
                  } else { 
                    self.setBoxWidth('500px');
                    self.$content.html(updateAjaxData.msg);
                    GRIDREFRESH(app._gridTable);  
                  }
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
      boxWidth: '90%',
      buttons : {
        cancel: {
          text: 'Close',
        }
      }
    });   
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

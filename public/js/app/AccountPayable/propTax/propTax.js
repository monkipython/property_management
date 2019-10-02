var app = {
  _url: '/propTax', 
  _urlIcon: '/propTaxIcon',
  _index:     'vendor_prop_tax_view', 
  _urlUpload: '/uploadPropTax',
  _urlReport: '/propTaxExport',
  _urlApproval: '/approvePropTax',
  _gridTable: '#gridTable',
  _idCol: 'vendor_prop_tax_id',
  _selectedIds:{},
  main: function(){
    this.grid();
    this.create();
    this.delete();
    this.storeSendApproval();
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
          urlReport      : app._urlReport,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          reportList     : ajaxData.reportList, 
          sortName       : 'prop',
          dateColumns    : ['start_date']
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
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
        });
        $(app._gridTable).on('editable-shown.bs.table', function(field, row, $el) {
          setTimeout(function(){ 
            $('.editable-input input').select();
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
            $('#delete, #firstInstallment, #secondInstallment, #supplemental').prop('disabled', !$(app._gridTable).bootstrapTable('getSelections').length)
        });
      }
    });
  },
//------------------------------------------------------------------------------
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-home"></i> EDIT PROPERTY TAX</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            AUTOCOMPLETE();
            INPUTMASK();
            UPLOAD(
              {},
              app._urlUpload, 
              {op:'PropTax', type:'prop_tax', foreign_id: id}
            );
            var formId = '#propTaxForm';
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
        AUTOCOMPLETE();
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
        title: '<b class="text-red"><i class="fa fa-fw fa-home"></i> CREATE PROPERTY TAX</b>',
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
                {op:'PropTax', type:'prop_tax'}
              );
              var formId = '#propTaxForm';
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
  delete: function() {
    $('#delete').on('click', function() {
      var ids = app._getIdSelections();
      var sData = {'id': ids};
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE PROP TAX</b>',
        content: ' Are you sure you want to delete ' + ids.length + ' Prop Tax.',
        buttons: {
          Delete: {
            action: function(){
              AJAX({
                url : app._url + '/' + ids[0],
                type: 'DELETE',
                data: sData,
                success: function(data){
                  app._disableButtons();
                  GRIDREFRESH(app._gridTable);
                }
              });
            },
            btnClass: 'btn-danger',
          },
          Cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  storeSendApproval: function(){
    $('#firstInstallment, #secondInstallment, #supplemental').on('click',function(){
      var btnType      = $(this).attr('id');
      var approvalType = btnType == 'firstInstallment' ? 'First Installment' : btnType == 'secondInstallment' ? 'Second Installment' : 'Supplemental';
      var titleIcon    = btnType == 'supplemental' ? 'fa-location-arrow' : 'fa-paper-plane-o';
      var ids    = app._getIdSelections();
      var sData  = {'vendor_prop_tax_id':ids, 'approvalType':btnType};
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw '+ titleIcon +'"></i> SEND '+ approvalType.toUpperCase() +' TO APPROVAL</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._urlApproval + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              $('.box-title').text('Are you sure you want to submit ' + ids.length + ' ' + approvalType + ' for Approval?');
              INPUTMASK();
              var formId = '#approvalForm';
              $(formId).unbind('submit').on('submit', function(){
                sData.invoice_date = $('#invoice_date').val();
                AJAXSUBMIT(formId, {
                  url:   app._urlApproval,
                  type: 'POST',
                  data  : sData,
                  success: function(data){
                    app._disableButtons();
                    $(app._gridTable).bootstrapTable('uncheckAll');
                    GRIDREFRESH(app._gridTable);
                    self.setBoxWidth('500px');
                    self.$content.html(data.msg);
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      
      return false;
    });
  },
//------------------------------------------------------------------------------
  _getIdSelections: function() {
    return Object.values(app._selectedIds);
  },
//------------------------------------------------------------------------------
  _disableButtons: function() {
    $('#delete, #firstInstallment, #secondInstallment, #supplemental').prop('disabled', true);
  },
};

// Start to initial the main function
$(function(){ app.main(); });
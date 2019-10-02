var app = {
  _url: '/insurance', 
  _urlIcon: '/insuranceIcon',
  _index:     'vendor_insurance_view', 
  _urlUpload: '/uploadInsurance',
  _urlUploadInsurance: '/insuranceUpload',
  _urlReport: '/insuranceExport',
  _urlAutoBankInfo: '/accountPayableBankInfo',
  _urlApproval: '/approveInsurance',
  _gridTable: '#gridTable',
  _idCol: 'vendor_insurance_id',
  _selectedIds:{},
  main: function(){
    this.grid();
    this.create();
    this.delete();
    this.storeSendApproval();
    this.uploadInsurance();
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
          dateColumns    : ['start_pay_date', 'effective_date']
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
        // Add dynamic dropdown to the bank column
        $(app._gridTable).on('post-body.bs.table',function(e,data){
          var tData = $(app._gridTable).bootstrapTable('getData');
          $.map(tData, function(row) {
            var bankData = [];
            var bankKeys = Object.keys(row.bank_id);
            var bankValues = Object.values(row.bank_id);
            for(var i = 0; i < bankKeys.length; i++) {
              bankData.push({value: bankKeys[i], text:bankKeys[i] + ' - ' + bankValues[i]});
            }
            row.bank_id = bankData;
            return row;
          });
          $.each(tData,function(i,row){
            var elem = $('tr[data-index=' + i + '] td a[data-name="bank"]');
            elem.editable({
              type: 'select',
              source: row.bank_id
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
            $('#delete, #request').prop('disabled', !$(app._gridTable).bootstrapTable('getSelections').length)
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
      title: '<b class="text-red"><i class="fa fa-fw fa-shield"></i> EDIT INSURANCE</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            app._initAutocomplete();
            INPUTMASK();
            UPLOAD(
              {},
              app._urlUpload, 
              {op:'Insurance', type:'insurance', foreign_id: id}
            );
            app._checkNumberPayment();
            var formId = '#insuranceForm';
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
        title: '<b class="text-red"><i class="fa fa-fw fa-shield"></i> CREATE INSURANCE</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              INPUTMASK();
              UPLOAD(
                {},
                app._urlUpload, 
                {op:'Insurance', type:'insurance'}
              );
              app._checkNumberPayment();
              app._initAutocomplete();
              var formId = '#insuranceForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url, 
                  type: 'POST',
                  success: function(updateAjaxData){
                    $('#amount').val('');
                    //app._resetForm(formId);
                    //self.setBoxWidth('500px');
                    //self.$content.html(updateAjaxData.msg);
                    GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
          });
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
    var selections = [];
    var $delete = $('#delete');
    $delete.on('click', function() {
      var ids = app._getIdSelections();
      var sData = {'id': ids};
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE INSURANCE</b>',
        content: ' Are you sure you want to delete ' + ids.length + ' Insurance(s).',
        buttons: {
          delete: {
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
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  storeSendApproval: function(){
    $('#request').on('click',function(){
      var ids    = app._getIdSelections();
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-shield"></i>SEND INSURANCE PAYMENT TO APPROVAL</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlApproval + '/create',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData.html);
              $('.box-title').text('Are you sure you want to submit ' + ids.length + ' Payment(s) for Approval?');
              INPUTMASK();
            }
          });
        },
        boxWidth:'550px',
        buttons: {
          confirm: {
            text: '<i class="fa fa-check"></i> Confirm',
            action: function(){
              var formId = '#approvalForm';
              var sData = {vendor_insurance_id: ids};
              sData.invoice_date     = $('#invoice_date').val();
              sData.isMonthlyPayment = $('#isMonthlyPayment').val();
              AJAXSUBMIT(formId, {
                url:  app._urlApproval,
                type: 'POST',
                data: sData,
                success: function(data){
                  app._disableButtons();
                  $(app._gridTable).bootstrapTable('uncheckAll');
                  GRIDREFRESH(app._gridTable);
                }
              });
            },
            btnClass: 'btn-success',
          },
          cancel: {
            text: '<i class="fa fa-fw fa-close"></i> Cancel',
            btnClass: 'btn-danger',
          }
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  uploadInsurance: function(){
    $('#uploadInsurance').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-upload"></i> UPLOAD INSURANCE FILE(S)</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._urlUploadInsurance + '/create',
            success: function(ajaxData){
              self.$content.html(ajaxData.html);
              UPLOAD(
                {},
                app._urlUploadInsurance,
                {
                  op  : 'Insurance',
                  type: 'insurance',
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
        buttons : {
          cancel: {
            text : 'Close',
          }
        }
      });
    });
  },
//------------------------------------------------------------------------------
  _checkNumberPayment: function() {
    var $numberPayment = $('#number_payment');
    var $monthPaymentAndStartDate = $('#monthly_payment, #start_pay_date');
    
    $numberPayment.change(function() {
      var numberPayment = $(this).val();
      if(numberPayment > 0) {
        $monthPaymentAndStartDate.prop('readonly', false);
      }else {
        $monthPaymentAndStartDate.prop('readonly', true);
        INPUTMASK();
      }
    }).change();
  },
//------------------------------------------------------------------------------
  _getIdSelections: function() {
    return Object.values(app._selectedIds);
  },
//------------------------------------------------------------------------------
  _disableButtons: function() {
    $('#delete, #request').prop('disabled', true);
  },
//------------------------------------------------------------------------------
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
      url   : app._urlAutoBankInfo,
      data  : {prop: $('#prop').val()}, 
      success: function(createAjaxData){
        $('#bank').html(createAjaxData.html);
      }
    });
  },  
//------------------------------------------------------------------------------
  _resetForm: function(formId){
    var amount = $('#amount').val();
    if($(formId).length > 0){
      $(formId)[0].reset();
      $('#bank').val('');
      $('#monthly_payment').prop('readonly',true);
      $('#start_pay_date').prop('readonly',true);
    }
    $('#amount').val(amount);
  }
};
// Start to initial the main function
$(function(){ app.main(); });
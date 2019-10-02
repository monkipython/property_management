var app = {
  _url: '/tenantMoveOutProcess', 
  _index: 'tnt_move_out_process_view', 
  _urlDepositRefund: '/tenantDepositRefund',
  _urlDepositRefundUndo: '/tenantDepositRefundUndo',
  _urlUploadMoveOutReport: '/uploadMoveOutReport',
  _urlReport: '/tenantMoveOutProcessExport',
  _urlUploadMoveOutFile: '/uploadMoveOutFile',
  _gridTable: '#gridTable',
  main: function(){
    this.grid();
  },
//------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(ajaxData){
        window.operateEvents = {
          'click .edit': function (e, value, row, index) { app.edit(row); },
          'click .depositRefund': function(e,value,row,index) {app._editDepositRefund(row);},
          'click .depositRefundUndo': function(e,value,row,index) {app._storeDepositRefundUndo(row);},
          'click .moveOutFileUpload': function (e, value, row, index) { app._moveOutFileUpload(row); },
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          urlReport      : app._urlReport,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          reportList     : ajaxData.reportList, 
          fixedNumber    : 1,
          sortName       : 'status',
          dateColumns    : ['move_in_date', 'move_out_date', 'udate']
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
              GRIDREFRESH(app._gridTable);
              Helper.displayGridEditErrorMessage(updateAjaxData);
            }
          });
        });
        $(app._gridTable).on('click-cell.bs.table',function(e,field,value,row,$element){
          switch(field){
            case 'moveout_report' : app._viewMoveOutReport(row); break;
            case 'moveout_file'   : app._viewMoveOutFile(row); break;
          }
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
  },
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-recycle"></i> VIEW TENANT MOVE OUT PROCESS</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            AUTOCOMPLETE();
            var formId = '#tntMoveOutProcessForm';
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
      boxWidth:'700',
      buttons: {
        Cancel: {text: 'Close'}
      }
    }); 
  },
//------------------------------------------------------------------------------
  _editDepositRefund: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-usd"></i> REFUND DEPOSIT</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._urlDepositRefund + '/' + id + '/edit',
          success: function(editUploadAjaxData){
            var titles = ['Service#', 'Service Description', 'Amount'];
            // var fullBillingIndex = editUploadAjaxData.billingCount;
            self.$content.html(editUploadAjaxData.html);
            app._addFullBillingTitle(titles);
            INPUTMASK();
            AUTOCOMPLETE();
            app._storeMoveOut(self);
            app._calculateTotalBalance();
            /*
            $('#moreBilling').unbind('click').on('click', function(){
              var fullBilling = Helper.replaceArrayKeyId(editUploadAjaxData.fullBillingEmpty, fullBillingIndex);
              $('.totalAmount').before(fullBilling);
              app._addFullBillingTitle(titles);
              var emptyFieldRows = $('.emptyField');
              var billingRemoveDoms = $('.billingRemove');
              emptyFieldRows.eq(emptyFieldRows.length-1).attr('data-key', fullBillingIndex);
              billingRemoveDoms.eq(billingRemoveDoms.length-1).attr('data-key', fullBillingIndex);
              fullBillingIndex++;
              INPUTMASK();
              AUTOCOMPLETE();
              app._destroyBilling(titles);
              $('input[name^="amount"]').on('keyup', function() {
                app._calculateTotalBalance();
              });
            });
            $('input[name^="amount"]').on('keyup', function() {
              app._calculateTotalBalance();
            }).trigger('keyup');
            app._destroyBilling(titles);
            
            */
            
          }
        });
      },
      boxWidth:'900px',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
//------------------------------------------------------------------------------
  _storeMoveOut: function(self){
    var formId = '#depositRefundForm';
    $(formId).unbind('submit').on('submit', function(){
      AJAXSUBMIT(formId, {
        url:  app._urlDepositRefund,
        type: 'POST',
        success : function(storeMoveOutAjaxData){
          GRIDREFRESH(app._gridTable);
          self.setBoxWidth('550px');
          self.$content.html(storeMoveOutAjaxData.msg);
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  _addFullBillingTitle: function(titles) {
    var fullBillingLength = $('#fullBilling > div').length;
    var firstRowTitle = $('#fullBilling > div .fullBillingTitle').length;
    if(fullBillingLength > 1 && firstRowTitle == 0) {
      $('#fullBilling div:first-child > div').each(function(i){
        $(this).prepend('<h4 class="fullBillingTitle">' + titles[i] + '</h4>');
      });
    }
  },
//------------------------------------------------------------------------------
 /* _destroyBilling: function(titles=[]){
    $('.billingRemove').unbind('click').on('click', function(e){
      var obj = $(this);
      var dataKey = obj.attr("data-key");
      var removeDom = $('#fullBilling div[data-key='+dataKey+']');
      removeDom.remove();
      app._addFullBillingTitle(titles);
      app._calculateTotalBalance();
    });
  },*/
//------------------------------------------------------------------------------
  _storeDepositRefundUndo: function(row) {
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-arrow-left"></i> UNDO DEPOSIT REFUND CONFIRMATION</b>',
      content: ' Are you sure you want to undo the deposit refund?',
      buttons: {
        Confirm: {
          action: function(){
            AJAX({
              url:  app._urlDepositRefundUndo,
              data: {'tnt_move_out_process_id': id},
              type: 'POST',
              success : function(storeMoveOutAjaxData){
                GRIDREFRESH(app._gridTable);
              }
            });
          },
          btnClass: 'btn-info',
        },
        Cancel: {
          btnClass: 'btn-danger'
        }
      }
    }); 
  },
//------------------------------------------------------------------------------
  _calculateTotalBalance: function() {
    var totalAmount = 0;
    var $totalAmount = $('#totalAmount');
    $('input[name^="amount"]').each(function(i) {
      var value    = $(this).val();
      var negative = $(this).closest('.col-md-3').hasClass('negative');
      var amount   = Number(value.replace(/[^0-9.-]+/g,""));
      amount = negative ? amount * -1 : amount;
      totalAmount += amount;
    });
    if(totalAmount < 0) {
      $totalAmount.closest('.col-md-3').addClass('negative').removeClass('positive');
    }else {
      $totalAmount.closest('.col-md-3').addClass('positive').removeClass('negative');
    }
    totalAmount = Math.abs(totalAmount);
    $totalAmount.val(totalAmount);
  },
//------------------------------------------------------------------------------
  _viewMoveOutReport: function(row){
    if(row.moveout_report){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW MOVE OUT REPORT</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlUploadMoveOutReport,
            data    : {id: row.tnt_move_out_process_id},
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              //Preview files
              self.$content.html(editUploadAjaxData);
            }
          });
        },
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        },
        onContentReady: function(){
          AUTOCOMPLETE();
          INPUTMASK();
        }
      });
    }
  },
//------------------------------------------------------------------------------
  _moveOutFileUpload: function(row) {
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-recycle"></i> MOVE OUT FILE UPLOAD</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._urlUploadMoveOutFile + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            UPLOAD(
              {},
              app._urlUploadMoveOutFile, 
              {op:'TenantMoveOut', type:'tenantMoveOutFile', foreign_id: id}
            );
          }
        });
      },
      boxWidth:'90%',
      buttons: {
        cancel: {
          text: 'Close',
          action: function(){
            GRIDREFRESH(app._gridTable);
          }
        }
      }
    }); 
  },
//------------------------------------------------------------------------------
  _viewMoveOutFile: function(row){
    if(row.moveout_file){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW MOVE OUT FILE</b>',
        content: function(){
          var self = this;
          var id = row.tnt_move_out_process_id;
          AJAX({
            url    : app._urlUploadMoveOutFile,
            data    : {id: id},
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              self.$content.html(editUploadAjaxData);
              if(row.status == '0') {
                var formId = '#rejectForm';
                $(formId).unbind('submit').on('submit', function(){
                  AJAXSUBMIT(formId, {
                    url:   app._urlUploadMoveOutFile + '/' + id,
                    type: 'DELETE',
                    success: function(updateAjaxData){
                      self.setBoxWidth('500px');
                      self.$content.html(updateAjaxData.msg);
                      GRIDREFRESH(app._gridTable);
                    }
                  });
                  return false;
                });
              }
            }
          });
        },
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        }
      }); 
    }
  }
};

// Start to initial the main function
$(function(){ app.main(); });
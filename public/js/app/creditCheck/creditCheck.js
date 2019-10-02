var app = {
  _url: '/creditCheck', 
  _urlReport: '/creditCheckReport',
  _urlIcon: '/creditCheckIcon',
  _urlUpload: '/upload',
  _urlTransferTenant: '/transferTenant',
  _urlUploadAgreement: '/uploadAgreement',
  _urlUploadApplication: '/uploadApplication',
  _urlProrate: '/prorate',
  _urlMoveIn: '/movein',
  _index:     'creditcheck_view', 
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
          'click .agreementUpload': function (e, value, row, index) { app._agreementUpload(row); },
          'click .transferTenant': function (e, value, row, index) { app._transferTenant(row); }
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          urlReport      : app._urlReport,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'prop',
          reportList     : ajaxData.reportList,
          dateColumns    : ['application.app_fee_recieved_date', 'cdate', 'move_in_date', 'housing_dt2']
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id = args.id;
          var sData = {};
          sData[name] = args[name];
          sData.prop = args.prop;
          sData.op  = 'approval';
          AJAX({
            url    : app._url + '/' + id,
            data   : sData,
            type   : 'PUT',
            success: function(updateAjaxData){
              if(updateAjaxData.error !== undefined){
                $el[0].innerHTML = oldValue;
                
                Helper.displayError(updateAjaxData);
              }
              GRIDREFRESH(app._gridTable);
            }
          });
        });
        $(app._gridTable).on('click-cell.bs.table', function (e, field, value, row, $element) {
          switch (field){
            case 'moved_in_status': app._moveIn(row, value); break;
            case 'undoMovein': break;
            case 'agreement': app._viewAgreement(row); break;
            case 'application.tnt_name': app._viewTenantCreditCheck(row); break;
          }
        });
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
          $('.fee').unbind('click').on('click', function(){
            var id = $(this).attr('data-id');
            var infoId = $(this).attr('data-key');
            AJAX({
              url    : app._url + '/' + id,
              data   : {op:'fee', application_info_id: infoId,  app_fee_recieved: ($(this).prop('checked') ? 1 : 0) },
              type   : 'PUT',
              success: function(updateAjaxData){
                GRIDREFRESH(app._gridTable);
              }
            });
          });
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
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> EDIT APPLICATION</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            UPLOAD({}, app._urlUploadApplication, {op:'CreditCheckUpload', type:'application', foreign_id: id});
            
            var formId = '#applicationForm';
            $(formId).unbind('submit').on('submit', function(){
              AJAXSUBMIT(formId, {
                url:   app._url + '/' + id,
                type: 'PUT',
                success: function(updateAjaxData){
                  dd(updateAjaxData);
                }
              });
              return false;
            });
          }
        });
      },
      onContentReady: function () {
        AUTOCOMPLETE();
        INPUTMASK();
      },
      boxWidth:'90%',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/


/*******************************************************************************
 ************************ click-cell.bs.table SECTION **************************
 ******************************************************************************/
  _viewTenantCreditCheck: function(row){
    if(row['application.tenatNameClickable'] == 1){
      var id = '';
      for(var i in row.application){
        id += row.application[i].application_info_id + ',';
      }

      AJAX({
        url: app._url + '/' + id,
        dataType: 'HTML',
        success: function(viewTenantAjaxData){
          CONFIRM({
            title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> TENANT CREDIT CHECK</b>',
            content: viewTenantAjaxData,
            boxWidth:'60%',
            buttons: {
              cancel: { 
                text: ' Close ',
                btnClass: 'btn-danger'
              }
            }
          }); 
        }
      });
    }
  },
  _agreementUpload: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> AGREEMENT AND PHOTO UPLOAD</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._urlUploadAgreement + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            UPLOAD(
              {},
              app._urlUploadAgreement, 
              {op:'CreditCheckUpload', type:'agreement', foreign_id: id}
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
  _viewAgreement: function(row){
    var id = row.id;
    if(row.agreement){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW TENANT AGREEMENT</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlUploadAgreement,
            data    : {id: id},
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              self.$content.html(editUploadAjaxData);
              var formId = '#rejectForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._urlUploadAgreement + '/' + id,
                  type: 'PUT',
                  success: function(updateAjaxData){
                    dd(updateAjaxData);
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
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        }
      }); 
    }
  },
  _transferTenant: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-exchange"></i> Transfer Tenant</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._urlTransferTenant + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            AUTOCOMPLETE();
            var formId = '#applicationForm';
            $(formId).unbind('submit').on('submit', function(){
              AJAXSUBMIT(formId, {
                url:  app._urlTransferTenant + '/' + id,
                type: 'PUT',
                success : function(storeMoveInAjaxData){
                  GRIDREFRESH(app._gridTable);
                  self.setBoxWidth('500px');
                  self.$content.html(storeMoveInAjaxData.msg);
                }
              });
              return false;
            });
          }
        });
      },
      boxWidth:'750px',
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
  _moveIn: function(row, value){
    if(value.match(/\>Move In/)){
      var id = row.id;
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> MOVE IN TENANT </b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlMoveIn + '/' + id + '/edit',
            success: function(editUploadAjaxData){
              var otherMeberIndex = editUploadAjaxData.otherMemberIndex;
              var fullBillingIndex = 3;
              var fullBillingCount = 0;
              var otherMeberCount = 0;
              self.$content.html(editUploadAjaxData.html);
              
              app._changeProrateType(editUploadAjaxData.fullBilling);

              $('#moreBilling,#moreOtherMember').unbind('click').on('click', function(){
                if($(this).attr('id') == 'moreBilling'){
                  var fullBilling = Helper.replaceArrayKeyId(editUploadAjaxData.fullBilling.emptyField, ++fullBillingIndex);
                  $('#fullBilling').append(fullBilling);
                  $('.emptyField[data-key=0]').attr('data-key', ++fullBillingCount);
                  app._bindBillingStopDate(fullBillingCount);
                } else {
                  var otherMember = Helper.replaceArrayKeyId(editUploadAjaxData.otherMember, ++otherMeberIndex);
                  $('#otherMember').append(otherMember);
                  $('.otherMemberEmptyField[data-key=0]').attr('data-key', ++otherMeberCount);
                }
                INPUTMASK();
                AUTOCOMPLETE();
              });

              $('#lessBilling,#lessOtherMember').unbind('click').on('click', function(){
                if($(this).attr('id') == 'lessBilling'){
                  $('.emptyField[data-key='+ fullBillingCount-- +']').remove();
                  fullBillingCount = (fullBillingCount <= 0) ? 0 : fullBillingCount;
                } else{
                  $('.otherMemberEmptyField[data-key='+ otherMeberCount-- +']').remove();
                  otherMeberCount = (otherMeberCount <= 0) ? 0 : otherMeberCount;
                }
              });
              app._storeMoveIn(self);
            }
          });
        },
        boxWidth:'98%',
        buttons: {
          cancel: {text: 'Close'}
        },
        onContentReady: function () {
          $('#move_in_date,#base_rent').unbind('').on('keyup',function(e){
              app._getProrateAmount();
          });
          setTimeout(function(){
            INPUTMASK({
              date: function(){ 
                app._getProrateAmount(); 
              }
            });
          }, 150);
          TOOLTIPSTER('i.tip', {zIndex: 100000005});
        }
      }); 
    }
  },
  _storeMoveIn: function(self){
    var formId = '#applicationForm';
    $(formId).unbind('submit').on('submit', function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-arrow-right"></i> MOVE IN CONFIRMATION</b>',
        content: ' Are you sure you want to move in this tenant.',
        buttons: {
          confirm: {
            action: function(){
              AJAXSUBMIT(formId, {
                url:  app._urlMoveIn,
                type: 'POST',
                success : function(storeMoveInAjaxData){
                  if(storeMoveInAjaxData.error !== undefined && storeMoveInAjaxData.error.prop_class){
                    ALERT({content: 'This property is not acctive. Please double check.'});
                  }
                  GRIDREFRESH(app._gridTable);
                  self.setBoxWidth('500px');
                  self.$content.html(storeMoveInAjaxData.msg);
                }
              });
            },
            btnClass: 'btn-info',
          },
          cancel: {
            btnClass: 'btn-danger'
          }
        }
      }); 
      return false;
    });
  },
  _getProrateAmount: function(){
    var moveinDate  = $('#move_in_date').val();
    var prorateType = $('#prorate').val();
    var baseRent    = $('#base_rent').val();
    AJAX({
      url    : app._urlProrate,
      data    : {id: $('#application_id').val(), moveinDate: moveinDate, prorateType:prorateType, baseRent: baseRent},
      success: function(prorateAmountAjaxData){
        if(prorateAmountAjaxData != ''){
          $('#fullBilling').html(prorateAmountAjaxData[prorateType]);
          app._changeProrateType(prorateAmountAjaxData);
          INPUTMASK();
        }
      }
    });
  },
  _changeProrateType: function(prorateAmountAjaxData){
    $('#prorate').unbind('change').on('change', function(){
      var val = $(this).val();
      $('#fullBilling').html(prorateAmountAjaxData[val]);
      INPUTMASK();
    });
  },
  _bindBillingStopDate: function(index){
    $('#fullBilling .emptyField[data-key=' + index + '] select[name*="schedule"]').unbind('change').on('change',function(){
      var schedule  = $(this).val();
      var stopDate  = schedule === 'M' ? '12/31/9999' : moment().endOf('month').format('MM/DD/YYYY');
      
      var dateInput = '#fullBilling .emptyField[data-key=' + index + '] input[name*="stop_date"]';
      $(dateInput).val(stopDate);
    });
  }
};
$(function(){ app.main(); });// Start to initial the main function
var app = {
  _urlDebitExpenseTransaction: '/debitExpenseTransaction',
  _urlBankInfo: 'bankInfo',
  _formId: '#debitExpenseTransactionForm',
  _mainForm: '#applicationForm',
  main: function(){
    this.create();
  },
  create: function(){
    var _getForm = function(type, defaultVal){
      var $formId= $(app._formId);
      $formId.html('');
      AJAX({
        url   : type + '/create',
        success: function(createAjaxData){
          $formId.html(createAjaxData.html);
          if(createAjaxData.text){
            $('#sideMsg').html(createAjaxData.text);
          }
          if(createAjaxData.isUpload){
            app._storeDebitExpenseTransactionUpload(type);
          }
          
          INPUTMASK();
          app._initAutocomplete();
          app._setGridTableHeight();   
         

          //##### SUBMIT THE LEDGER TO VIEW/POST INVOICE #####//
          $(app._mainForm).unbind('submit').on('submit', function(){
            
            $('#initText').hide();
            
            app._storeDebitExpenseTransaction();
            
            return false;
          });
        }
      });
    };
    _getForm($('#type').val());
    $('#type').on('change', function(){
      // HIDE AND SHOW THE PENAL
      var type = $(this).val();
      _getForm(type);
    });
  },
  //------------------------------------------------------------------------------
  _storeDebitExpenseTransaction: function(){
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> CONFIRM RECORDING</b>',
      content: 'Are you sure you want to recording this transaction?',
      boxWidth:'450px',
      buttons: {
        confirm: {
          text: '<i class="fa fa-fw fa-check"></i> Confirm',
          btnClass: 'btn-green',
          action: function(){
            AJAXSUBMIT(app._mainForm, {
              url:  app._urlDebitExpenseTransaction,
              type: 'POST',
              success : function(storeAjaxData){
                // DEAL WITH REUSE BATCH NUMBER
                app._reuseBatchNumber(storeAjaxData);

                $('#sideMsg').prepend(storeAjaxData.sideMsg);
                $('#amount,#prop,#unit,#gl_acct,#remark,#vendid,#invoice').val('');
                $('#check_no').val('000000');
                $('#ar_bank').html('');
                $('.autocompleteAfter').remove();
              }
            });
          }
        },
        cancel: {
          text: '<i class="fa fa-fw fa-close"></i> Cancel',
          btnClass: 'btn-red',
        }
      },
    });
  },
//------------------------------------------------------------------------------
  store: function() {
    $(app._mainForm).unbind('submit').on('submit',function(){
      AJAXSUBMIT(app._mainForm,{
        url: app._urlDebitExpenseTransaction,
        type: 'POST',
        success: function(storeAjaxData){
          // DEAL WITH REUSE BATCH NUMBER
          app._reuseBatchNumber(storeAjaxData);

          $('#sideMsg').prepend(storeAjaxData.sideMsg);
          $('#amount,#prop,#unit,#gl_acct,#remark,#vendid,#invoice').val('');
          $('#check_no').val('000000');
          $('#ar_bank').html('');
          $('.autocompleteAfter').remove();
        }
      });
      return false;
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _initAutocomplete: function(){
    AUTOCOMPLETE({
      formId: app._mainForm,
      param: {includeField:['list_bank', 'list_unit'], additionalField: {prop: $('#prop').val() } },
      callback: function(dataSet){
        if(dataSet.append_prop !== undefined){
          $('#unit').val('');
          app._getBank();
        }
      }
    });
    
    $(app._mainForm + ' #prop').on('focusout',function(){
      $('#unit').val('');
      app._getBank();
    });
  },
//------------------------------------------------------------------------------
  _getBank: function(){
    AJAX({
      url   : app._urlBankInfo,
      data  : {prop: $('#prop').val()}, 
      success: function(createAjaxData){
        $('#ar_bank').html(createAjaxData.html);
      }
    });
  }, 
//------------------------------------------------------------------------------
  _setGridTableHeight: function() {
    var qqUploader = $('.qq-uploader');
    const minHeight = 300;
    var rawVal = $(window).height() - $('.main-header').outerHeight() - 200;
    var height = (rawVal > minHeight) ? rawVal : minHeight;
    $('.gridTable,.nav-tabs-custom').css('height', height);
    if(qqUploader.length > 0) {
      var loaderHeight = height - 30 + 'px';
      var listHeight   = height - 110 + 'px';
      qqUploader.css({height: loaderHeight, 'max-height': loaderHeight});
      $('.qq-upload-list').css({height: listHeight, 'max-height': listHeight});
    }
  },
//------------------------------------------------------------------------------
  _reuseBatchNumber: function(storeAjaxData){
    $('#batch').html(storeAjaxData.batchHtml);
    $('#batch').unbind('change').on('change', function(){
      if($(this).val()){
        $('#date1').val(storeAjaxData.date1).attr('readonly', true);
      } else{
        $('#date1').attr('readonly', false);
      }

      $('#date1').datepicker('destroy');
      $('#date1:not([readonly])').datepicker({
        format:'mm/dd/yyyy',
        autoclose:true,
        immediateUpdates:true,
        clearBtn:true,
        todayHighlight: true,
        keyboardNaviagtion: false,
      });
    });
  },
  _storeDebitExpenseTransactionUpload: function(type){
    UPLOAD({}, '/' + type, {
      op:'DebitExpenseTransaction', type:type, route: 'show', onComplete: function(responseJSON){
        if(responseJSON !== undefined){
          if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
            ALERT({content: responseJSON.error.popupMsg});
          } else{
            $('#sideMsg').prepend(responseJSON.sideMsg);
          }
        }
      }}
    );
  }
};
$(function(){ app.main(); });// Start to initial the main function

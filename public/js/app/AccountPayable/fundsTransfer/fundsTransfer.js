var app = {
  _url: '/fundsTransfer',
  _urlBankInfo: 'accountPayableBankInfo',
  
  main: function(){
    this.create();
  },
  create: function(){
    app._setPanelHeight();
    INPUTMASK();
    AUTOCOMPLETE();
    app._initAutocomplete('');
    app._initAutocomplete('to');
    
    $('#fundsTransferForm').unbind('submit').on('submit',function(){
      var formId = '#fundsTransferForm';
      AJAXSUBMIT(formId,{
        url: app._url,
        type: 'POST',
        success: function(responseData){
          if(responseData !== undefined){
            if(responseData.error !== undefined && responseData.error.popupMsg !== undefined){
              $('#sideMsg').prepend(responseData.error.popupMsg);
            } else {
              $('#sideMsg').prepend(responseData.sideMsg);
              app._resetForm();
            }
          }
        }
      });
      return false;
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _resetForm: function(){
    $('#fundsTransferForm')[0].reset();
    $('#bank, #tobank').val('');
  },
  _setPanelHeight: function(){
    const minHeight  = 300;
    var rawVal = $(window).height() - $('.main-header').outerHeight();
    var height = (rawVal > minHeight) ? rawVal : minHeight;
    $('.gridTable').css('height', height);
  },
//------------------------------------------------------------------------------
  _initAutocomplete: function(prefix){
    var formId = '#fundsTransferForm';
    AUTOCOMPLETE({callback: function(dataSet){
      var value = dataSet.value;
      if(dataSet.append_prop !== undefined){
//        $('#unit').val('');
//        $('#tenant,#tenantInfo').html('');
        $('#' + prefix + 'trust').val(app._extractTrust(value));
        app._getBank(prefix);
        if(prefix === 'to'){
          app._setTransferRemark();
        }
      }
    }},'#' + prefix + 'prop');

    $(formId +  ' #' + prefix + 'prop').on('focusout',function(){
      app._getBank(prefix);
    });
  },
//------------------------------------------------------------------------------
  _getBank: function(prefix){
    AJAX({
      url   : app._urlBankInfo,
      data  : {prop: $('#' + prefix + 'prop').val()}, 
      success: function(createAjaxData){
        $('#' + prefix + 'bank').html(createAjaxData.html);
      }
    });
  }, 
//------------------------------------------------------------------------------
  _setTransferRemark: function(){
    $('#remark').val('Fund transfer for ' + $('#prop').val() + ' to ' + $('#toprop').val());
  },
//------------------------------------------------------------------------------
  _extractTrust: function(valueStr){
    return valueStr.replace(/(.*)\(Trust:\s+(.*)\)(.*)/g,'$2');
  },
};

$(function(){ app.main(); });// Start to initial the main function



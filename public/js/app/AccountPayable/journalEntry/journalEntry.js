var app = {
  _urlJournalEntry: '/journalEntry',
  _urlBankInfo: 'bankInfo',
  _formId: '#journalEntryForm',
  main: function(){
    this.addMoreEntry();
    this.store();
    app._initAutocomplete();
    app._calculateTotalAmount();
    INPUTMASK();
    app._onPropChangeRemoveBankList();
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  store: function() {
    $(app._formId).unbind('submit').on('submit',function(){
      AJAXSUBMIT(app._formId,{
        url: app._urlJournalEntry,
        type: 'POST',
        success: function(storeAjaxData){
          $('#journalEntry').empty();
          $('#moreBilling').trigger('click');
          $('#sideMsg').html(storeAjaxData.sideMsg);
        }
      });
      return false;
    });
  },
  addMoreEntry: function() {
    var fullBillingIndex = 0;
    var titles = ['Prop', 'Bank', 'Gl Acct', 'Date', 'Amount', 'Remark', 'Check'];
    var copiedRow = $('#journalEntry').clone().html().replace(/\s+/, "");
    app._addFullBillingTitle(titles);
    $('#moreBilling').unbind('click').on('click', function(){
      fullBillingIndex++;
      var fullBilling = Helper.replaceArrayKeyId(copiedRow, fullBillingIndex);
      $('#journalEntry').append(fullBilling);
      app._addFullBillingTitle(titles);
      var journalEntryRows = $('.journalEntryRow');
      var journalEntryRemoveDoms = $('.journalEntryRemove');
      journalEntryRows.eq(journalEntryRows.length-1).attr('data-key', fullBillingIndex);
      journalEntryRemoveDoms.eq(journalEntryRemoveDoms.length-1).attr('data-key', fullBillingIndex);
      app._destroyJournalEntry(titles);
      INPUTMASK();
      app._initAutocomplete();
      app._calculateTotalAmount();
      app._onPropChangeRemoveBankList();
    });
    app._destroyJournalEntry(titles);
  },
  _destroyJournalEntry: function(titles){
    $('.journalEntryRemove').unbind('click').on('click', function(e){
      var obj = $(this);
      var dataKey = obj.attr("data-key");
      
      $('#journalEntry div[data-key='+dataKey+']').remove();
      app._addFullBillingTitle(titles);
      app._calculateTotalAmount();
    });
  },
  _addFullBillingTitle: function(titles) {
    var fullBillingLength = $('#journalEntry > div').length;
    var firstRowTitle = $('#journalEntry > div .journalEntryTitle').length;
    if(fullBillingLength > 0 && firstRowTitle == 0) {
      $('#journalEntry div:first-child > div').each(function(i){
        $(this).prepend('<h4 class="journalEntryTitle">' + titles[i] + '</h4>');
      });
    }
  },
//------------------------------------------------------------------------------
  _initAutocomplete: function(){
    AUTOCOMPLETE({
      formId: app._formId,
      param: {includeField:['list_bank'], selector: {} },
      callback: function(dataSet){
        if(dataSet.data !== undefined){
          app._getBank(dataSet.data, dataSet.num);
        }
      }
    });
  },
//------------------------------------------------------------------------------
  _getBank: function(prop, num){
    AJAX({
      url   : app._urlBankInfo,
      data  : {prop: prop}, 
      success: function(createAjaxData){
        $('#ar_bank\\['+ num +'\\]').html(createAjaxData.html);
      }
    });
  },
//------------------------------------------------------------------------------
  _calculateTotalAmount: function() {
    var $amount = $('.amount');
    var $totalAmount = $('.totalAmount');
    $amount.unbind('keyup').on('keyup', function() {
      var totalAmount = 0;
      $amount.each(function() {
        var value    =  $(this).val();
        var amount   = Number(value.replace(/[^0-9.-]+/g,""));
        totalAmount += amount;
      });
      if(totalAmount == 0) {
        $totalAmount.addClass('text-green').removeClass('text-red');
      }else {
        $totalAmount.addClass('text-red').removeClass('text-green');
      }
      totalAmount = totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2 });
      $totalAmount.text(totalAmount);
    }).trigger('keyup');
  },
  //----------------------------------------------------------------------------
  _onPropChangeRemoveBankList: function() {
    $('.prop').on('keyup', function(e) {
      var arrNum = $(this).attr('name').match( /\d+/g);
      var select = $('#ar_bank\\['+arrNum +'\\]');
      select.empty();
    });
  }
};

// Start to initial the main function
$(function(){ app.main(); });
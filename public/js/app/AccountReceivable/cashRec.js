var app = {
  _urlLedgerCard: 'ledgerCard',  
  _urlRpsView: 'rpsView',
  _urlReport: 'ledgerCardExport',
  _urlLedgerCardFix: 'ledgerCardFix',
  _urlBankInfo: 'bankInfo',
  _urlTenantInfo: 'tenantInfo',
  _urlDepositRefund: 'depositRefund',
  _urlEachReport: '',
  _urlRpsDeleteFile: 'rpsDeleteFile',
  _gridTable: '#gridTable',
  _columnData: [],
  _mainForm: '#applicationForm',
  _subForm: '#subApplicationForm',
  _showForm: '#showConfirmForm',
  _originalAmount: '',
  _isInitial: true,
  _originalEl: {},
  _tenantInfo: {},
  _date1: '',
  _createDefaultVal: {},
  main: function(){
    this.create();
    this.onArrowClick();
  },
//------------------------------------------------------------------------------
  create: function(){
    var _getForm = function(type, defaultVal){
      var $formId= $('#ledgerCardForm');
      $formId.html('');
      AJAX({
        url   : type + '/create',
        data  : app._createDefaultVal,
        success: function(createAjaxData){
          $formId.html(createAjaxData.html);
          app._columnData = createAjaxData.column;
          if(createAjaxData.text){
            $('#sideMsg').html(createAjaxData.text);
          }
          if(createAjaxData.isUpload){
            switch (type) {
              case 'rpsCheckOnly': app._storeRPSCheckOnly(type); break;
              case 'rpsCreditCheck': app._storeRPSCreditCheck(type); break;
              case 'rpsTenantStatement': app._storeRPSTenantStatement(type); break;
              case 'paymentUpload': app._storePaymentUpload(type); break;
              case 'invoiceUpload': app._storeInvoiceUpload(type); break;
              case 'depositUpload': app._storeDepositUpload(type); break;
            }
          }
          
          INPUTMASK({isIncludeDefaultDaterange: ($('#dateRange').val() === '' ? true : false)});
          app._initAutocomplete();
          app._setGridTableHeight();
          app._showledgerCardFromLink();          
          app._getBank();
          
          if(createAjaxData.tenantOption !== undefined){
            $('#tenant').html(createAjaxData.tenantOption);
          }
          // RESET THE UNIT AND TENANT
          $('#prop,#unit').unbind('down').on('keyup', function(e){
            var id = $(this).attr('id');
            if (e.keyCode != 13 && e.keyCode != 9 && e.keyCode != 16 ) {
              if(id == 'prop'){
                $('#unit').val('');
              }
              $('#tenant,#tenantInfo').html('');
              $('#initText').show();
              $('.bootstrap-table').hide();
            }
          });
          
          //##### SUBMIT THE LEDGER TO VIEW/POST INVOICE #####//
          $(app._mainForm).unbind('submit').on('submit', function(){
            window.location.href = url().split('#')[0] + '#'; // Need to refresh the page so that it won't keep the old redirect link
            
            app._isInitial = false;
            $('#initText').hide();
            var type = $('#type').val();
            var serialize  = $(this).serialize();
            
            if(type != 'depositCheck'){
              app._createDefaultVal = {prop:$('#prop').val(), unit:$('#unit').val(), tenant:$('#tenant').val(), dateRange:($('#dateRange').val() !== undefined ? $('#dateRange').val() : '')};
            }
            
            switch(type){
              case 'postInvoice':  app._storePostInvoice(type, serialize); break;
              case 'postPayment':  app._showPostPayment(type, serialize); break;
              case 'depositCheck': app._storeDepositCheck(type, serialize); break;
              default: app._showLedgerCard(serialize);
            }
            return false;
          });
        }
      });
    };
    
    _getForm($('#type').val());
    $('#type').on('change', function(){
      // HIDE AND SHOW THE PENAL
      var type = $(this).val();
      if(type.match(/ledgerCard|postPayment|postInvoice/)){
        $('#gridContainer,#tenantInfo,#submit').show();
        $('#sideMsgContainer').hide();
//        if(isEmpty(app._createDefaultVal)){
//          $('##tenantInfo').html('');
//        }
      } else{
        $('#gridContainer,#tenantInfo,#submit').hide();
        $('#sideMsgContainer').show();
//        $('#sideMsg').html('asfs');
      }
      if(type.match(/ledgerCard|postPayment|postInvoice|depositCheck/)){
        $('#submit').show();
      } else{
        $('#submit').hide();
      }
      _getForm(type);
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _showledgerCardFromLink: function(){
    if(app._isInitial){
      var urlhash = (url('#') !== undefined) ? url('#') : false;
      if(urlhash && urlhash.prop && urlhash.unit && urlhash.tenant){
        $('#prop').val(urlhash.prop);
        $('#unit').val(urlhash.unit);
        $('#initText').hide();
        app._getTenant(app._mainForm, urlhash.tenant);
      }
    }
  },
//------------------------------------------------------------------------------
  _storePostInvoice: function(type, serialize){
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> CONFIRM INVOICE</b>',
      content: 'Are you sure you want to invoice this tenant?',
      boxWidth:'450px',
      buttons: {
        confirm: {
          text: '<i class="fa fa-fw fa-check"></i> Confirm',
          btnClass: 'btn-green',
          action: function(){
            AJAXSUBMIT(app._mainForm, {
              url:  type,
              type: 'POST',
              success : function(storeAjaxData){
        //        app._getErrorPopupMsg(storeAjaxData);
                app._showLedgerCard();
                $('#amount,#service,#remark').val('');
              }
            });
          }
        },
        cancel: {
          text: '<i class="fa fa-fw fa-close"></i> Cancel',
          btnClass: 'btn-red',
          action: function(){
            app._showLedgerCard();
          }
        }
      },
    });
  },
//------------------------------------------------------------------------------
  _showPostPayment: function(type, serialize){
    AJAXSUBMIT(app._mainForm, {
      url     : type + '/showPostPaymentConfirm',
      data    : serialize,
      success: function(showAjaxData){
        if(showAjaxData.html !== undefined){
          CONFIRM({
            title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> CONFIRM PAYMENT</b>',
            content: showAjaxData.html,
            boxWidth:'850px',
            buttons: {
              confirm: {
                text: '<i class="fa fa-fw fa-check"></i> Confirm',
                btnClass: 'btn-green',
                action: function(){
                  app._storePostPayment(type);
                }
              },
              cancel: {
                text: '<i class="fa fa-fw fa-close"></i> Cancel',
                btnClass: 'btn-red',
                action: function(){
                  app._showLedgerCard();
                }
              }
            }
          });
        }
      }
    });
  },
//------------------------------------------------------------------------------
  _storePostPayment: function(type){
    AJAX({
      url     : type,
      type    : 'POST',
      data    : $(app._mainForm).serialize() + '&' + $(app._showForm).serialize(),
      success: function(storeAjaxData){
//        app._reuseBatchNumber(storeAjaxData); // DEAL WITH REUSE BATCH NUMBER
        app._showLedgerCard(); // SHOW LEDGER CARD
        $('#amount').val('');
      }
    });
  },
//------------------------------------------------------------------------------
  _storeDepositCheck: function(type, serialize){
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
              url:  type,
              type: 'POST',
              success : function(storeAjaxData){
                // DEAL WITH REUSE BATCH NUMBER
//                app._reuseBatchNumber(storeAjaxData);

                $('#sideMsg').prepend(storeAjaxData.sideMsg);
                $('#amount,#prop,#unit,#gl_acct,#remark').val('');
                $('#ar_bank').html('');
                $('.autocompleteAfter').remove();
                app._createDefaultVal = {}; // We need to reset this so that ledger card, record payment, and post invoice work correctly
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
  _showLedgerCard: function(serialize){
    app._getLedgerCardToolBar();
    $('.tabClass').unbind('click').on('click', function(){
      var gridId = $(this).attr('data-key');
      app._grid(gridId, $(app._mainForm).serialize());
    });
    $('.tabClass[data-key=ledgerCard]').unbind('trigger').trigger('click');
    
    // need to suggestion cache and current suggestions after AJAX call otherwise it still show up
    $('.autocomplete').autocomplete('clear');
  },
//------------------------------------------------------------------------------
  _showLedgerCardFix: function(args,name){
    var sData = {};
    sData[name] = args[name];
    sData.cntl_no = args.cntl_no;
    sData.oldAmount = args.amount;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> CONFIRM FIX TRANSACTION</b>',
      content: function(){
        var self    = this;
        AJAX({
          url    : app._urlLedgerCardFix + '/showFix',
          data   : sData,
          success: function(showAjaxData){
            var _autocomplete = function(showAjaxData){
              AUTOCOMPLETE({
                formId: app._showForm,
                callback: function(dataSet, formId){
                  if(dataSet.append_prop !== undefined){
                    $(app._showForm + ' #unit,' + app._showForm + ' #vendid,' + app._showForm + ' #gl_acct').val('');
                    $(app._showForm + ' #tenant').html('');
                  }
                  
                  // TO PREVENT THE DATA TO AUTOCOMPLETE TO REAPPEAR
                  if(!dataSet.append_gl_acct){
                    app._getTenant(app._showForm);
                  }
                }
              });
            };
            
            self.$content.html(showAjaxData.html);
            var isError = showAjaxData.isError !== undefined ? showAjaxData.isError : false;
            $('#removePaymentContainer').html(showAjaxData.removePaymentField);
            _autocomplete(showAjaxData);
            
            if(showAjaxData.isFixInvoiceWithPayment){
              $('#removePaymentContainer').hide();
//              $('#method').unbind('change').on('change', function(){
//                Helper.isShow('#removePaymentContainer', ($(this).val() == 'apply' ? false : true));
//                _autocomplete(showAjaxData);
//              });
            }
            app._storeLedgerCardFix(sData, self);
            app._isSameTrust();
          }
        });
      },
      boxWidth:'1000px',
      buttons: {
        cancel: {
          text: '<i class="fa fa-fw fa-close"></i> Close',
          btnClass: 'btn-red',
          action: function(){
            app._originalEl[0].innerHTML = app._originalAmount;
          }
        }
      },
//      onContentReady: function(){
//        // CHECK TO SEE IF THE TRUST IS THE SAME OR NOT //
//        $(app._showForm + ' #prop').unbind('focusout').on('focusout', function(){
//          AJAX({
//            url   : app._urlLedgerCardFix,
//            data  : {oldProp: $(app._mainForm + ' #prop').val() , prop: $(app._showForm + ' #prop').val()}, 
//            success: function(indexAjaxData){
//              Helper.removeDisplayError();
//              if(!indexAjaxData.error){
//                $('#isSameTrust').val(indexAjaxData.isSameTrust);
//                Helper.isShow('#additionalForm', (indexAjaxData.isSameTrust ? false : true));
//              } else{
//                indexAjaxData.formId = app._showForm;
//                Helper.displayError(indexAjaxData);
//              }
//            }
//          });
//        });
//      }
    });
  },
//------------------------------------------------------------------------------
  _isSameTrust: function(){
    // CHECK TO SEE IF THE TRUST IS THE SAME OR NOT //
//    $(app._showForm + ' #prop').unbind('focusout').on('focusout', function(){
    $(app._showForm + ' #prop').on('focusout', function(){
      AJAX({
        url   : app._urlLedgerCardFix,
        data  : {oldProp: $(app._mainForm + ' #prop').val() , prop: $(app._showForm + ' #prop').val()}, 
        success: function(indexAjaxData){
          Helper.removeDisplayError();
          if(!indexAjaxData.error){
            $('#isSameTrust').val(indexAjaxData.isSameTrust);
            Helper.isShow('#additionalForm', (indexAjaxData.isSameTrust ? false : true));
          } else{
            indexAjaxData.formId = app._showForm;
            Helper.displayError(indexAjaxData);
          }
        }
      });
    });
  },
  _storeLedgerCardFix: function(sData, self){
    $(app._showForm).unbind('submit').on('submit', function(){
      var serialize = $(this).serializeArray();     
      for(var i in serialize){
        var v = serialize[i];
        sData[v.name] = v.value;
      }
      AJAXSUBMIT(app._showForm, {
        url    : app._urlLedgerCardFix,
        data   : sData,
        type   : 'POST',
        success: function(storeAjaxData){
          self.$content.html(storeAjaxData.html);
          GRIDREFRESH('#ledgerCard');
          app._getLedgerCardToolBar();
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  _grid: function(gridId, serialize){
    if($('#prop').val() != '' && $('#unit').val() != ''  && $('#tenant').val() != ''){
      var id = '#' + gridId;
      $(id).bootstrapTable('destroy');
      var column = app._columnData;
      GRID({
        id             : id,
        url            : app._urlLedgerCard + '/' + gridId + '?' + serialize,
        urlReport      : app._urlReport,
        dataReport     : serialize,
        isOpInUrlReport: true,
        columns        : column[gridId].columns,
        dataField      : 'row',
        fixedColumns   : false,
        heightButtonMargin : 175,
        fixedNumber    : 1,
        sortName       : 'prop',
        pagination     : false,
        filterControl  : false,
        reportList     : column[gridId].reportList,
      });

      $(id).unbind('click-cell.bs.table').on('click-cell.bs.table', function(e,field,value,row,$element){
        switch(field){
          case 'tx_code': app._viewRpsImage(row); break;
        }
      });

      $(id).unbind('load-success.bs.table').on('load-success.bs.table', function (e, data, value, row, $element) {
        Helper.removeDisplayError();
        Helper.displayError(data);
        app._getTenant(app._mainForm);
      });

      $(id).unbind('editable-shown.bs.table').on('editable-shown.bs.table', function(e, field, row, $element) {
        app._originalAmount = row.amount;
        app._originalEl = $element;
        setTimeout(function(){ 
          $('.editable-input input').select();
        });
      });

      $(id).unbind('editable-save.bs.table').on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
        app._showLedgerCardFix(args, name);
      });
    }
  },
//------------------------------------------------------------------------------
  _viewRpsImage: function(row){
    var pattern = new RegExp(/payment/i);
    if(pattern.test(row.tx_code) && String(row.batch).length >= 9 && row.job !== undefined && row.job.length > 0){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW CHECK IMAGES</b>',
        content: function(){
          var self    = this;
          var reqData = {
            op: 'getRpsImage',
            prop: row.prop,
            batch: row.batch,
            unit: row.unit,
            job: row.job,
            tenant: row.tenant,
            date1 : row.date1,
          };
          //Fetch Transaction Related Document I.e. (Money Order or Coupon) and display Front and Back Images in modal
          AJAX({
            url    : app._urlRpsView,
            data    : reqData,
            success: function(editUploadAjaxData){
              if(editUploadAjaxData.error !== undefined && editUploadAjaxData.error.popupMsg !== undefined){
                self.close();
                ALERT({content: editUploadAjaxData.error.popupMsg});
              } else{
                //Preview files
                self.$content.html(editUploadAjaxData.html);
              }
            }
          });
        },
        boxWidth:'80%',
        buttons: {
          cancel: {text: 'Close'}
        },
        onContentReady: function(){
        }
      });
    }
  },
  //------------------------------------------------------------------------------
  onArrowClick: function() {
    $('#arrow-btn').on('click', function() {
      var $arrowBtn      = $('#arrow-btn');
      var $formContainer = $('#formContainer');
      var $gridContainer = $('#gridContainer');
      var windowWidth    = window.innerWidth;
      var rotateDeg      = windowWidth > 991 ? '180' : '270';
      if(!$formContainer.hasClass('hideContainer')) {
        // Hide Form
        $formContainer.removeClass('showContainer').addClass('hideContainer');
        $gridContainer.removeClass('col-md-9').addClass('col-md-12');
        $arrowBtn.css({
          "-webkit-transform": "rotate(" + rotateDeg + "deg)",
          "-moz-transform": "rotate(" + rotateDeg + "deg)",
          "-o-transform": "rotate(" + rotateDeg + "deg)",
          "transform": "rotate(" + rotateDeg + "deg)" 
        });
      }else {
        // Show Form
        $gridContainer.removeClass('col-md-12').addClass('col-md-9');
        $formContainer.removeClass('hideContainer').addClass('showContainer');
        $arrowBtn.css({
          "-webkit-transform": "",
          "-moz-transform": "",
          "-o-transform": "",
          "transform": "" 
        });
      }
      // Reset the column header width after change in grid width
      setTimeout(function() {
        $('.table').bootstrapTable('resetView');
      }, 500);
    });
  },
//------------------------------------------------------------------------------
  _storeDepositRefund: function(){
    $('#issueDeposit,#reverseIssueDeposit').unbind('click').click(function(){
      var id = $(this).attr('id');
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW CHECK IMAGES</b>',
        content: function(){
          var self    = this;
          AJAX({
            url    : app._urlDepositRefund + '/create',
            data   : {op:id},
            success: function(showAjaxData){
              self.$content.html(showAjaxData.html);
              AUTOCOMPLETE();
            }
          });
        },
        boxWidth: '500px',
        buttons: {
          confirm: {
            text: '<i class="fa fa-fw fa-check"></i> Confirm',
            btnClass: 'btn-green',
            action: function(){
              AJAX({
                url  : app._urlDepositRefund,
                type : 'POST',
                data : $(app._mainForm).serialize() + '&op=' + id + '&gl_acct=' + $('#gl_acct').val(),
                success: function(storeAjaxData){
                  Helper.displayError(storeAjaxData);
                  if(!storeAjaxData.error){
                    CONFIRM({
                      title: '<b class="text-red">CONFIRMATION</b>',
                      content: storeAjaxData.popupMsg,
                      boxWidth: '500px',
                      buttons: {
                        cancel: {
                          text: '<i class="fa fa-fw fa-close"></i> Close',
                          btnClass: 'btn-red',
                        }
                      }
                    });

                    $('#tenantDepositDetailWrapper').html(storeAjaxData.tenantDepositDetail);
                    // UPDATE tenant deposit detail
                    app._tenantInfo[storeAjaxData.data.tenant] = storeAjaxData.tenantDepositDetail;
                    app._storeDepositRefund();
                  }
                  
                }
              });
              app._initAutocomplete();
            }
          },
          cancel: {
            text: '<i class="fa fa-fw fa-close"></i> Cancel',
            btnClass: 'btn-red',
          }
        }
      });
    });
  },
//------------------------------------------------------------------------------
  _initAutocomplete: function(){
    AUTOCOMPLETE({
      formId: app._mainForm,
      param: {includeField:['list_bank', 'list_unit'], additionalField: {prop: $('#prop').val() } },
      callback: function(dataSet){
        if(dataSet.append_prop !== undefined){
          $('#unit').val('');
          $('#tenant,#tenantInfo').html('');
          app._getBank();
        }
//        app._getTenant(app._mainForm);
      }
    });
    
    $(app._mainForm + ' #unit').on('focusout', function(){
      if($(app._mainForm + ' #prop').val()){
        app._getTenant(app._mainForm);
      }
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
  _getTenant: function(formId, tenant){
    var tenant = tenant || '';
    var prop = $(formId + ' #prop').val();
    var unit = $(formId + ' #unit').val();
    if(prop !== '' && unit !== ''){
      if($('#type').val() != 'depositCheck'){
        AJAX({
          url   : app._urlTenantInfo,
          data  : {prop: prop, unit: unit, tenant: $(formId + ' #tenant').val()}, 
          success: function(createAjaxData){
            if(formId == app._mainForm){
              app._tenantInfo  = createAjaxData.tenantInfo;
              app._getTenantChange(createAjaxData.sltTenant);
            }
            $(formId + ' #tenant').html(createAjaxData.html);
            
            
            // THIS IS SPECIFICALLY FOR LINK FROM OTHER PAGE
            if(tenant){
              $('#tenant').val(tenant);
              app._showLedgerCard();
            }
            
            $('.autocomplete').autocomplete('clear');
          }
        });
      }
    }
  },
  _getTenantChange: function(sltTenant){
//    var _triggerSubmit = function(){
//      if($('#type').val() == 'ledgerCard'){
//        $('#submit').unbind('trigger').trigger('submit');
//      }
//    };
    app._storeDepositRefund();
    if(sltTenant !== undefined){
      $('#tenantInfo').html(app._tenantInfo[sltTenant]);
//      _triggerSubmit();
      app._storeDepositRefund();
    }
    $('#tenant').unbind('change').on('change', function(){
      $('#tenantInfo').html(app._tenantInfo[$(this).val()]);
//      _triggerSubmit();
      app._storeDepositRefund();
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
  _getLedgerCardToolBar: function(){
    AJAX({
      url     : app._urlLedgerCard,
      data    : $(app._mainForm).serialize(),
      success: function(postAjaxData){
        $('#toolbar').html(postAjaxData.html);
        TOOLTIPSTER('.tip');
      }
    });
  },
//------------------------------------------------------------------------------
//  _reuseBatchNumber: function(storeAjaxData){
//    $('#batch').html(storeAjaxData.batchHtml);
//    $('#batch').unbind('change').on('change', function(){
//      if($(this).val()){
//        $('#date1').val(storeAjaxData.date1).attr('readonly', true);
//      } else{
//        $('#date1').attr('readonly', false);
//      }
//
//      $('#date1').datepicker('destroy');
//      $('#date1:not([readonly])').datepicker({
//        format:'mm/dd/yyyy',
//        autoclose:true,
//        immediateUpdates:true,
//        clearBtn:true,
//        todayHighlight: true,
//        keyboardNaviagtion: false,
//      });
//    });
//  },
/*******************************************************************************
 ************************ RPS UPLOAD SECTION FUNCTION **************************
 ******************************************************************************/
  _storeRPSCheckOnly: function(type){
    UPLOAD({maxConnections: 1}, '/' + type, {
      op:'CashRec', type:type, route: 'show', onComplete: function(responseJSON){
        if(responseJSON !== undefined){
           if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
            ALERT({content: responseJSON.error.popupMsg});
          } else {
            $('#sideMsg').html(responseJSON.html);
            $(app._subForm).unbind('submit').on('submit', function(e){
              dd( $(this).serialize());
              AJAXSUBMIT(app._subForm, {
                url   : '/' + type,
                data  : $(this).serialize() + '&route=store',
                type  : 'POST',
                success: function(storeAjaxData){
                  $('#sideMsg').html(storeAjaxData.sideMsg);
                  app._destoryRpsFile(storeAjaxData);
                }
              });
              return false;
            });
          }
        }
      }}
    );
  },
  _storeRPSCreditCheck: function(type){
    UPLOAD({}, '/' + type, {
      op:'CashRec', type:type, route: 'show', onComplete: function(responseJSON){
        if(responseJSON !== undefined){
          if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
            ALERT({content: responseJSON.error.popupMsg});
          } else {
            $('#sideMsg').prepend(responseJSON.sideMsg);
            app._destoryRpsFile(responseJSON);
          }
        }
      }}
    );
  },
  _storeRPSTenantStatement: function(type){
    UPLOAD({}, '/' + type, {
      op:'CashRec', type:type, route: 'show', onComplete: function(responseJSON){
        if(responseJSON !== undefined){
          if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
            ALERT({content: responseJSON.error.popupMsg});
          } else{
            $('#sideMsg').prepend(responseJSON.sideMsg);
            app._destoryRpsFile(responseJSON);
          }
        }
      }}
    );
  },
  _storePaymentUpload: function(type){
    UPLOAD({}, '/' + type, {
      op:'CashRec', type:type, route: 'show', onComplete: function(responseJSON){
        if(responseJSON !== undefined){
          if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
            ALERT({content: responseJSON.error.popupMsg});
          } else{
            $('#sideMsg').prepend(responseJSON.sideMsg);
          }
        }
      }}
    );
  },
  _storeInvoiceUpload: function(type){
    UPLOAD({},'/' + type,{
      op: 'CashRec', type:type, route:'show',onComplete: function(responseJSON){
        if(responseJSON !== undefined){
          if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
            ALERT({content: responseJSON.error.popupMsg});
          } else {
            $('#sideMsg').prepend(responseJSON.sideMsg);  
          }
        }    
      } 
    });
  },
  _storeDepositUpload: function(type){
    UPLOAD({},'/' + type,{
      op: 'CashRec', type:type, route:'show',onComplete: function(responseJSON){
        if(responseJSON !== undefined){
          if(responseJSON.error !== undefined && responseJSON.error.popupMsg !== undefined){
            ALERT({content: responseJSON.error.popupMsg});
          } else {
            $('#sideMsg').prepend(responseJSON.sideMsg);  
          }
        }    
      } 
    });
  },
  _destoryRpsFile: function(responseJSON){
    AJAX({
      url     : app._urlRpsDeleteFile + '/' + responseJSON.file ,
      type   : 'DELETE',
      success: function(postAjaxData){
      }
    });
  }
};
$(function(){ app.main(); });// Start to initial the main function

var app = {
  _url: '/creditCheck',
  _urlUpload: '/uploadApplication',
  main: function(){
    this.addAdditionTenant();
    this.store();
    INPUTMASK();
    AUTOCOMPLETE({
      callback:function(callbackData){
        var unitDetail = callbackData.detail_unit.split('|');
        var rentDeposit = {};
        for(var i in unitDetail){
          var p = unitDetail[i].split('~');
          var rentDep = p[1].split(' - ');
          rentDeposit[p[0]] = {rent:rentDep[0], deposit:rentDep[1]};
        }
        $('#new_rent,#sec_deposit').val('');
        $('#unit').unbind('change').on('change', function(){
          var val = $(this).val();
          $('#new_rent').val('$' + rentDeposit[val].rent);
          $('#sec_deposit').val('$' + rentDeposit[val].deposit);
        });
      }
    });
    UPLOAD(
      {},
      app._urlUpload, 
      {op:'CreditCheckUpload', type:'application'}
    );
    
    $('#prop').on('keyup', function(e){
      if (e.keyCode != 13 && e.keyCode != 9 && e.keyCode != 16 ) {
        $('#new_rent,#sec_deposit').val('');
        $('#unit').html('');
      }
    });
    
  },
  addAdditionTenant: function(){
    var id = $('#tenantInfoWrapper');
    var tenantInfo = id.html();
    var index = 0;
    $('#additionalTenant,#removeTenant').on('click', function(){
      var isRemove = ($(this).attr('id') == 'removeTenant') ? 1 : 0;
      switch (isRemove) {
        case 1:
          if(index >= 1){
            $('#eachTenant' + index).remove(); 
            index = (index <= 0) ? 0 : --index;
          }
          break;
        default:
          ++index;
          var html = Helper.replaceArrayKeyId(tenantInfo, index);
          html = html.replace(/Tenant #1/, 'Tenant #' + (index + 1));
          html = html.replace(/collapse0/g, 'collapse' + index);
          html = html.replace(/eachTenant0/, 'eachTenant' + index);
          id.prepend(html);
      }
      $('#tenantNum').html((index + 1));
      INPUTMASK();
    });
  },
  store: function(){
    var id = '#applicationForm';
    $(id).unbind('submit').on('submit', function(){
      var checkSSNData = JSON.parse(JSON.stringify($(id).serialize() + '&op=validateSSN'));
      var _postShow = function(id){
        var _submitApproval = function(ajaxData, status){
          // START TO UPDATE APPLICATION
          AJAX({
            url    : app._url + '/' + ajaxData.appId,
            data   : {op:'RunApproval',application_status: status, sec_deposit_add: $('#showTenantAlertFom #sec_deposit_add').val(), sec_deposit_note: $('#showTenantAlertForm #sec_deposit_note').val()},
            //data   : {op:'RunApproval', status: status, sec_deposit_add: $('#showTenantAlertForm #sec_deposit_add').val(), sec_deposit_note: $('#showTenantAlertForm #sec_deposit_note').val()},
            type   : 'PUT',
            success: function(updateAjaxData){
              $('#applicationForm').html('').before(updateAjaxData.updateCreateMsg);
            }
          });
        };
        
        AJAXSUBMIT(id, {
          url:  app._url,
          type: 'POST',
          success: function(ajaxData){
            AJAX({
              url: app._url + '/' + ajaxData.id,
              data:{isCreateApp:1, op:'ShowTenantAlert'},
              dataType: 'HTML',
              success: function(viewTenantAjaxData){
                CONFIRM({
                  title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> TENANT CREDIT CHECK RESULT</b>',
                  content: viewTenantAjaxData,
                  boxWidth:'60%',
                  buttons: {
//                      confirm :{
//                        text: 'Confirm',
//                        btnClass: 'btn-green',
//                        action: function(){
//                            _submitApproval(ajaxData,'Change Status');
//                        }
//                      },
                    rejected : {
                      text     : 'Reject',
                      btnClass : 'btn-danger',
                      action   : function(){
                        _submitApproval(ajaxData,'Rejected');   
                      }
                    },
                    approved : {
                      text     : 'Approve',
                      btnClass : 'btn-green',
                      action   : function(){
                        _submitApproval(ajaxData,'Approved');
                      }
                    }
                  }, 
                  onContentReady: function () {
                    INPUTMASK();
                  }
                }); 
              }
            });
          }
        });
      };
      
      AJAXSUBMIT(id, {
        url:  app._url,
        type: 'POST',
        data: checkSSNData,
        success : function(ajaxData){
          if(ajaxData.html){
            CONFIRM({
              title: '<b class="text-red"><i class="fa fa-fw fa-exclamation-circle"></i> ATTENTION</b>',
              content: ajaxData.html,
              boxWidth:'850px',
              buttons: {
                submit: {
                  text: ' Yes ',
                  btnClass: 'btn-green',
                  action: function(confirmButton){
                    _postShow(id);
                  }
                },
                cancel: { 
                  text: ' No ',
                  btnClass: 'btn-danger',
                  action: function(confirmButton){
                    DISABLEDSUBMIT(id, false);
                  }
                }
              }
            });
          } else{
            _postShow(id);
          }
        }
      });
      return false;
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
};
// Start to initial the main function
$(function(){ app.main(); });
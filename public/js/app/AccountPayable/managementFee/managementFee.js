var app = {
  _url: '/managementFee',
  _urlApproval: '/approveManagementFee',
  _urlReportList: '/managementFeeExport',
  _gridTable: '#gridTable',
  _idCol:'vendor_util_payment_id',
  _selectedIds:{},
//------------------------------------------------------------------------------
  main: function(){
    this.grid();
    this.submitToApproval();
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
          sortName       : 'prop',
          reportList     : ajaxData.reportList,
          dateColumns    : ['start_date'],
        });

        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
        });
      }
    });
  },
//------------------------------------------------------------------------------
  submitToApproval: function(){
    $('#generatePayment').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"> GENERATE NEW MANAGEMENT FEE(S)</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._urlApproval + '/create',
            success: function(responseData){
              self.$content.html(responseData.html);
              
              var formId = '#managementFeeApprovalForm';
              $(formId).unbind('submit').on('submit',function(){
                AJAXSUBMIT(formId,{
                  url: app._urlApproval,
                  type: 'POST',
                  success: function(storeAjaxData){
                    if(storeAjaxData !== undefined){
                      if(storeAjaxData.error !== undefined && storeAjaxData.error.popupMsg !== undefined){
                        ALERT({content: storeAjaxData.error.popupMsg});
                      } else {
                        if(storeAjaxData.sideErrMsg !== undefined){
                          $('#sideMsg').prepend(storeAjaxData.sideErrMsg);  
                        }
                          
                        if(storeAjaxData.sideMsg !== undefined){
                          $('#sideMsg').prepend(storeAjaxData.sideMsg);
                          app._resetForm(formId);
                        }
                        GRIDREFRESH(app._gridTable);
                      }
                    }
                  }
                });
                return false;
              });
            }
          });
        },
        onContentReady: function(){
          app._setPanelHeight();
          app._bindEvent();
          INPUTMASK();
          AUTOCOMPLETE();
        },
        boxWidth: '1200px',
        buttons: {
          cancel: {
            text:'Close',
          }
        }
      })
      return false;
    });
  },
//------------------------------------------------------------------------------
  _setPanelHeight: function(){
    const minHeight  = 300;
    var rawVal = $('#managementFeeApprovalForm').height();
    var height = (rawVal > minHeight) ? rawVal : minHeight;
    $('#sideMsg').css('height', height);
  },
//------------------------------------------------------------------------------
  _bindEvent: function(){
    $('#invoice_date').on('change',function(){
       var value = $(this).val();
       var month = moment(value).format('MMMM');
       var year  = moment(value).format('YYYY');
       $('#remark').val('Management Fee ' + month + ' ' + year);
    });
  },
//------------------------------------------------------------------------------
  _resetForm: function(formId){
    $(formId)[0].reset();
    $('#prop_type').val('');
    $('#mangtgroup').val('');
  }
};

// Start to initial the main function
$(function(){ app.main(); });

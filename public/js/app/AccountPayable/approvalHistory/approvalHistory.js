var app = {
  _url: '/approvalHistory',  
  _urlUpload: '/uploadApprovalHistory',
  _urlReport: '/approvalHistoryExport',
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
        GRID({
          id             : app._gridTable,
          url            : app._url,
          urlReport      : app._urlReport,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'vendid',
          dateColumns    : ['invoice_date'],
          reportList     : ajaxData.reportList,
        });
        $(app._gridTable).on('click-cell.bs.table', function (e, field, value, row, $element) {
          if(field == 'invoiceFile'){
            app._viewInvoice(row); 
          }
        });
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
          $('a.checkCopyLink').unbind('click').on('click',function(){
            var rowId = $(this).attr('data-vendor-payment-id');
            if(rowId > 0){
              CONFIRM({
                title: '<b class="text-red"><i class="fa fa-fw fa-money-check"></i>VIEW CHECK COPY</b>',
                content: function(){
                  var self = this;
                  AJAX({
                    url: app._urlReport,
                    data: {op:'checkCopyUploadView',vendor_payment_id:rowId},
                    dataType:'HTML',
                    success: function(ajaxData){
                      self.$content.html(ajaxData);
                    }
                  });
                },
                onContentReady: function(){
                  AUTOCOMPLETE();
                  INPUTMASK();
                  $('.eachPdf').unbind('click').on('click',function(){
                    var path = $(this).attr('data-path');
                    $('.eachPdf').removeClass('active');
                    $(this).addClass('active');
                    AJAX({
                      url: app._urlReport,
                      data: {op:'checkCopy',vendor_payment_id:rowId,path:path},
                      success: function(responseData){
                        $('#uploadView').html(responseData.html);
                      }
                    });
                  });
                  $('.eachPdf').first().trigger('click');
                },
                boxWidth: '90%',
                buttons: {
                  cancel: {
                    text: 'Close',
                  }
                }
              });    
            }
            
          });
        });
        
        
      }
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
//------------------------------------------------------------------------------
  _viewInvoice: function(row){
    var id = row.id;
    if(row.invoiceFile){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW INVOICE</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlUpload,
            data    : {id: id},
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              self.$content.html(editUploadAjaxData);
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
};

// Start to initial the main function
$(function(){ app.main(); });
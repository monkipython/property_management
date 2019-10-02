var app = {
  _url: 'report',  
  _urlRpsView: 'rpsView',
  _urlApprovalHistoryExport: '/approvalHistoryExport',
  _urlEachReport: '',
  _gridTable: '#gridTable',
  _columnData: [],
  _isTab : false,
  _isInitial: true,
  _navClicked: false,
  _formId: '#applicationForm',
  main: function(){
    this.getHeight();
    this.create();
    this.store();
    this.onArrowClick();
    SELECT2('#report', '#dropdownData', {placeholder:'Select Report'});
    this.showReportFromLink();
  },
//------------------------------------------------------------------------------
  create: function(){
    $('#openNav').on('click', function(){
      // Refresh the grid table every time the user clicks the nav to resize the page
      var navOpen = $('#mainBody').attr('class').match(/sidebar\-collapse/) ? '' : 'sidebar-collapse';
      Cookies.set('nav', navOpen);
      
      //Set a global variable indicating that the grid table refresh was done through resizing the window and not resubmitting the form
      app._navClicked = true;
      GRIDREFRESH('#gridTable');
    });
    
    $('#submit').on('click',function(){
      //Set a global variable indicating that the grid table refresh was done through resubmitting the form, meaning validation or any error message should be shown
      app._navClicked = false; 
    });
    
    $('#report').on('change', function(){
      var report = app._getReportValue();
      var $formId= $('#reportForm');
      $('#reportHeader').text($(this).find(":selected").text());
      $formId.html('');
      app._urlEachReport = '/' + report;
      if(report != ''){
        AJAX({
          url   : app._urlEachReport + '/create',
          success: function(createAjaxData){
            var $reportBody = $('#reportBody');
            $reportBody.empty();
            if(createAjaxData.tab) {
              app._isTab = true;
            }else {
              $('<table id="gridTable"></table>').appendTo($reportBody);
              app._isTab = false;
              app._columnData = createAjaxData.column;
            }
            $formId.html(createAjaxData.html);
            INPUTMASK({isIncludeDefaultDaterange: createAjaxData.isIncludeDefaultDaterange !== undefined ? createAjaxData.isIncludeDefaultDaterange : true});
            AUTOCOMPLETE();
            app._copyField();
          }
        });
      }
    });
  },
//------------------------------------------------------------------------------
  store: function(){
    var formId = app._formId;
    var $reportBody = $('#reportBody');
    $(formId).unbind('submit').on('submit', function(submitEvent){
      var serialized = $(this).serialize();
      window.location.href = url().split('#')[0] + '#' + serialized; // Need to refresh the page so that it won't keep the old redirect link
      if(app._urlEachReport != '' && !app._isTab){
        app._grid(serialized, app._urlEachReport,submitEvent);
      }else if(app._isTab) {
        AJAXSUBMIT(formId, {
          url:  app._urlEachReport,
          data: serialized + '&op=tab',
          success : function(ajaxData){
            app._columnData = ajaxData.column; 
            $reportBody.empty();
            $(ajaxData.tab).appendTo($reportBody);
            var firstTab = Object.keys(app._columnData)[0];
            if(ajaxData.sortTabs === undefined || ajaxData.sortTabs === true){
              firstTab = Object.keys(app._columnData).sort()[0];
            }
            app._showPropUnits(serialized, firstTab);
          }
        });
      }else{
        ALERT({
          title: '<b class="text-red"><i class="fa fa-exclamation"></i> ERROR</b>',
          content: 'Please select report you want to run.',
        });
      }
      return false;
    });
  },
//------------------------------------------------------------------------------
  getHeight: function(){
    var _insertHeight = function(){
      var minHeight = 300;
      var rawVal    = $(window).height() - 240;
      var height    =  (rawVal > minHeight) ? rawVal : minHeight;
      $('.gridTable').height(height);
    };
    _insertHeight();
    $(window).resize(function(){
      _insertHeight();
    });
  },
  showReportFromLink: function(){
    if(app._isInitial){
      var urlhash = (url('#') !== undefined) ? url('#') : false;
      if(urlhash && urlhash.report){
        $('#report').val(urlhash.report).trigger('change');
        setTimeout(function() {
          $.each(urlhash, function(key, value) {
            $('#'+key).val(value);
          });
          $('#submit').trigger('submit');
        }, 750);
      }
    }
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _grid: function(serialize, urlEachReport,submitEvent){
    $(app._gridTable).bootstrapTable('destroy');
    var ajaxData = app._columnData;
   
    GRID({
      id             : app._gridTable,
      sortName       : 'prop',
      url            : urlEachReport + '?' + serialize,
      urlReport      : urlEachReport,
      dataReport     : serialize,
      showColumns    : false,
      heightButtonMargin : 210,
      isOpInUrlReport: true,
      columns        : ajaxData.columns,
      fixedColumns   : false,
      fixedNumber    : 1,
      filterControl  : false,
      pagination     : false,
      reportList     : ajaxData.reportList,
      responseHandler: function(res){
        if(!(app._navClicked)  && submitEvent !== undefined && submitEvent.isTrigger === undefined && submitEvent.type === 'submit' && res.error !== undefined && res.error.msg !== undefined){
          ALERT({
           content: res.error.msg,
          });
        }
        app._navClicked = false;
        return res;
      },
    });
    
    $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
    });
    
  },
//----------------------------------------- ------------------------------------
  _gridTab: function(gridId, serialize){
    var id = '#' + gridId;
    id = id.replace('*','');
    $(id).bootstrapTable('destroy');
    var column = app._columnData;
    GRID({
      id             : id,
      url            : app._urlEachReport + '?' + serialize + '&selected=' + gridId,
      urlReport      : app._urlEachReport + '?' + serialize + '&selected=' + gridId,
      columns        : column[gridId].columns,
      dataField      : 'row',
      fixedColumns   : false,
      fixedNumber    : 1,
      heightButtonMargin : 210,
      sortName       : 'prop',
      pagination     : false,
      filterControl  : false,
      reportList     : column[gridId].reportList,
    });
    $(id).on('load-success.bs.table', function (e, data, value, row, $element) {
      Helper.removeDisplayError();
      Helper.displayError(data);
      app._viewCheckImage();
    });
    
     $(id).unbind('click-cell.bs.table').on('click-cell.bs.table', function(e,field,value,row,$element){
      var newValue  = value.replace(/&nbsp;|&emsp;/g,'');
      var elements  = $.parseHTML(newValue);
      var elem      = Array.isArray(elements) && elements.length > 0 ? elements[0] : '';
      if($(elem).hasClass('rpsViewClick')){
        row.batchValue   = $(elem).text().trim();
        app._viewRpsImage(row);
      }
    });
    if(app._urlEachReport==='/trailBalanceReport'){
      // Let the overflow-y can scroll to the bottom when there is a lot of data in the table. 
      $('.box-body').css('height','100%');
    }
  },
//----------------------------------------- ------------------------------------
   _showPropUnits: function(serialize, dataKey){
    $('.tabClass').unbind('click').on('click', function(){
      var gridId = $(this).attr('data-key');
      app._gridTab(gridId, serialize);
    });
    $('.tabClass[data-key="'+ dataKey +'"]').unbind('trigger').trigger('click');
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _getReportValue: function(){
    return $('#report').val();
  },
//------------------------------------------------------------------------------
  _copyField: function(){
    $('.copyTo').focus(function() {
      var id = $(this).attr('id').replace(/^to/, '#');
      var val = $(id).val();
      $(this).val(val).select();
    });
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
  _viewRpsImage: function(row){
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW CHECK IMAGES</b>',
      content: function(){
        var self    = this;
        var reqData = {
          op: 'getRpsImage',
          prop: row.prop,
          batch: row.batchValue,
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
  },
  _viewCheckImage: function(){
    $('.checkNo').unbind('click').on('click', function() {
      var rowId = $(this).attr('data-vendor-payment-id');
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-money-check"></i>VIEW CHECK COPY</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._urlApprovalHistoryExport,
            data: {op:'checkCopyUploadView',vendor_payment_id:rowId},
            dataType:'HTML',
            success: function(ajaxData){
              self.$content.html(ajaxData);
            }
          });
        },
        onContentReady: function(){
          $('.eachPdf').unbind('click').on('click',function(){
            var path = $(this).attr('data-path');
            $('.eachPdf').removeClass('active');
            $(this).addClass('active');
            AJAX({
              url: app._urlApprovalHistoryExport,
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
//      var imgPath = $(this).attr('data-img');
//      CONFIRM({
//        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW CHECK IMAGES</b>',
//        content: function(){
//          var self    = this;
//          self.$content.html('<img src="' + imgPath + '" />');
//        },
//        boxWidth:'80%',
//        buttons: {
//          cancel: {text: 'Close',
//                   btnClass: 'btn-red'}
//        }
//      });
    });
  },
};
$(function(){ app.main(); });// Start to initial the main function

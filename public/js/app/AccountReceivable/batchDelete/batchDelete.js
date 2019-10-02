var app = {
  _url: '/batchDelete',  
  _urlEachReport: '',
  _gridTable: '#gridTable',
  _columnData: [],
  _formId: '#applicationForm',
  main: function(){
    this.show();
    this.onArrowClick();
    INPUTMASK();
    this.setGridTableHeight();
  },
//------------------------------------------------------------------------------
  show: function(){
    $(app._formId).unbind('submit').on('submit', function(e){
      AJAXSUBMIT(app._formId, {
        url   : app._url + '/show',
        success: function(showAjaxData){
          $('#reportBody').show();
          $('#sideMsgContainer').hide();
          
          $('#toolbar').html(showAjaxData.toolbar);
          app._grid(showAjaxData);
          app._deleteBatch(showAjaxData);
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  setGridTableHeight: function() {
    const minHeight = 300;
    var rawVal = $(window).height() - $('.main-header').outerHeight() - 150;
    var height = (rawVal > minHeight) ? rawVal : minHeight;
    $('.gridTable').css('height', height);
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _grid: function(showAjaxData){
    var id = app._gridTable;
    $(id).bootstrapTable('destroy');
    GRID({
      id             : id,
      data           : showAjaxData.rows,
      columns        : showAjaxData.gridInfo.columns,
      dataField      : 'row',
      fixedColumns   : false,
      heightButtonMargin : 150,
      fixedNumber    : 1,
      sortName       : 'prop',
      pagination     : false,
      filterControl  : false,
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
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
  _deleteBatch: function(showAjaxData){
    $('#delete').on('click', function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> CONFIRM DELETE BATCH</b>',
        content: Html.errMsg('You are about to delete this batch'),
        boxWidth:'450px',
        buttons: {
          confirm: {
            text: '<i class="fa fa-fw fa-check"></i> Confirm',
            btnClass: 'btn-green',
            action: function(){
              AJAX({
                url  : app._url + '/delete',
                type : 'DELETE',
                data : $(app._formId).serialize() + '&total=' + showAjaxData.total,
                success: function(deleteAjaxData){
                  $('#reportBody').hide();
                  $('#sideMsgContainer').show();
                  $('#sideMsg').html(deleteAjaxData.sideMsg);
                  $('#batch').val('');
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
    });
  }
};
$(function(){ app.main(); });// Start to initial the main function
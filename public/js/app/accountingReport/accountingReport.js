var app = {
  _url: 'accountingReport',  
  _urlSubmitForm: '',
  _urlReportGroup:'accountingReportGroup',
  _urlReportList:'accountingReportList',
  _urlDragDropGroup:'dragDropGroup',
  _urlDragDropList:'dragDropList',
  _isInitial: true,
  _navClicked: false,
  _columnData: [],
  _report: '#report',
  main: function(){
    this.getHeight();
    this.edit();
    this.createReport();
    this.createList();
    this.createGroup();
    this.destroyReport();
    this.submitForm();
    this.onArrowClick();
    SELECT2(app._report, '#dropdownData', {placeholder:'Select Report'});
    this.showReportFromLink();
  },
//------------------------------------------------------------------------------
  edit: function(){
    app._hideAccordionBtn();
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

    $(app._report).on('change', function(){
      var reportVal  = app._getReportValue();
      var reportText = app._getReportText();
      var $formId= $('#sortableForm');
      $('#reportHeader').text($(this).find(":selected").text());
      $formId.html('');
      app._hideAccordionBtn();
      if(reportVal != ''){
        var reportUrl;
        switch(reportText) {
          case 'Operating Statement':
            app._urlSubmitForm = reportUrl = 'operatingStatementReport';
            break;
          case 'Balance Sheet':
            app._urlSubmitForm = reportUrl = 'balanceSheetReport';
            break;
          default: 
            reportUrl = app._url;
            app._urlSubmitForm = 'accountingReportDefaultTemplate';
        }
        AJAX({
          url   : reportUrl + '/' + reportVal +'/edit',
          success: function(createAjaxData){
            $formId.html(createAjaxData.sortable);
            $('#submitForm').html(createAjaxData.submitForm);
            $formId.addClass(createAjaxData.sortablePerm);
            var sortableOptions = {
              group: 'shared',
              sort: true,
              fallbackOnBody: true,
              invertSwap: true,
              swapThreshold: .65,
              animation: 150,
              easing: 'cubic-bezier(1, 0, 0, 1)',
              filter: '.listRemove, .listEdit',
              onEnd: function(evt) {
                var item = evt.item;
                var from = evt.from;
                var to   = evt.to;
                var toParentId = $(to).attr('id');
                // Report List: revert back to original position if drag to wrong area
                if(toParentId == 'sortableForm' && $(item).hasClass('nested-2')) {
                  from.appendChild(item);
                  app._reorderList(from.childNodes, from);
                  return;
                // Report Group: revert back to original position if drag to wrong area
                }else if(toParentId != 'sortableForm'&& $(item).hasClass('nested-1')) {
                  from.appendChild(item);
                  app._reorderList($('.nested-1'), $formId);
                  return;
                }
                
                if($(item).hasClass('nested-1')) {
                  app.updateGroupOrder('.nested-1');
                }else if($(item).hasClass('nested-2')) {
                  app.updateListOrder(item, to.childNodes, from.childNodes);
                }
              }
            };
            var $sortables = $('.nested-sortable');
            for(var i = 0; i < $sortables.length; i++) {
              new Sortable($sortables[i], sortableOptions);
            }
            INPUTMASK({isIncludeDefaultDaterange: ($('#dateRange').val() === '' ? true : false)});
            app.editList();
            app.editGroup();
            app.destroyList();
            app.destroyGroup();
          }
        });
      }
    });
  },
  updateGroupOrder: function(ele) {
    var idList = $(ele).map(function() {
      return $(this).data('id');
    }).get();
    AJAX({
      url : app._urlDragDropGroup + '/' + idList,
      type: 'PUT',
      data: {'report_group_id': idList},
      success: function(createAjaxData){
  
      }
    });
  },
//------------------------------------------------------------------------------
  updateListOrder: function(item, to, from) {
    var selected = item.dataset.id;
    var groupId = item.parentNode.parentNode.dataset.id;
    var idList = $(from).map(function() {
      return $(this).data('id');
    }).get();
    var idCount = idList.length;
    var idListTo = $(to).map(function() {
      return $(this).data('id');
    }).get();
    idList = idList.concat(idListTo);
    AJAX({
      url : app._urlDragDropList + '/' + idCount,
      type: 'PUT',
      data: {'report_list_id': idList, 'report_group_id': groupId, 'selected': selected},
      success: function(createAjaxData){

      }
    });
  },
//------------------------------------------------------------------------------
  submitForm: function() {
    var formId = $('#reportForm');
    var $reportBody = $('#reportBody');
    var report = app._getReportValue();
    formId.unbind('submit').on('submit',function() {
      var serialized = $(this).serialize();
      AJAXSUBMIT(formId, {
        url:  app._urlSubmitForm,
        data: serialized + '&op=tab&id=' + report,
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
      return false;
    });
  },
//------------------------------------------------------------------------------
  createReport: function(){
    $('#createReport').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-file-text-o"></i> CREATE REPORT</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              var formId = '#accountingReportForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url, 
                  type: 'POST',
                  success: function(storeAjaxData){
                    self.setBoxWidth('500px');
                    self.$content.html(storeAjaxData.msg);
                    $(app._report).select2({
                      data: JSON.parse(storeAjaxData.dropdownData)
                    });
                    $(app._report).val(storeAjaxData.reportNameId);
                    app._refreshSortable();
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  createGroup: function(){
    $('#createGroup').on('click', function() {
      var report = app._getReportValue();
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-group"></i> CREATE GROUP</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._urlReportGroup + '/create',
            data: {'report_name_id': report},
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              var formId = '#reportGroupForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._urlReportGroup, 
                  type: 'POST',
                  success: function(storeAjaxData){
                    self.setBoxWidth('500px');
                    self.$content.html(storeAjaxData.msg);
                    app._refreshSortable();
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  createList: function(){
    $('#createList').on('click', function() {
      var report = app._getReportValue();
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-list"></i> CREATE LIST</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._urlReportList + '/create',
            data: {'report_name_id': report},
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              SELECT2('#acct_type_list', '#accTypeData', {placeholder:'Select Account Type'});
              var formId = '#reportListForm';
              $(formId).unbind('submit').on('submit', function(){
                var acctTypeList = $('#acct_type_list').serializeArray();
                var accTypeValue = app._arrayToCommaString(acctTypeList);
                AJAXSUBMIT(formId, {
                  url:   app._urlReportList, 
                  type: 'POST',
                  data: {report_group_id: $('#report_group_id').val(),acct_type_list: accTypeValue, name_list: $('#name_list').val(), gl_list: $('#gl_list').val()},
                  success: function(storeAjaxData){
                    self.setBoxWidth('500px');
                    self.$content.html(storeAjaxData.msg);
                    app._refreshSortable();
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  editGroup: function() {
    $('.groupEdit').unbind('click').on('click', function() {
      var id = $(this).data('key');
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-group"></i> EDIT REPORT GROUP</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlReportGroup + '/' + id + '/edit',
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              self.$content.html(editUploadAjaxData);
              INPUTMASK();
              var formId = '#reportGroupForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._urlReportGroup + '/' + id,
                  type: 'PUT',
                  success: function(updateAjaxData){
                    app._refreshSortable();
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
    });
  },
//------------------------------------------------------------------------------
  editList: function() {
    $('.listEdit').unbind('click').on('click', function() {
      var id = $(this).data('key');
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-list"></i> EDIT REPORT LIST</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlReportList + '/' + id + '/edit',
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              self.$content.html(editUploadAjaxData);
              SELECT2('#acct_type_list', '#accTypeData', {placeholder:'Select Account Type'});
              var formId = '#reportListForm';
              $(formId).unbind('submit').on('submit', function(){
                var acctTypeList = $('#acct_type_list').serializeArray();
                var accTypeValue = app._arrayToCommaString(acctTypeList);
                AJAXSUBMIT(formId, {
                  url:   app._urlReportList + '/' + id,
                  type: 'PUT',
                  data: {acct_type_list: accTypeValue, name_list: $('#name_list').val(), gl_list: $('#gl_list').val(), usid: $('#usid').val()},
                  success: function(updateAjaxData){
                    app._refreshSortable();
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
    });
  },
//------------------------------------------------------------------------------
  destroyReport: function() {
    $('#destroyReport').unbind('click').on('click', function() {
      var report = app._getReportValue();
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE REPORT</b>',
        content: ' Are you sure you want to remove this report?',
        buttons: {
          confirm: function(){
            AJAX({
              url : app._url + '/' + report,
              type: 'DELETE',
              success: function(data){
                $(app._report + ' option[value='+ report +']').remove();
                app._refreshSortable();
              }
            });
          },
          cancel: function(){
          }
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  destroyGroup: function() {
    $('.groupRemove').unbind('click').on('click', function() {
      var $groupList = $(this).closest('.nested-1');
      var id    = $(this).data('key');
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE REPORT GROUP</b>',
        content: ' Are you sure you want to remove this group and all the nested list?',
        buttons: {
          confirm: function(){
            AJAX({
              url : app._urlReportGroup + '/' + id,
              type: 'DELETE',
              success: function(data){
                $groupList.remove();
              }
            });
          },
          cancel: function(){
          }
        }
      });
    });
  },
//------------------------------------------------------------------------------
  destroyList: function() {
    $('.listRemove').unbind('click').on('click', function() {
      var $list = $(this).closest('.list');
      var id    = $(this).data('key');
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE REPORT LIST</b>',
        content: ' Are you sure you want to remove this list?',
        buttons: {
          confirm: function(){
            AJAX({
              url : app._urlReportList + '/' + id,
              type: 'DELETE',
              success: function(data){
                $list.remove();
              }
            });
          },
          cancel: function(){
          }
        }
      });
    });
  },
//------------------------------------------------------------------------------
  getHeight: function(){
    var _insertHeight = function(){
      var minHeight = 300;
      var rawVal    = $(window).height() - 290;
      var height    =  (rawVal > minHeight) ? rawVal : minHeight;
      $('.gridTable').height(height + 30);
      var gridHeight = $('#gridContainer').height();
      $('#formContainer').height(gridHeight - 20);
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
        $(app._report).val(urlhash.report).trigger('change');
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
  _refreshSortable: function(){
    $(app._report).trigger('change');
  },
  _getReportValue: function(){
    return $(app._report).val();
  },
  _getReportText: function(){
    return $(app._report + ' option:selected').text();
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
  _reorderList: function(list, container) {
    var $reorderedList = $(list).detach().sort(function(a, b) {
      $a = $(a).data('order');
      $b = $(b).data('order');
      return ($a > $b) ? ($a > $b) ? 1 : 0 : -1;
    });
    $(container).append($reorderedList);
  },
//----------------------------------------- ------------------------------------
   _showPropUnits: function(serialize, dataKey){
    $('.tabClass').unbind('click').on('click', function(){
      var gridId = $(this).attr('data-key');
      app._gridTab(gridId, serialize);
    });
    $('.tabClass[data-key="'+ dataKey +'"]').unbind('trigger').trigger('click');
  },
//----------------------------------------- ------------------------------------
  _gridTab: function(gridId, serialize){
    var id = '#' + gridId;
    id = id.replace('*','');
    $(id).bootstrapTable('destroy');
    var column = app._columnData;
    GRID({
      id             : id,
      url            : app._urlSubmitForm + '/' + gridId + '?' + serialize,
      urlReport      : app._urlSubmitForm + '?' + serialize,
      columns        : column[gridId].columns,
      dataField      : 'row',
      fixedColumns   : false,
      fixedNumber    : 1,
      heightButtonMargin : 410,
      sortName       : 'prop',
      pagination     : false,
      filterControl  : false,
      reportList     : column[gridId].reportList,
    });
  },
  _hideAccordionBtn: function() {
    var report = app._getReportValue();
    var $accordionBtn = $('#accordion, #buttonContainer');
    if(report) {
      $accordionBtn.css('display', 'block');
    }else {
      $accordionBtn.css('display', 'none');
    }
  },
  _arrayToCommaString: function(array) {
    var accTypeValue = '';
    $.each(array, function(i, v) {
      accTypeValue += i == 0 ? v['value'] : ',' + v['value'];
    });
    return accTypeValue;
  }
};
$(function(){ app.main(); });// Start to initial the main function

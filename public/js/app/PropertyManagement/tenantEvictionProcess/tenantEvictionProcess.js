var app = {
  _url: '/tenantEvictionProcess', 
  _index: 'tnt_eviction_process_view', 
  _urlEvictionEvent: '/tenantEvictionEvent',
  _urlExport: '/tenantEvictionProcessExport',
  _urlReport: 'tenantEvictionProcessReport',
  _urlUpload: '/uploadTenantEvictionEvent',
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
        window.operateEvents = {
          'click .edit': function (e, value, row, index) { app.edit(row); },
          'click .evictionEvent': function(e,value,row,index) {app._editEvictionEvent(row);},
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          urlReport      : app._urlExport,
          clsReportList  : 'evictionReport',
          columns        : ajaxData.columns,
          fixedColumns   : false,
          reportList     : ajaxData.reportList, 
          fixedNumber    : 1,
          sortName       : 'prop',
          dateColumns    : ['move_in_date', 'move_out_date']
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id = args.id;
          var sData = {};
          sData[name] = args[name];
          AJAX({
            url    : app._url + '/' + id,
            data   : sData,
            type   : 'PUT',
            success: function(updateAjaxData){
              if(updateAjaxData.error !== undefined){
                $el[0].innerHTML = oldValue;
              }
              GRIDREFRESH(app._gridTable);
              Helper.displayGridEditErrorMessage(updateAjaxData);
            }
          });
        });
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
          app.onReportClick();
        });
        $(app._gridTable).on('editable-shown.bs.table', function(field, row, $el) {
          setTimeout(function(){ 
            $('.editable-input input').select();
          });
        });
      }
    });
  },
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-user-times"></i> VIEW TENANT EVICTION PROCESS</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            AUTOCOMPLETE();
            var formId = '#tntEvictionProcessForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url + '/' + id,
                  type: 'PUT',
                  success: function(updateAjaxData){
                    GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
        });
      },
      boxWidth:'700',
      buttons: {
        Cancel: {text: 'Close'}
      }
    }); 
  },
//------------------------------------------------------------------------------
  _editEvictionEvent: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-etsy"></i> VIEW TENANT EVICTION EVENT</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._urlEvictionEvent + '/' + id + '/edit',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData.html);
            INPUTMASK();
            AUTOCOMPLETE();
            app._createEvictionEvent(self);
            var eventIdList = editUploadAjaxData.eventId;
            var eventFormCount = eventIdList.length;
            for(var i = 0; i < eventFormCount; i++) {
              UPLOAD(
                {element: document.getElementById('evictionEventUpload' + eventIdList[i])},
                app._urlUpload, 
                {op:'TenantEviction', type:'tenantEvictionEvent', foreign_id: eventIdList[i]},
                eventIdList[i]
              );
              var formId = '#tntEvictionEventForm' + eventIdList[i];
              $(formId).unbind('submit').on('submit', function(){
                var eventId = $(this).find('#tnt_eviction_event_id').val();
                AJAXSUBMIT('#tntEvictionEventForm' + eventId, {
                  url:  app._urlEvictionEvent + '/' + eventId,
                  type: 'PUT',
                  success: function(updateAjaxData){
                    GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
          }
        });
      },
      boxWidth:'90%',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
//------------------------------------------------------------------------------
  _createEvictionEvent: function(selfEvent) {
    $('#new').on('click', function() {
      selfEvent.close();
      var evictionProcessId = $('#tnt_eviction_process_id').val();
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-etsy"></i> CREATE TENANT EVICTION EVENT</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlEvictionEvent + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              $('#tntEvictionEventForm #tnt_eviction_process_id').val(evictionProcessId);
              INPUTMASK();
              AUTOCOMPLETE();
              UPLOAD(
                {},
                app._urlUpload, 
                {op:'TenantEviction', type:'tenantEvictionEvent'}
              );
              var formId = '#tntEvictionEventForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._urlEvictionEvent, 
                  type: 'POST',
                  success: function(updateAjaxData){
                    GRIDREFRESH(app._gridTable);
                    self.close();
                    var objId = {id: evictionProcessId};
                    app._editEvictionEvent(objId);
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'80%',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
    });
  },
//------------------------------------------------------------------------------
  onReportClick: function() {
    $('.evictionReport').unbind('click').on('click', function(e) {
      e.preventDefault();
      var op = $(this).data('type');
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-gavel"></i> GENERATE TENANT EVICTION REPORT</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._urlReport + '/create',
            data: {op: op},
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              INPUTMASK();
              var formId = '#evictionReportForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._urlReport, 
                  success: function(ajaxData){
                    if(ajaxData.error != undefined){
                      CONFIRM({
                        title: '<b class="text-red"><i class="fa fa-times-circle"></i> ERROR</b>',
                        content:ajaxData.error.msg,
                        buttons: {
                          cancel: {text: 'Close'}
                        }
                      }); 
                    }else{
                      var jc = CONFIRM({ 
                        title  : (ajaxData.title !== undefined) ? ajaxData.title  : '<span class="text-red"><i class="fa fa-download"></i> DOWNLOAD</span>',
                        content: (ajaxData.downloadMsg !== undefined) ? ajaxData.downloadMsg : ajaxData.msg,
                        buttons: {
                          cancel: {text: 'Close'}
                        },
                        onContentReady: function () {
                          $('.downloadLink').unbind('click').on('click', function(){
                            jc.close();
                            self.close();
                          });
                        }
                      }); 
                    }
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'450px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      
    });
  }
};

// Start to initial the main function
$(function(){ app.main(); });
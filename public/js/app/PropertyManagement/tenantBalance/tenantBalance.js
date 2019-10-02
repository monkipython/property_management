var app = {
  _url: '/tenantBalance', 
  _gridTable: '#gridTable',
  main: function(){
    this.grid();
    this.create();
  },
//------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(ajaxData){
        window.operateEvents = {
//          'click .edit': function (e, value, row, index) { app.edit(row); },
//          'click .delete': function(e,value,row,index) {app._deleteTenant(row);},
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          //urlReport      : app._urlReport,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'prop',
          reportList     : ajaxData.reportList,
          dateColumns    : ['move_in_date']
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id          = args.id;
          var sData       = {};
          sData[name]     = args[name];
          sData.prop      = args.prop;
          
          AJAX({
            url: app._url + '/' + id,
            data: sData,
            type: 'PUT',
            success: function(resposneData){
              if(responseData.error !== undefined){
                $el[0].innerHTML = oldValue;
              }
            }
          });
        });
        
        $(app._gridTable).on('click-cell.bs.table',function(e,field,value,row,$element){

        });
        
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
        });
        $(app._gridTable).on('editable-shown.bs.table', function(field, row, $el) {
          setTimeout(function(){ 
            $('.editable-input input').select();
          });
        });
      }
    });
  },
//  create: function() {
//    $('#new').on('click', function() {
//      CONFIRM({
//        title: '<b class="text-red"><i class="fa fa-fw fa-address-card-o"></i> CREATE TENANT</b>',
//        content: function(){
//          var self = this;
//          AJAX({
//            url     : app._url + '/create',
//            dataType: 'HTML',
//            success: function(createPropAjaxData){
//              self.$content.html(createPropAjaxData);
//              INPUTMASK();
//              AUTOCOMPLETE();
//              var formId = '#tenantForm';
//              $(formId).unbind('submit').on('submit', function(){
//                AJAXSUBMIT(formId, {
//                  url:   app._url, 
//                  type: 'POST',
//                  success: function(updateAjaxData){
//                    GRIDREFRESH(app._gridTable);
//                    self.setBoxWidth('500px');
//                    self.$content.html(updateAjaxData.msg);
//                  }
//                });
//                return false;
//              });
//            }
//          });
//        },
//        boxWidth:'60%',
//        buttons: {
//          cancel: {text: 'Close'}
//        },
//      });
//      return false;
//    });
//  },
//  edit: function(row){
//    var id = row.id;
//    CONFIRM({
//      title: '<b class="text-red"><i class="fa fa-fw fa-address-card-o"></i> EDIT TENANT</b>',
//      content: function(){
//        var self = this;
//        AJAX({
//          url    : app._url + '/' + id + '/edit',
//          dataType: 'HTML',
//          success: function(editUploadAjaxData){
//            self.$content.html(editUploadAjaxData);
//            INPUTMASK();
//            AUTOCOMPLETE();
//            var formId = '#tenantForm';
//            $(formId).unbind('submit').on('submit', function(){
//              var oldSpecCode = row.spec_code;
//              var specCodeVal = $('#spec_code').val();
//              if(oldSpecCode == 'E' && specCodeVal != 'E') {
//                CONFIRM({
//                  title: '<b class="text-red"><i class="fa fa-arrow-left"></i> UNDO EVICTION PROCESS CONFIRMATION</b>',
//                  content: ' Are you sure you want to undo the eviction?',
//                  buttons: {
//                    Confirm: {
//                      action: function(){
//                        app._ajaxSubmitUpdate(id, formId);
//                      },
//                      btnClass: 'btn-info',
//                    },
//                    Cancel: {
//                      btnClass: 'btn-danger'
//                    }
//                  }
//                }); 
//              }else if(oldSpecCode != 'E' && specCodeVal == 'E') {
//                // If user changes tenant spec_code to eviction then open tenant_eviction_process create
//                CONFIRM({
//                  title: '<b class="text-red"><i class="fa fa-fw fa-user-times"></i> CREATE TENANT EVICTION PROCESS</b>',
//                  content: function(){
//                    var self = this;
//                    AJAX({
//                      url     : app._urlTenantEvictionProcess + '/create',
//                      dataType: 'HTML',
//                      success: function(createPropAjaxData){
//                        self.$content.html(createPropAjaxData);
//                        INPUTMASK();
//                        var evictionFormId = '#evictionProcessForm';
//                        $(evictionFormId).unbind('submit').on('submit', function(){
//                          var sData        = JSON.parse(JSON.stringify($(formId).serialize()));
//                          var attorneyData = JSON.parse(JSON.stringify($(evictionFormId).serialize()));
//                          sData = sData + '&' + attorneyData;
//                          app._ajaxSubmitUpdate(id, formId, sData, self);
//                          return false;
//                        });
//                      }
//                    });
//                  },
//                  boxWidth:'500px',
//                  buttons: {
//                    cancel: {text: 'Close'}
//                  },
//                });
//              }else {
//                app._ajaxSubmitUpdate(id, formId);
//              }
//              return false;
//            });            
//          }
//        });
//      },
//      onContentReady: function () {
//        AUTOCOMPLETE();
//      },
//      boxWidth:'90%',
//      buttons: {
//        cancel: {text: 'Close'}
//      }
//    });
//  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/


/*******************************************************************************
 ************************ click-cell.bs.table SECTION **************************
 ******************************************************************************/
//------------------------------------------------------------------------------
  _ajaxGridUpdate: function(id, sData, self='') {
    AJAX({
      url    : app._url + '/' + id,
      data   : sData,
      type   : 'PUT',
      success: function(updateAjaxData){
        if(updateAjaxData.error !== undefined){
          ALERT({content: updateAjaxData.error.popupMsg});
        }else{
          GRIDREFRESH(app._gridTable);
        }
        if(self) {
          self.close();
        }
      }
    });
  },
//------------------------------------------------------------------------------
  _ajaxSubmitUpdate: function(id, formId, sData=null, self='') {
    AJAXSUBMIT(formId, {
      url:   app._url + '/' + id,
      type: 'PUT',
      data: sData,
      success: function(updateAjaxData){
        if(updateAjaxData.error !== undefined){
          ALERT({content: updateAjaxData.error.popupMsg});
        }else{
          GRIDREFRESH(app._gridTable);
        }
        if(self) {
          self.close();
        }
      }
    });
  } 
};

// Start to initial the main function
$(function(){ app.main(); });
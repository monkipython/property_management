var app = {
  _url: '/unit',
  _urlReport: '/unitExport',
  _urlDate: '/unitDate',
  _urlFeatures: '/unitFeatures',
  _urlHist: '/unitHist',
  _gridTable: '#gridTable',
  main: function(){
    this.grid();
    this.create();
  },
  grid: function(){
    AJAX({
      url: app._url,
      data: {op:'column'},
      success: function(columns){
        window.operateEvents = {
          'click .edit': function (e, value, row, index) { app.edit(row); },
          'click .editHist':function(e,value,row,index) { app._editUnitHistory(row); },
          'click .editFeatures': function(e,value,row,index) { app._editUnitFeatures(row); },
          'click .editDate': function(e,value,row,index) { app._editUnitDate(row); },
          'click .delete': function(e,value,row,index) {app._deleteUnit(row);},
        };
        
        GRID({
          id: app._gridTable,
          url: app._url,
          urlReport: app._urlReport,
          columns: columns.columns,
          fixedColumns: false,
          fixedNumber    : 1,
          sortName       : 'prop.prop',
          reportList     : columns.reportList,
          dateColumns    : ['move_in_date','move_out_date'],
        });

        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id = args.id;
          var sData = {};
          sData[name]      = args[name];
          sData.prop       = args['prop.prop'];
          
          AJAX({
            url    : app._url + '/' + id,
            data   : sData,
            type   : 'PUT',
            success: function(updateAjaxData){
              if(updateAjaxData.error !== undefined){
                $el[0].innerHTML = oldValue;
              }
            }
          });
        });
        $(app._gridTable).on('load-success.bs.table',function(e,data,value,row,$element){
          $('i.tip').tooltipster({
            theme: 'tooltipster-light',
            delay: 10,
            debug: false,
          });
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
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> EDIT UNIT INFORMATION</b>',
      content: function(){
        var self = this;
        AJAX({
          url: app._url + '/' + id + '/edit',
          success: function(editAjaxData){
            self.$content.html(editAjaxData.html);
            
            var formId = '#unitForm';
            $(formId).unbind('submit').on('submit',function(){
              INPUTMASK();
              AJAXSUBMIT(formId,{
                url: app._url + '/' + id,
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
      onContentReady: function(){
        AUTOCOMPLETE();
        INPUTMASK();
      },
      boxWidth:'80%',
      buttons: {
        cancel: {text: 'Close'}
      }
    });
  },
  create: function(){
    $('#new').unbind('click').on('click',function(){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-home"></i> ADD NEW UNIT</b>',
        content: function(){
          var self = this;
          AJAX({
            url: app._url + '/create',
            success: function(createAjaxData){
              self.$content.html(createAjaxData.html);
              var formId   = '#unitCreateForm';

              $(formId).unbind('submit').on('submit',function(){
                AJAXSUBMIT(formId,{
                  url: app._url,
                  type: 'POST',
                  success: function(storeAjaxData){
                    GRIDREFRESH(app._gridTable);
                    $(formId).trigger('reset');
                    
                    var resultHtml = $('#result').html();
                    if(storeAjaxData.error !== undefined && storeAjaxData.error.mainMsg !== undefined){
                      resultHtml += storeAjaxData.error.mainMsg;
                    }
                    
                    if(storeAjaxData.msg !== undefined){
                      resultHtml += storeAjaxData.msg;
                    }
                    
                    $('#result').html(resultHtml);
                    $('.autocompleteAfter').text('');
                  },
                  error: function(jqXHR,textStatus, errorThrown){
                    dd(textStatus);
                  }
                });
                return false;
              });
            },

          });
        },
        onContentReady: function(){
          AUTOCOMPLETE();
          INPUTMASK();
        },
        boxWidth:'85%',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
    });
  },
  _editUnitHistory: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> EDIT UNIT HISTORY</b>',
      content: function(){
        var self = this;
        AJAX({
          url: app._urlHist + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editAjaxData){
            self.$content.html(editAjaxData);

            var formId = '#unitHistForm';
            $(formId).unbind('submit').on('submit',function(){
              AJAXSUBMIT(formId,{
                url: app._urlHist + '/' + id,
                type: 'PUT',
                success: function(updateAjaxData){
                  dd(updateAjaxData);
                }
              });
              return false;
            });
          }
        });
      },
      onContentReady: function(){
        AUTOCOMPLETE();
        INPUTMASK();
      },
      boxWidth:'80%',
      buttons: {
        cancel: {text: 'Close'}
      }
    });
  },
  _editUnitFeatures: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> EDIT UNIT FEATURES</b>',
      content: function(){
        var self = this;
        AJAX({
          url: app._urlFeatures + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editAjaxData){
            self.$content.html(editAjaxData);

            var formId = '#unitFeatureForm';
            $(formId).unbind('submit').on('submit',function(){
              INPUTMASK();
              AJAXSUBMIT(formId,{
                url: app._urlFeatures + '/' + id,
                type: 'PUT',
                success: function(updateAjaxData){
                  dd(updateAjaxData);
                }
              });
              return false;
            });
          }
        });
      },
      onContentReady: function(){
        AUTOCOMPLETE();
        INPUTMASK();
      },
      boxWidth:'80%',
      buttons: {
        cancel: {text: 'Close'}
      }
    });
  },
  _editUnitDate: function(row){
    var id = row.id;
    var prop = row['prop.prop'];
    var unit = row.unit;
    
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> EDIT UNIT DATES</b>',
      content: function(){
        var self = this;
        AJAX({
          url: app._urlDate + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editAjaxData){
            self.$content.html(editAjaxData);
            
            var formId = '#unitDateForm';
            $(formId).unbind('submit').on('submit',function(){
              INPUTMASK();
              AJAXSUBMIT(formId,{
                url: app._urlDate + '/' + id,
                type: 'PUT',
                success: function(updateAjaxData){
                  dd(updateAjaxData);
                }
              });
              return false;
            });
          }
        });
      },
      onContentReady: function(){
        AUTOCOMPLETE();
        INPUTMASK();
      },
      boxWidth:'70%',
      buttons: {
        cancel: {text: 'Close'}
      }
    });
  },
  _deleteUnit: function(row){
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE UNIT</b>',
      content: ' Are you sure you want to remove this unit.',
      buttons: {
        confirm: function(){
          AJAX({
            url : app._url + '/' + row.id,
            type: 'DELETE',
            success: function(data){
              GRIDREFRESH(app._gridTable);
            }
          });
        },
        cancel: function(){
        }
      }
    });
  }
};

$(function(){app.main();});


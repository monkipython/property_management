var app = {
  _url: '/trust', 
  _urlIcon: '/trustIcon',
  _urlReport: '/trustExport',
  _index:     'trust_view', 
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
          'click .edit': function (e, value, row, index) { app.edit(row); }
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          urlReport      : app._urlReport,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'prop',
          reportList     : ajaxData.reportList,
          dateColumns    : ['start_date']
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
              Helper.displayGridEditErrorMessage(updateAjaxData);
            }
          });
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
  create: function() {
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-home"></i> CREATE TRUST</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.setContent(createPropAjaxData);
              INPUTMASK();
              $('.trustOption').on('change', function() {
                app._onTrustOptionChange(this.value, self);
              });
              var formId = '#trustForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url, 
                  type: 'POST',
                  success: function(updateAjaxData){
                    self.setBoxWidth('500px');
                    self.$content.html(updateAjaxData.msg);
                    GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
          });
        },
        onContentReady: function () {
          AUTOCOMPLETE();
        },
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-home"></i> EDIT TRUST</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.setContent(editUploadAjaxData);
            INPUTMASK();
            var formId = '#trustForm';
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
      onContentReady: function () {
        AUTOCOMPLETE();
      },
      boxWidth:'90%',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
  _onTrustOptionChange: function(value, self) {
    AJAX({
      url    : app._url + '/create',
      data: {op: value},
      dataType: 'HTML',
      success: function(createPropAjaxData){
        self.setContent(createPropAjaxData);
        INPUTMASK();
        $('.trustOption').on('change', function() {
          app._onTrustOptionChange(this.value, self);
        });
        var formId = '#trustForm';
        $(formId).unbind('submit').on('submit', function(){
          AJAXSUBMIT(formId, {
            url:   app._url, 
            type: 'POST',
            success: function(updateAjaxData){
              self.setBoxWidth('500px');
              self.$content.html(updateAjaxData.msg);
              GRIDREFRESH(app._gridTable);
            }
          });
          return false;
        });
      }
    });
  }
};

// Start to initial the main function
$(function(){ app.main(); });
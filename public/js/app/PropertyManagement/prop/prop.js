var app = {
  _url: '/prop', 
  _massiveUrl: '/massiveProp',
  _urlIcon: '/propIcon',
  _urlUpload: '/upload',
  _urlReport: '/propExport',
  _index:     'prop_view', 
  _gridTable: '#gridTable',
  main: function(){
    this.grid();
    this.create();
    this.massiveEdit();
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
          var id      = args.id;
          var sData   = {};
          sData.prop  = args.prop;
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
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-home"></i> EDIT PROPERTY</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            var formId = '#propertyForm';
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
      boxWidth:'90%',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
  create: function() {
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-home"></i> CREATE PROPERTY</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              INPUTMASK();
              $('.propOption').on('change', function() {
                app._onPropOptionChange(this.value, self);
              });
              var formId = '#propertyForm';
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
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
  massiveEdit: function() {
    $('#massiveEdit').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-home"></i> EDIT PROPERTIES</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._massiveUrl + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              INPUTMASK();
              var formId = '#propertyMassiveForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._massiveUrl, 
                  type: 'POST',
                  success: function(updateAjaxData){
                    GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
  _onPropOptionChange: function(value, self) {
    AJAX({
      url    : app._url + '/create',
      data: {op: value},
      dataType: 'HTML',
      success: function(createPropAjaxData){
        self.$content.html(createPropAjaxData);
        INPUTMASK();
        $('.propOption').on('change', function() {
          app._onPropOptionChange(this.value, self);
        });
        var formId = '#propertyForm';
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
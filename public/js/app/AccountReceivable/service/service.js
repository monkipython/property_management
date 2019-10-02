var app = {
  _url: '/service', 
  _urlReport: '/serviceReport',
  _urlIcon: '/serviceIcon',
  _index:     'service_view', 
  _gridTable: '#gridTable',
  main: function(){
    this.grid();
    this.create();
    this.update();
    this.delete();
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
          reportList     : ajaxData.reportList,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'prop'
        });
      }
    });
  },
//------------------------------------------------------------------------------
  update: function(){
    $('#update').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-edit"></i> UPDATE SERVICE</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._url + '/' + 1 + '/edit',
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              self.$content.html(editUploadAjaxData);
              AUTOCOMPLETE();
              INPUTMASK();
              var formId = '#serviceForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url + '/' + 0,
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
        boxWidth:'550px',
        buttons: {
          cancel: {text: 'Close'}
        }
      }); 
    });
  },
//------------------------------------------------------------------------------
  create: function() {
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-plus-square"></i> CREATE SERVICE</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              AUTOCOMPLETE();
              INPUTMASK();
              var formId = '#serviceForm';
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
        boxWidth:'550px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
    });
  },
//------------------------------------------------------------------------------
  delete: function() {
    $('#delete').on('click', function(e) {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-trash"></i> DELETE SERVICE</b>',
        content: function(){
          var self = this;
          AJAX({
            url    : app._url + '/' + 0 + '/edit',
            dataType: 'HTML',
            success: function(deleteFormAjaxData){
              self.$content.html(deleteFormAjaxData);
              AUTOCOMPLETE();
              INPUTMASK();
              var formId = '#serviceForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url + '/' + 0,
                  type: 'DELETE',
                  success: function(deleteAjaxData){
                    self.setBoxWidth('500px');
                    self.$content.html(deleteAjaxData.msg);
                    GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'550px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
    });
  }
};

// Start to initial the main function
$(function(){ app.main(); });
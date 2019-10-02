var app = {
  _url: '/vendors', 
  _urlIcon: '/vendorIcon',
  _index:     'vendor_view', 
  _urlUpload: '/uploadVendors',
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
          'click .edit'   : function (e, value, row, index) { app.edit(row); },
          'click .delete' : function (e, value, row, index) { app._deleteVendor(row); },
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'vendid'
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id = args.id;
          var sData = {};
          sData[name] = args[name];
          sData.prop = args.prop;
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
//------------------------------------------------------------------------------
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-handshake-o"></i> EDIT VENDOR</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            AUTOCOMPLETE();
            INPUTMASK();
            UPLOAD(
              {},
              app._urlUpload, 
              {op:'Vendors', type:'vendors', foreign_id: id}
            );
            var formId = '#vendorForm';
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
      boxWidth:'80%',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
//------------------------------------------------------------------------------
  create: function() {
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-handshake-o"></i> CREATE VENDOR</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              UPLOAD(
                {},
                app._urlUpload, 
                {op:'Vendors', type:'vendors'}
              );
              AUTOCOMPLETE();
              INPUTMASK();
              var formId = '#vendorForm';
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
        boxWidth:'80%',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
    });
  },
//------------------------------------------------------------------------------
  _deleteVendor: function(row){
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-trash"></i>DELETE VENDOR</b>',
      content: ' Are you sure want to remove this vendor.',
      buttons: {
        confirm: {
          text: 'Confirm',
          action: function(){
            AJAX({
              url: app._url + '/' + row.id,
              type: 'DELETE',
              success: function(ajaxData){
                GRIDREFRESH(app._gridTable);
              }
            }); 
          }
        },
        cancel: {
          text: 'Close',
        }
      }
    });
  }
};

// Start to initial the main function
$(function(){ app.main(); });
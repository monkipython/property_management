var app = {
  _url: '/bank', 
  _urlIcon: '/bankIcon',
  _urlUpload: '/upload',
  _urlReport: '/bankExport',
  _index:     'bank_view', 
  _gridTable: '#gridTable',
  _popUpBoxWidth: '1200',
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
          reportList     : ajaxData.reportList
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id = args.id;
          var sData = {};
          sData[name] = args[name];
          sData.bank_id = args.bank_id;
          AJAX({
            url    : app._url + '/' + id,
            data   : sData,
            type   : 'PUT',
            success: function(updateAjaxData){
              if(updateAjaxData.error !== undefined){
                $el[0].innerHTML = oldValue;
              }
              Helper.displayGridEditErrorMessage(updateAjaxData);
              GRIDREFRESH(app._gridTable);
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
  create: function() {
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-university"></i> CREATE BANK</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createBankAjaxData){
              self.$content.html(createBankAjaxData);
              INPUTMASK();
              AUTOCOMPLETE();
              $('.bankOption').on('change', function() {
                app._onBankOptionChange(this.value, self);
              });
              // RESET THE PROP DROPDOWN 
              $('#trust').unbind('down').on('keyup', function(e){
                if (e.keyCode != 13 && e.keyCode != 9 && e.keyCode != 16 ) {
                  $('#prop').html('<option value="0">No Prop</option>');
                }
              });
              var formId = '#bankForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url, 
                  type: 'POST',
                  success: function(updateAjaxData){
                    if(updateAjaxData.html) {
                      app._confirmDuplicateBank(updateAjaxData.html, self);
                    }else {
                      self.setBoxWidth('500px');
                      self.$content.html(updateAjaxData.msg);
                      GRIDREFRESH(app._gridTable);
                    }
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
        boxWidth:app._popUpBoxWidth,
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
//------------------------------------------------------------------------------
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-university"></i> EDIT BANK</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            AUTOCOMPLETE();
            var formId = '#bankForm';
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
      boxWidth:app._popUpBoxWidth,
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
  _confirmDuplicateBank: function(data, self) {
    ALERT({
      title: '<b class="text-red"><i class="fa fa-fw fa-university"></i><i class="fa fa-fw fa-university duplicate"></i> DUPLICATED BANK</b>',
      content: data,
      boxWidth:'60%',
      buttons: {
        formSubmit: {
          text: ' Proceed ',
          btnClass: 'btn-success',
          action: function() {
            var formId = '#bankForm';
            var bData = $('#bankData').text();
            AJAXSUBMIT(formId, {
              url:   app._url, 
              type: 'POST',
              data: {op: 'proceed', bData: bData},
              success: function(updateAjaxData){
                self.setBoxWidth('500px');
                self.$content.html(updateAjaxData.msg);
                GRIDREFRESH(app._gridTable);
              }
            });
          }
        },
        cancel: { 
          text: ' Cancel ',
          btnClass: 'btn-danger'
        }
      }
    }); 
  },
  _onBankOptionChange: function(value, self) {
    AJAX({
      url    : app._url + '/create',
      data: {op: value},
      dataType: 'HTML',
      success: function(createPropAjaxData){
        self.$content.html(createPropAjaxData);
        INPUTMASK();
        AUTOCOMPLETE();
        $('.bankOption').on('change', function() {
          app._onBankOptionChange(this.value, self);
        });
        var formId = '#bankForm';
        $(formId).unbind('submit').on('submit', function(){
          AJAXSUBMIT(formId, {
            url:   app._url, 
            type: 'POST',
            success: function(updateAjaxData){
              if(updateAjaxData.html) {
                app._confirmDuplicateBank(updateAjaxData.html, self);
              }else {
                self.setBoxWidth('500px');
                self.$content.html(updateAjaxData.msg);
                GRIDREFRESH(app._gridTable);
              }
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
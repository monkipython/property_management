var app = {
  _url: '/account', 
  _urlPermission: '/permission',
  _gridTable: '#gridTable',
  _rolePermission: {},
  _pageId: '',
  main: function(){
    this.grid();
  },
//--------------------------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(ajaxData){
        window.operateEvents = {
          'click .permission': function (e, value, row, index) { app._editPermission(row); },
          'click .delete': function (e, value, row, index) { app._deleteAccount(row); }
        };

        GRID({
          id             : app._gridTable,
          url            : app._url,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'email',
          reportList     : app._reportList
        });
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
        });
        // TABLE EDIT UPDATE
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args) {
          var id = args.id;
          var sData = {};
          sData[name] = args[name];
          AJAX({
            url    : app._url + '/' + id,
            data   : sData,
            type   : 'PUT',
            success: function(updateAjaxData){
              Helper.displayGridEditErrorMessage(updateAjaxData);
            }
          });
        });

        $(app._gridTable).on('click-cell.bs.table', function (e, field, value, row, $element) {
          switch (field){
            case 'permission': app._editPermission(row); break;
          }
        });
        $(app._gridTable).on('editable-shown.bs.table', function(field, row, $el) {
          setTimeout(function(){ 
            $('.editable-input input').select();
          });
        });
      }
    });
  },
  _deleteAccount: function(row){
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE ACCOUNT</b>',
      content: ' Are you sure you want to delete this account.',
      buttons: {
        confirm: function(){
          AJAX({
            url : app._url + '/' + row.id,
            type: 'DELETE',
            success: function(){
              GRIDREFRESH(app._gridTable);
            }
          });
        },
        cancel: function(){
        }
      }
    }); 
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _editPermission: function(row){
    var accountId = row.id;
    AJAX({
      url    : app._urlPermission + '/' + accountId + '/edit' ,
      success: function(editAjaxData){
        dd(editAjaxData);
        if(editAjaxData.rolePermission){
          app._rolePermission = editAjaxData.rolePermission;
          dd(app._rolePermission );
        }
        
        CONFIRM({
          title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> Setting Permission</b>',
          content: editAjaxData.html,
          boxWidth:'80%',
          buttons: {
            cancel: { 
              text: 'Close',
              action: function(){
                // Reset the permission
                app._rolePermission = {};
              }
            }
          },
          onContentReady: function () {
            $('button.btn-box-tool').parents().addClass('collapsed-box');
            $('.btn-box-tool').click(function(){
              if($(this).parents().attr('class').match(/collapsed\-box/)){
                $(this).parents().removeClass('collapsed-box');
                $(this).children().removeClass('fa fa-plus').addClass('fa fa-minus');
              } else{
                $(this).parents().addClass('collapsed-box');
                $(this).children().removeClass('fa fa-minus').addClass('fa fa-plus');                 
              }
            });
            $('button.btn-box-tool').first().trigger('click');
            app._showPermission(accountId);
            app._updatePermission(accountId);
            app._indexChangeRole(accountId);
          }
        }); 
      }
    });
  },
  _showPermission: function(accountId){
    var _showAjaxPermission = function (id,accountId){
      var rolePerm = '';
      dd(app._rolePermission);
      for(var i in app._rolePermission){
        rolePerm += i + '-' + (app._rolePermission[i] ? 1 : 0)  + '_';
      }
      app._pageId = id;
      AJAX({
        url: app._urlPermission + '/' + id,
//        data: {rolePermission: JSON.stringify(app._rolePermission), account_id: accountId, accountRole_id: $('#accountRole_id').val()},
        data: {rolePermission: rolePerm.replace(/_$/, '', rolePerm), account_id: accountId, accountRole_id: $('#accountRole_id').val()},
        dataType: 'HTML',
        success: function(showAjaxData){
          $('#bodyPermission').html(showAjaxData);
          CHECKBOXTOGGLE('.checkboxToggle');

          $('.checkboxToggle').unbind('change').on('change', function() {
            // Everytime users click on checkbox it will change the role to customize
            $('#accountRole_id').val(0); 
            var toggleId = $(this).attr('data-id');
            app._rolePermission[toggleId] = $(this).prop('checked');
            $('#rolePermission').val(JSON.stringify(app._rolePermission));
          });
        }
      });
    };
  
    var id = $('.eachPage').first().attr('data-id');
    $('.eachPage').first().addClass('active');
    _showAjaxPermission(id, accountId);
    $('.eachPage').unbind('click').on('click', function(){
      $('.eachPage').removeClass('active');
      $(this).addClass('active');
      id = $(this).attr('data-id');
      _showAjaxPermission(id, accountId);
    });
  },
  _updatePermission: function(accountId){
    var formId = '#applicationForm';
    $(formId).unbind('submit').on('submit', function(){
      AJAXSUBMIT(formId, {
        url:  app._urlPermission + '/' + accountId,
        type: 'PUT',
        success : function(updateAjaxData){
          GRIDREFRESH(app._gridTable);
        }
      });
      return false;
    });
  },
  _indexChangeRole: function(accountId){
    $('#accountRole_id').unbind('change').on('change', function(){
      var accountRoleId = $(this).val();
      AJAX({
        url: app._urlPermission,
        data: {accountRole_id: accountRoleId},
        success: function(indexAjaxData){
          app._rolePermission = accountRoleId ? indexAjaxData.rolePermission : {};
          $('li[data-id=' + app._pageId + ']').addClass('active').trigger('click');
        }
      });
    });
  },
  _operateFormatter: function(value, row, index) {
//    return [
//      '<a class="permission" href="javascript:void(0)" title="Set Up Permission"><i class="fa fa-lock text-aqua pointer"></i></a> | ',
//      '<a class="delete" href="javascript:void(0)" title="Delete"><i class="fa fa-trash-o text-red pointer"></i></a>'
//    ].join('');
  }
};

// Start to initial the main function
$(function(){ app.main(); });
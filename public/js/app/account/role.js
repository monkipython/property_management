var app = {
  _url: '/role', 
  _gridTable: '#gridTable',
  _rolePermission: {},
  _confirm: '',
  main: function(){
    this.grid();
    this.createRole();
  },
//--------------------------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(columns){
        window.operateEvents = {
          'click .edit': function (e, value, row, index) { app._editRole(row); },
          'click .delete': function (e, value, row, index) { app._deleteRole(row); }
        };

        GRID({
          id             : app._gridTable,
          url            : app._url,
          columns        : columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'role',
          reportList     : app._reportList
        });
        $(app._gridTable).on('load-success.bs.table', function (e, data, value, row, $element) {
          TOOLTIPSTER('i.tip');
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args) {
          var id = args.id;
          var sData = {};
          sData[name] = args[name];
          AJAX({
            url    : app._url + '/' + id,
            data   : sData,
            type   : 'PUT',
            success: function(updateAjaxData){}
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
  createRole: function(){
    $('#create').on('click', function(){
      AJAX({
        url: app._url + '/create',
        dataType: 'HTML',
        success: function(editAjaxData){
          app._confirm = CONFIRM({
            title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> CREATE NEW ROLE</b>',
            content: editAjaxData,
            boxWidth:'80%',
            buttons: {
              cancel: { 
                text: 'Close',
                action: function(){
                  app._rolePermission = {}; // Reset the permission
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
              app._showRolePermission();
              app._storeRole();
            }
          }); 
        }
      });
      return false;
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
  _editRole: function(row){
    var roleId = row.id;
    AJAX({
      url    : app._url + '/' + roleId + '/edit' ,
      success: function(editAjaxData){
        if(editAjaxData.rolePermission){
          app._rolePermission = editAjaxData.rolePermission;
        }
        CONFIRM({
          title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> Setting Permission For '+row.role+' Role</b>',
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
            app._showRolePermission();
            app._updateRole(roleId);
          }
        }); 
      }
    });
  },
  _deleteRole: function(row){
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE ROLE</b>',
      content: ' Are you sure you want to delete the role: ' + row.role,
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
  }, 
  _updateRole: function(roleId){
    var formId = '#applicationForm';
    $(formId).unbind('submit').on('submit', function(){
      AJAXSUBMIT(formId, {
        url:  app._url + '/' + roleId,
        type: 'PUT',
        success : function(updateAjaxData){
          GRIDREFRESH(app._gridTable);
        }
      });
      return false;
    });
  },
  _showRolePermission: function(){
    var _showAjaxPermission = function (id){
      AJAX({
        url: app._url + '/' + id,
        data: {rolePermission: JSON.stringify(app._rolePermission)},
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
    _showAjaxPermission(id);
    $('.eachPage').unbind('click').on('click', function(){
      $('.eachPage').removeClass('active');
      $(this).addClass('active');
      id = $(this).attr('data-id');
      _showAjaxPermission(id);
    });
  },
  _storeRole: function(){
    var id = '#applicationForm';
    $(id).unbind('submit').on('submit', function(){
      AJAXSUBMIT(id, {
        url:  app._url,
        type: 'POST',
        success : function(ajaxData){
          // Reset permission for next use
          app._rolePermission = {};
          GRIDREFRESH(app._gridTable);
        }
      });
      return false;
    });
  },
  _operateFormatter: function(value, row, index) {
    return [
//      '<a class="edit" href="javascript:void(0)" title="Edit"><i class="fa fa-edit text-aqua pointer"></i></a> | ',
//      '<a class="delete" href="javascript:void(0)" title="Delete"><i class="fa fa-trash-o text-red pointer"></i></a>'
    ].join('');
  }
};

// Start to initial the main function
$(function(){ app.main(); });

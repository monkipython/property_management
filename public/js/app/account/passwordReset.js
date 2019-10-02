var app = {
  _url: '/passwordReset',
  main: function(){
    this.store();
    this.update();
  },
  store: function(){
    var id = '#applicationForm';
    $(id).unbind('submit').on('submit', function(){
      var storeData = JSON.parse(JSON.stringify($(id).serialize()));
      AJAXSUBMIT(id, {
        url:  app._url,
        type: 'POST',
        data: storeData,
        success : function(storeAjaxData){
          $('#applicationForm').html(storeAjaxData.mainMsg);
        }
      });
      return false;
    }); 
  },
  update: function(){
    var id = '#applicationFormPasswordReset';
    $(id).unbind('submit').on('submit', function(){
      var storeData = JSON.parse(JSON.stringify($(id).serialize()));
      AJAXSUBMIT(id, {
        url:  app._url + '/passwordReset',
        type: 'PUT', // use update route because want to take advantage of _token
        data: storeData,
        success : function(updateAjaxData){
          $('#applicationFormPasswordReset').html(updateAjaxData.mainMsg);
        }
      });
      return false;
    });
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/
};
// Start to initial the main function
$(function(){ app.main(); });

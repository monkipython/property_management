var app = {
  _url: '/profile',
  main: function(){
    this.update();
  },
  update: function(){
    var id = '#applicationForm';
    $(id).unbind('submit').on('submit', function(){
      var storeData = JSON.parse(JSON.stringify($(id).serialize()));
      AJAXSUBMIT(id, {
        url:  app._url + '/profile',
        type: 'PUT', // use update route because want to take advantage of _token
        data: storeData,
        success : function(updateAjaxData){
//          $('#applicationForm').html(updateAjaxData.mainMsg);
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
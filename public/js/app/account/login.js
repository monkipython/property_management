var app = {
  _url: '/login',
  main: function(){
    this.update();
  },
  update: function(){
    var id = '#applicationForm';
    $(id).unbind('submit').on('submit', function(){
      var storeData = JSON.parse(JSON.stringify($(id).serialize()));
      AJAXSUBMIT(id, {
        url:  app._url + '/login',
        type: 'PUT', // use update route because want to take advantage of _token
        data: storeData,
        success : function(ajaxData){
          window.location = ajaxData.link;
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
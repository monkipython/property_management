var app = {
  _url: '/register',
  main: function(){
    this.initLoad();
    this.addAdditionTenant();
    this.store();
    INPUTMASK();
  },
  initLoad: function(){
  },
  addAdditionTenant: function(){
  },
  store: function(){
    var id = '#applicationForm';
    $(id).unbind('submit').on('submit', function(){
      var storeData = JSON.parse(JSON.stringify($(id).serialize()));
      AJAXSUBMIT(id, {
        url:  app._url,
        type: 'POST',
        data: storeData,
        success : function(ajaxData){
          $('.register-box-body').html(ajaxData.msg);
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
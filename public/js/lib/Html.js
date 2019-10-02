var Html = {
  span: function(str, params){
    params = params || {};
    return '<span' + Html.listParams(params) + '>' + str + '</span>';
  },
  div: function(str, params){
    params = params || {};
    return '<div' + Html.listParams(params) + '>' + str + '</div>';
  },
  select: function(optionData, params){
    for(var i in optionData){
      Html.tag();
    }
  },
  tag: function(tag, str, params){
    return '<' + tag + ' ' + Html.listParams(params) + '>' + str + '</' + tag + '>';
  },
  listParams: function(params){
    var str = '';
    for(var fl in params){ 
      str += fl + "='" + params[fl] + "' "; 
    }
    return (str == '') ? '' : ' ' + str;
  },
  sucMsg: function(str){
    var span  = Html.span('',{'class':'icon icon glyphicon glyphicon-ok','aria-hidden':'true'});
    var div   = Html.div(span + ' ' + str,{'class':'alert alert-success text-center','role':'alert'});
    return div;
  },
  errMsg: function(str){
    var span  = Html.span('',{'class':'icon icon glyphicon glyphicon-exclamation-sign','aria-hidden':'true'});
    var div   = Html.div(span + ' ' + str,{'class':'alert alert-danger text-center','role':'alert'});
    return div;  
  },
  warnMsg: function(str){
    var span  = Html.span('',{'class':'icon icon glyphicon glyphicon-exclamation-sign','aria-hidden':'true'});
    var div   = Html.div(span + ' ' + str,{'class':'alert alert-warning text-center','role':'alert'});
    return div;  
  },
  infoMsg: function(str){
    var span  = Html.span('',{'class':'icon icon glyphicon glyphicon-exclamation-sign','aria-hidden':'true'});
    var div   = Html.div(span + ' ' + str,{'class':'alert alert-info text-center','role':'alert'});
    return div;    
  }

/*******************************************************************************
************************ HELPER FUNCTION **************************************
******************************************************************************/
};
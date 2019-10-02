var Helper = {
  replaceArrayKeyId: function(html, index){
    return html.replace(/\[[0-9+]\]/g, '[' + index + ']');
  },
//------------------------------------------------------------------------------
  displayError: function(ajaxData, msgId){
    var msgId = msgId || 'msg';
    var formId = ajaxData.formId ? ajaxData.formId + ' ' : '';
    var getHtmlError = function(fl, val, formId){
      var errorMsg = Html.span(val, {class:'help-block'});
      var cls  = $(formId + '#' + fl).attr('class');
      if(cls && cls.match(/^select2/)){
        
      } else{
        if($(formId + '#' + fl).attr('type') != 'hidden'){
          $(formId + '#' + fl).after(errorMsg);
          $(formId + '#' + fl).parent().addClass('has-error'); 
        }
      }
    };
    if(ajaxData.error !== undefined && ajaxData.error.popupMsg !== undefined){
      ALERT({content: ajaxData.error.popupMsg});
    } else if(ajaxData.error){
      var error = ajaxData.error;
      if(typeof error == 'string'){
        $('#' + msgId).html(ajaxData.error);
      } else{
        for(var fl in error){
          var val = error[fl];
          if(typeof val == 'string'){
            getHtmlError(fl, val, formId);
          } else{
            for(var i in val){
              var field = fl + '\\['+i+'\\]';
              getHtmlError(field, val[i], formId);
            }
          }
        }
      }
      return false;
    }else {
      return true;
    }
  },
//------------------------------------------------------------------------------
  removeDisplayError: function(){
    $('.has-error').removeClass('has-error');
    $('.help-block').remove();
    $('#uploadMsg').html('');
    $('#msg').html('');
//    $('#mainMsg').hide();
  },
//------------------------------------------------------------------------------
  displayGridEditErrorMessage: function(updateAjaxData){
    if(updateAjaxData.error !== undefined){
      for(var key in updateAjaxData.error){
        ALERT({
          title: '<b class="text-red"><i class="fa fa-exclamation"></i> ERROR</b>',
          content: updateAjaxData.error[key],
        }); 
        break;
      }
    }
  },
//------------------------------------------------------------------------------
 getSelectedValues: function(selected,columns){
    var columnVals   = {};
    for(var i = 0; i < columns.length; i++){
      columnVals[columns[i]] = selected.map(function(v){return v[columns[i]];});
    }
    return columnVals;
  },
//------------------------------------------------------------------------------
  setValues: function(selected,name,value){
    return selected.map(function(v){v[name] = value;return v;});
  },
//------------------------------------------------------------------------------
  bindTableDynamicSelect: function(tableId,name,listName){
    $(tableId).on('post-body.bs.table',function(e,data){
      var tData = $(tableId).bootstrapTable('getData');
      $.map(tData, function(row) {
        var bankData = [];
        var bankKeys = Object.keys(row[listName]);
        var bankValues = Object.values(row[listName]);
        for(var i = 0; i < bankKeys.length; i++) {
          bankData.push({value: bankKeys[i], text:'('+ bankKeys[i] + ') ' + bankValues[i]});
        }
        row[listName] = bankData;
        return row;
      });
      $.each(tData,function(i,row){
        var elem = $('tr[data-index=' + i + '] td a[data-name="' + name +  '"]');
        elem.editable({
          type: 'select',
          source: row[listName],
        });
      }); 
    });
  },
//------------------------------------------------------------------------------
  isShow: function(id, isShow){
    if(isShow){
      $(id).show();
    } else{
      $(id).hide();
    }
  },
//------------------------------------------------------------------------------
  extractCurrencyValue: function(str){
    var sign       = str.match(/\$(.*)\((.*)\)|\-\$(.*)/g) != null ? -1 : 1;
    var amount     = parseFloat(str.replace(/\$|\(|\)|\,|\-|\s/g,'').trim());
    amount         = isNaN(amount) ? 0 : parseFloat((sign * amount).toFixed(2));
    return amount;
  },
//------------------------------------------------------------------------------
  formatUsMoney: function(num){
    var isNegative   = num < 0;
    var str          = Math.abs(num).toLocaleString('en-US',{style:'currency',currency:'USD'});
    str              = isNegative ? str.replace(/\$(.*)/g,'\$($1)') : str;
    return str;
  }
};
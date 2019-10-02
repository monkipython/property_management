var app = {
  _url: '/signAgreement',
  _urlProrate: '/prorate',
  _focusIndex: 0,
  _emptyDefault: 'Not Applicable',
  _signatureCount: 0,
  _sigTypes: [{tenant_initial:'tnt-initials'},{tenant_signature:'tnt-sig'}],
  _resetKeys: {'tnt-sig':'resetSignature','tnt-initials':'resetInitials'},
  _imgData : {'tnt-initials': '','tnt-sig':''},
  _scrollClicked: false,
  _numOccupants: 0,
  _screenThresh: 800,
  _confirmWidth: '65%',
  _pageLoadComplete: false,
  _validateError: '<div class="alert alert-danger text-center" role="alert"><span class="icon glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>&nbsp;Error Processing Rental Agreement</div>',
  _errorDiv: '<div class="alert alert-danger text-center" role="alert"><span class="icon glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>&nbsp;Error Processing Signatures</div>',
  _inspectionFull : { 
    'N' : 'NEW',
    'S' : 'SATISFACTORY/CLEAN',
    'O' : 'OTHER',
    'D' : 'DEPOSIT DEDUCTION',
  },
  _defaultData : {
    'tnt-initials' : {
      text: '',
      font: 'Source Sans Pro',
    },
    'tnt-sig' : {
      text: '',
      font: 'Source Sans Pro',
    }
  },
  main: function(){
    //Initialize event handlers
    app._initSignatureCount();
    app._bindClickHandler();
    app._bindEvents();
    app._determineIfAllSigned();
    app._getNumOccupants();
    if($('button.reportDoc').length > 0){
      app._launchInitialWindow();
    }
    app._getProrateAmount();
  },
/*
################################################################################
########################## PAGE INITIALIZATION SECTION    ######################
################################################################################
*/
  _initSignatureCount: function(){
    var signatureCount  = $('button.apply-sig').length;
    app._signatureCount = signatureCount; //Number of input elements in the web page  
    app._numOccupants   = $('.occupant-name').length > 0 ? $('.occupant-name').length : 1;
    
    $('input[type="checkbox"]').on('click',function(){
      $(this).attr('value',$(this).prop('checked') ? 1 : 0);
    });
    
    $('input.number-touchspin').TouchSpin();
    //Initialize datepicker
    INPUTMASK();
  },
/*
################################################################################
########################## EVENT HANDLERS SECTION    ###########################
################################################################################
*/
  _bindClickHandler: function(){
    
    var _hideMainMsg  = function(time){
                    setTimeout(function(){ 
                      var defaultMainMsg = '<div class="box-body text-center">Loading ....</div><div class="overlay"><i class="fa fa-refresh fa-spin"></i></div>';
                      $('#mainMsg').fadeOut(1000).hide().html(defaultMainMsg);
                    }, time);
                  };
    $('.sign-prompt').unbind('click').on('click',function(e){
      e.preventDefault();

      //Generate signature modal
      CONFIRM({
        title: 'Use Signature Pad',
        content: '<div id="jSig" class="signature-pane"></div>',
        boxWidth: '50%',
        onContentReady: function(){
          //Render signature that allows the 'undo last stroke' option
          $('#jSig').jSignature({'UndoButton':true});
          var self = this;
          //Set jSignature width if large screen
          if(screen.width > app._screenThresh){
            self.setBoxWidth('60%');
          }
        },
        buttons: {
          attach: {
            text: 'Attach Signature',
            action: function(){
              //Get image's data URI from jSignature's canvas
              var imageData = $('#jSig').jSignature('getData');
              //Get nearby hidden image from the DOM near the textbox
              var img       = $('img.signature-img[id*="_all_sigImg"]');
              //Make image visible to the user
              img.css('display','inline-block');
              
              //Have image's data correspond to the jSignature created
              img.attr('src',imageData);
              
              //Have hidden input store the image's data URI 
              img.siblings('.signature-img-data').val($('#jSig').jSignature('getData'));
              
              //Hide the font picker dropdown menu and display the keyboard button
              img.siblings('.signature, .fontPicker').css('display','none');
              img.siblings('.keyboard-prompt').css('display','inline-block');
            }
          },
          clear: {
            text: 'Clear',
            action: function(){
              //Clear jSignature canvas
              $('#jSig').jSignature('reset');
              return false;
            }
          },
          cancel: {text: 'Close'},
        }
      });
    });
    
    $('.keyboard-prompt').unbind('click').on('click',function(e){
      e.preventDefault();
      
      //Hide the signature image
      $(this).siblings('.signature-img').css('display','none');
      //Display font selector dropdown menu
      $(this).siblings('.signature, .fontPicker').css('display','inline-block');
      
      //Empty the hidden input of the past image's data URI
      $(this).siblings('.signature-img-data').val('');
      
      //Hide button
      $(this).css('display','none');
    });
    
    $('.next-sig').unbind('click').on('click',function(e){
      app._focusIndex    = app._focusIndex + 1 < app._signatureCount ? app._focusIndex + 1 : app._signatureCount - 1;
      app._focusIndex    = app._scrollClicked ? app._focusIndex : 0;
      app._scrollClicked = true;
      var selector       = $('a.signature:eq(' + app._focusIndex + ')');
      var display        = $('button.apply-sig:eq(' + app._focusIndex + ')').css('display');
      var notClicked     = typeof(display) !== 'undefined' && display  !== 'none';
      var btnCount       = $('button.apply-sig:visible').length;
      if(btnCount > 0){
          while(!(notClicked) && (app._focusIndex + 1 < app._signatureCount)){
            app._focusIndex = app._focusIndex + 1 < app._signatureCount ? app._focusIndex + 1 : app._signatureCount - 1;
            selector        = $('a.signature:eq(' + app._focusIndex + ')');
            display         = $('button.apply-sig:eq(' + app._focusIndex + ')').css('display');
            notClicked      = typeof(display) !== 'undefined' && display  !== 'none';
          }

          if(typeof(selector) !== 'undefined' && typeof(selector.offset) !== 'undefined' && typeof(selector.offset()) !== 'undefined'){
            $('html, body').animate({
              scrollTop: selector.offset().top - (parseInt(screen.height / 2))
            },0,'linear');
          }
      } else {
        $('#mainMsg,#msg').hide();
        $('#mainMsg').show().html(Html.infoMsg('No Sections Left Needing Signatures'));
        _hideMainMsg(1000);
      }
    });
    
    $('.prev-sig').unbind('click').on('click',function(e){
      e.preventDefault();
      app._focusIndex    = (app._focusIndex - 1 >= 0) ? app._focusIndex - 1 : 0;
      app._focusIndex    = app._scrollClicked ? app._focusIndex : 0;
      app._scrollClicked = true;
      var selector       = $('a.signature:eq(' + app._focusIndex + ')');
      var notClicked     = $('button.apply-sig:eq(' + app._focusIndex + ')').css('display') !== 'none';
        
      var btnCount       = $('button.apply-sig:visible').length;
      if(btnCount > 0){
          while(!(notClicked) && (app._focusIndex - 1 >= 0)){
            app._focusIndex = (app._focusIndex - 1 >= 0) ? app._focusIndex - 1 : 0;
            selector        = $('a.signature:eq(' + app._focusIndex + ')');
            notClicked      = $('button.apply-sig:eq(' + app._focusIndex + ')').css('display') !== 'none';
          }

          if(typeof(selector) !== 'undefined' && typeof(selector.offset) !== 'undefined' && typeof(selector.offset()) !== 'undefined'){
            $('html, body').animate({
              scrollTop: selector.offset().top - (parseInt(screen.height / 2))
            },0,'linear');
          } 
      } else {
            $('#mainMsg,#msg').hide();
            $('#mainMsg').show().html(Html.infoMsg('No Sections Left Needing Signatures'));
            _hideMainMsg(1000);
          }
      
      
    });
    
    $('button[class*="tnt-sig-"]').unbind('click').on('click',function(e){
      e.preventDefault();
      
      var className = $(this).attr('class').replace(/(apply\-sig\s+)/,'');
      app._setConfirmSignatureModal($(this),className,'','');
    });

    $('.fontPicker').on('change',function(e){
      //Change input's font to the font selected by the dropdown menu
      $(this).css('font-family',e.target.value);
      $('.jconfirm input.signature').css('font-family',e.target.value);
    });
  
    $('#resetInitials, #resetSignature').unbind('click').on('click',function(e){
      e.preventDefault();
      
      var validIds = ['resetInitials','resetSignature'];
      var id       = $(this).attr('id');
      
      var index    = validIds.indexOf(id);
      if(index !== -1){
        app._launchInitialWindow();
      }
    });
    
    $('#signature_button_initials, #signature_button_signature').unbind('click').on('click',function(e){
      e.preventDefault();
      
      var signatureOpts = {'UndoButton':true,'width':'100%','height':'100%'};

      
      var containerElem   = $('#' + $(this).attr('canvas-target'));
      
      if(containerElem.find('canvas').length === 0){
        containerElem.jSignature(signatureOpts);
        containerElem.children('button.clear-sig').css('display','inline-block');
        containerElem.append('<button class="clear-sig">Clear</button>');
      }
      $('.clear-sig').unbind('click').on('click',function(e){
        e.preventDefault();
        
        $(this).parents('div.signature-container').jSignature('reset');
      });
    });
    
    $('button[id*=signature_button_signature_]').unbind('click').on('click',function(e){
        e.preventDefault();
        var signatureOpts = {'UndoButton':true,'width':1000,'height':250};
        
         if($(this).siblings('.signature-container').find('canvas').length === 0){
          $(this).siblings('.signature-container').jSignature(signatureOpts);
          $(this).siblings('.signature-container').children('button.clear-sig').css('display','inline-block');
          $(this).siblings('.signature-container').append('<button class="clear-sig">Clear</button>');
        }
        $('.clear-sig').unbind('click').on('click',function(e){
          e.preventDefault();

          $(this).parents('div.signature-container').jSignature('reset');
        });
        
    });
    
    $('select.font-toggle').on('change',function(e){
      var parent = $(this).parents('div.signature-window');
      parent.find('input.signature').css('font-family',e.target.value);
    });
    
    $('button.tnt-sig, button.tnt-initials').unbind('click').on('click',function(e){
      e.preventDefault();
      
      var className = $(this).attr('class').replace(/(apply\-sig\s+)/,'');;
      var imgData   = app._imgData[className];
      var textData  = app._defaultData[className];
      if(textData.text.length != 0 || imgData.length != 0){
        app._setConfirmSignatureModal($(this),className,'','');
      } else {
        var targetId = app._resetKeys[className];
        $('#' + targetId).click();
      }
    });
    
    $('#inspectionNotesSection input[type="radio"]').unbind('change').on('change',function(){
       var value          = this.value;
       var prefix         = $(this).attr('name').replace(/movein/g,'move_in_notes').replace(/moveout/g,'move_out_notes');
       var notesElem      = $('input[type="text"][name="' + prefix + '"]');
       var translation    = app._inspectionFull[value];
       notesElem.val(translation);
    });
    
    $('input[name*="provide_parking_"], input[name*="provide_pets_"], input[name*="provide_storage_"]').unbind('change').on('change',function(){
        var targetName     = $(this).attr('name').replace(/provide_/g,'').replace(/_(\[\d+\])$/g,'_cost_');
        var val            = this.value;
        var hide           = val.match(/not_/gi) !== null;
        hide               = $(this).attr('name').match(/_pets_/gi) !== null ? !(hide) : hide;
        var spanStyle      = hide ? 'inline-block' : 'none';
        var pStyle         = hide ? 'block' : 'none';
        $('span.hide-on-load[id*="' + targetName + '"]').css('display',spanStyle);
        $('p.hide-on-load[id*="' + targetName + '"]').css('display',pStyle);
        $('input[type="text"].decimal[id*="' + targetName + '"]').css('display',spanStyle);
        
        var costVal        = $('input[type="text"].decimal[id*="' + targetName + '"]').val().replace(/\$|\,/g,'');
        costVal            = isNaN(parseFloat(costVal)) ? 0 : parseFloat(costVal);
        var increment      = hide ? 1 : -1;
        var totalVal       = $('input[type="text"][id*="total_cost_"]').val().replace(/\$|\,/g,'');
        totalVal           = isNaN(parseFloat(totalVal)) ? 0 : parseFloat(totalVal);
        totalVal           = (increment * costVal) + totalVal;
        $('input[type="text"][id*="total_cost_"]').val(totalVal);
        INPUTMASK();
    });
    
    
    $('input[type="checkbox"].service-pay-checkbox-group, input[type="checkbox"].reimburse-pay-checkbox-group').unbind('change').on('change',function(){
       var toggleTargets   = {
        'service-pay-checkbox-group'   : 'reimburse-pay-checkbox-group',
        'reimburse-pay-checkbox-group' : 'service-pay-checkbox-group',
       };
       
       var togglePatterns  = {
         'service-pay-checkbox-group'  : {
           current: 'service',
           target : 'reimburse',
         },
         'reimburse-pay-checkbox-group': {
           current: 'reimburse',
           target : 'service',
         },
       };
       
       var id = $(this).attr('id');
       if($(this).attr('value') == 1){
           var matchClasses    = $(this).attr('class').match(new RegExp(Object.keys(toggleTargets).join('|')),'gi');
           var toggleClass     = matchClasses != null ? toggleTargets[matchClasses[0]] : '';
           var togglePattern   = matchClasses != null ? togglePatterns[matchClasses[0]] : {};
           var targetId        = Object.keys(togglePattern).length > 0 ? id.replace(new RegExp(togglePattern.current,'g'),togglePattern.target).replace(/(\[|\])/g,'\\$1') : '';
           if(toggleClass.length > 0){
               $('#' + targetId).attr('value',0);
               $('#' + targetId).prop('checked',false);
               //$('input[type="text"].' + toggleClass).val(app._emptyDefault);
           }
       }
    });
    
    $('input.decimal[name*="storage_cost_"], input.decimal[name*="pets_cost_"], input.decimal[name*="parking_cost_"]').unbind('click').on('click',function(e){
        e.preventDefault();
        var currentVal    = $(this).val().replace(/\$|\,/g,'');
        currentVal        = isNaN(parseFloat(currentVal)) ? 0 : parseFloat(currentVal);
        $(this).attr('stored-value',currentVal);
    });
    
    $('input.decimal[name*="storage_cost_"], input.decimal[name*="pets_cost_"], input.decimal[name*="parking_cost_"]').unbind('keyup').on('keyup',function(e){
        e.preventDefault();
        var currentId     = $(this).attr('id').replace(/(\[|\])/g,'\\$1');
        var namePrefix    = $(this).attr('name').replace(/\[\d+\]$/g,'');
        var pastVal       = $(this).attr('stored-value') === undefined ? 0 : parseFloat($(this).attr('stored-value'));
        var currentVal    = $(this).val().replace(/\$|\,/g,'');
        currentVal        = isNaN(parseFloat(currentVal)) ? 0 : parseFloat(currentVal);
        var totalVal      = $('input.decimal[name="total_cost_[0]"]').val().replace(/\$|\,/g,'');
        totalVal          = isNaN(parseFloat(totalVal)) ? 0 : parseFloat(totalVal);
        $(this).attr('stored-value',currentVal);
        $('input.decimal[name="total_cost_[0]"]').val(totalVal + currentVal - pastVal);
        $('input.decimal[name="total_cost_[0]"]').css('display','inline-block');
        $('input.decimal[name*="' + namePrefix + '"]:not(#' + currentId + ')').val(currentVal);
    });

    
    $('#addOccupant').unbind('click').bind('click',function(e){
      e.preventDefault();
      var numForms  = $('span[id*="occupant_row"]').length;
      var lastRow   = $('span[id*="occupant_row"]:eq(' + (numForms - 1) + ')').get(0).outerHTML;
      var newRow    = lastRow.replace(/\"occupant_name\[\d+\]\"/g,'"occupant_name[' + numForms + ']"').replace(/\"occupant_row\[\d+\]\"/g,'"occupant_row[' + numForms + ']"');
      $('#occupantContainer').append(newRow);
      $('input[id*="occupant_name"]:eq(' + numForms + ')').val('');
      app._getNumOccupants();
      app._bindClickHandler();
    });

    $('#removeOccupant').unbind('click').bind('click',function(e){
      e.preventDefault();
      var numForms  = $('span[id*="occupant_row"]').length;
      if(numForms > 1){
        $('span[id*="occupant_row"]:eq(' + (numForms - 1) + ')').remove();
        app._getNumOccupants();
        app._bindClickHandler();
      }
    });  
    
    $('input[id*="occupant_name"]').unbind('keyup').on('keyup',function(){
      var val = $(this).val().trim();
      app._getNumOccupants();
    });
    
    $('#move_in_date').unbind('keyup').on('keyup',function(){
      app._getProrateAmount();
    });
    $('#move_in_date').unbind('change').on('change',function(){
      app._getProrateAmount();
    });
  },
/*
################################################################################
########################## FORM SUBMISSION SECTION    ##########################
################################################################################
*/
  _bindEvents: function(){
    $('.reportDoc').on('click',function(){
      var selfBtn = $(this);
      $('#mainMsg,#msg').show();
      var op = $(this).attr('data-type');
      var formId = '#agreementForm';
      
      var _hideMainMsg = function(time){
                    setTimeout(function(){ 
                      var defaultMainMsg = '<div class="box-body text-center">Loading ....</div><div class="overlay"><i class="fa fa-refresh fa-spin"></i></div>';
                      $('#mainMsg').fadeOut(1000).hide().html(defaultMainMsg);
                    }, time);
                  };
      //Gather all input's from agreement form
      var dataString = 'op=' + op + '&occupants=' + app._numOccupants + app._getFontsSerialized() + '&' +  $(formId).serialize();
      AJAX({
        url: app._url,
        data: dataString,
        //Use POST because of the size of jSignature's data URI's
        type: 'POST',
        success: function(ajaxData){
          $('#mainMsg,#msg').hide();
          Helper.removeDisplayError();
          if(ajaxData.error !== undefined){
            Helper.displayError(ajaxData);
            $('#mainMsg').show().html(app._validateError);
            $('html, body').animate({
              scrollTop: $('span.has-error:eq(0)').offset().top - parseInt(screen.height / 2)
            },0,'linear');
            
          }else{
            $('#mainMsg').show().html(ajaxData.mainMsg);
            _hideMainMsg(1000);
            $('.reportDoc').html('Agreement Complete');
            $('.reportDoc').removeClass('reportDoc');
            var jc = CONFIRM({
              title: '<span class="text-red"><i class="fa fa-download"></i> VIEW AND DOWNLOAD AGREEMENT</span>',
              content: ajaxData.contentMsg,
              buttons: {
                email: {
                  text: 'Email Rental Agreement',
                  action: function(){
                    CONFIRM({
                      title: '<b><i class="fa fa-fw fa-email"></i> EMAIL AGREEMENT COPY</b>',
                      boxWidth: '60%',
                      content: function(){
                        var self = this;
                        AJAX({
                          url : app._url,
                          //data: 'op=sendEmail&application_id=' + $('#application_id').val(),
                          data : {
                            op: 'sendEmail',
                            application_id: $('#application_id').val(),
                            
                          },
                          success: function(ajaxDataResp){
                            self.$content.html(ajaxDataResp.html);
                            INPUTMASK(); 
                          }
                        });
                      },
                      onContentReady: function(){
                        $('#addEmail').unbind('click').bind('click',function(e){
                          e.preventDefault();
                          var numForms  = $('#emailForm div.form-group').length;
                          var lastRow   = $('#emailForm div.form-group:eq(' + (numForms - 1) + ')').get(0).outerHTML;
                          var newRow    = lastRow.replace(/\"email\[\d+\]\"/g,'"email[' + numForms + ']"').replace(/\>Email \(\d+\)\: \<\/label\>/g,'<label>Email (' + (numForms+1) + '): </label>');
                          $('#emailForm').append(newRow);
                          $('#emailForm input[id*="email"]:eq(' + numForms + ')').val('');
                        });

                        $('#removeEmail').unbind('click').bind('click',function(e){
                          e.preventDefault();
                          var numForms  = $('#emailForm input').length;
                          if(numForms > 1){
                            $('#emailForm div.form-group:eq(' + (numForms - 1) + ')').remove();
                          }
                        });                        
                      },
                      buttons : {
                        cancel: {text:'Close'},
                        send: {
                          text: 'Send Email',
                          action: function(){
                            AJAX({
                              url: app._url,
                              data: 'op=submitEmail&application_id=' + $('#application_id').val() + '&link=' + ajaxData.link + '&' + $('#emailForm').serialize(),
                              success: function(responseData){
                                console.log(responseData);
                              }
                            });
                          }
                        },
                      }
                    });
                    return false;
                  }
                },
                print:  {
                  text: 'View and Print Agreement',
                  action: function(){
                    //Print agreement form
                    var link = ajaxData.link;
                    var win  = window.open(link,'_blank');
                    if(win){
                      win.focus();
                    } else {
                      alert('Allow this website to open new tabs');
                    }
                    return false;
                  }
                },
              },
              onContentReady: function () {
                $('.downloadLink').unbind('click').on('click', function(){
                  jc.close();
                });
              }
            }); 
          }
        }
      });
    });
    $('#generateAgreementPreview').on('click',function(){
      $('#mainMsg,#msg').show();
      var op = 'preview';
      var formId = '#agreementForm';
      
      var _hideMainMsg = function(time){
                    setTimeout(function(){ 
                      var defaultMainMsg = '<div class="box-body text-center">Loading ....</div><div class="overlay"><i class="fa fa-refresh fa-spin"></i></div>';
                      $('#mainMsg').fadeOut(1000).hide().html(defaultMainMsg);
                    }, time);
                  };
      //Gather all input's from agreement form
      var dataString = 'op=' + op + '&occupants=' + app._numOccupants + app._getFontsSerialized()  + '&' +  $(formId).serialize();
      AJAX({
        url: app._url,
        data: dataString,
        //Use POST because of the size of jSignature's data URI's
        type: 'POST',
        success: function(ajaxData){
          $('#mainMsg,#msg').hide();
          Helper.removeDisplayError();
          if(ajaxData.error !== undefined){
            Helper.displayError(ajaxData);
            $('#mainMsg').show().html(app._validateError);
            $('html, body').animate({
              scrollTop: $('span.has-error:eq(0)').offset().top - parseInt(screen.height / 2)
            },0,'linear');
            
          }else{
            $('#mainMsg').show().html(ajaxData.mainMsg);
            _hideMainMsg(1000);
            
            var jc = CONFIRM({
              title: '<span class="text-red"><i class="fa fa-download"></i> PREVIEW AGREEMENT</span>',
              content: 'Your Agreement has been Successfully Saved',
              buttons: {
                cancel: {text: 'Close'},
                print:  {
                  text: 'View and Print Agreement Preview',
                  action: function(){
                    //Print agreement form
                    var link = ajaxData.link;
                    var win  = window.open(link,'_blank');
                    if(win){
                      win.focus();
                    } else {
                      alert('Allow this website to open new tabs');
                    }
                  }
                },
              },
              onContentReady: function () {
                $('.downloadLink').unbind('click').on('click', function(){
                  jc.close();
                });
              }
            }); 
          }
        }
      });
    });
  },
/*
################################################################################
########################## SIGNATURE FUNCTIONS    ##############################
################################################################################
*/
  _launchAdditionalRequest: function(appId,additionalForms,index){
    AJAX({
      url: app._url,
      data: 'op=createAdditionalSigForm&application_id=' + appId + '&formsLeft=' + (additionalForms - 1) + '&index=' + index,
      success: function(respData){
        app._defaultData['tnt-sig-' + index] = {text:'',font:'Source Sans Pro'};
        app._imgData['tnt-sig-' + index] = '';
        var sigType = {};
        sigType['tenant_signature_'  + index] = 'tnt-sig-' + index;
        app._sigTypes.push(sigType);
        var confirm = CONFIRM({
          title: '<span class="text-center">Enter Additional Signature</span>',
          content: respData.html,
          onContentReady: function(){ 
            app._bindClickHandler();
          },
          boxWidth: '90%',
          buttons: {
            attach: {
              text: 'Apply to Agreement',
              action: function(){
                var tabFormId    = '#tab_form';
                var key         = 'tnt-sig-' + index;
                if($(tabFormId + ' div.tab-content div.tab-pane.active form').attr('id') === 'esignatureForm' && $('.jconfirm div.signature-container canvas').length > 0){
                  var elem       = $('.jconfirm div.signature-container:eq(0)');
                  var padId      = '#' + elem.attr('id');
                  var nativeData = $(padId).jSignature('getData','native');
                  var hiddenInput = $('input[name="' + elem.attr('data-target') + '"]');

                  if(typeof(nativeData) !== 'undefined' && nativeData.length > 0){
                    var raw = $(padId).jSignature('getData');
                    hiddenInput.val(raw);
                    
                    app._imgData[key] = $(padId).jSignature('getData');
                  }
                }
                
                app._defaultData[key]['text'] = $('input[name="initial_tenant_signature_' + index + '"]').val();
                app._defaultData[key]['font'] = $('#font_toggle').val();
                
                var formId = $(tabFormId + ' div.tab-content div.tab-pane.active form').attr('id');
                var data   = $('#' + formId).serialize();
                AJAX({
                  url: app._url + '/' + appId,
                  data: data + '&formIndex='+index + '&formId=' + formId,
                  type: 'PUT',
                  success: function(responseData){
                    
                    if(!responseData.error && !responseData.noRule){
                      confirm.close();
                      if(respData.additionalForms >= 1){
                        app._launchAdditionalRequest(appId,additionalForms - 1,++index);
                      }
                    } else if(responseData.error !== undefined){
                      $('#mainMsg').show().html(app._formErrorDiv(responseData.error));
                    } else {
                      $('#mainMsg').show().html(app._errorDiv);
                    }
                  },
                });
                return false;
              }
            }
          },
          onDestroy: function(){

          }
        });
      }
    });
  },
  _launchInitialWindow: function(){
    var appId     = $('#application_id').val();
    AJAX({
        url: app._url,
        data: 'op=doubleTemplate&application_id=' + appId,
        success: function(ajaxData){
          var jc = CONFIRM({
            title: 'Enter Your Signature / Initials',
            content: ajaxData.html,
            onContentReady: function(){
              app._bindClickHandler();
            },
            boxWidth: '80%',
            buttons: {
              attach : {
                text : 'Apply to Agreement',
                action: function (){
                  var tabFormId  = '#tab_form';
                  var containers = $('.signature-container').length;
                  for(var i = 0; i < containers; i++){
                    var elem        = $('.signature-container:eq(' + i + ')');
                    var hiddenInput = $('input[name="' + elem.attr('data-target') + '"]');
                    
                    if($(tabFormId + ' div.tab-content div.tab-pane.active form').attr('id') === 'esignatureForm' && elem.children('canvas').length > 0){
                      var padId       = '#' + elem.attr('id');
                      var nativeData  = $(padId).jSignature('getData','native');
                      
                      if(typeof(nativeData) !== 'undefined' && nativeData.length > 0){
                        hiddenInput.val($(padId).jSignature('getData'));
                      }
                    }
                  }
                  
                  var formId = $(tabFormId + ' div.tab-content div.tab-pane.active form').attr('id');
                  var data   = $('#' + formId).serialize() + '&formId=' + formId;
                  AJAX({
                    url: app._url + '/' + appId,
                    data: data,
                    type: 'PUT',
                    success: function(responseData){
                      app._fetchImageFromDiv('tnt-initials','#signature_pad_initials','initial_tenant_initials');
                      app._fetchImageFromDiv('tnt-sig','#signature_pad_signature','initial_tenant_signature');
                      
                      if(!responseData.error && !responseData.noRule){
                        jc.close();
                        if(ajaxData.additionalForms >= 1){
                          app._launchAdditionalRequest(appId,ajaxData.additionalForms,1);
                        }
                      } else if(responseData.error !== undefined){
                        $('#mainMsg').show().html(app._formErrorDiv(responseData.error));
                      } else {
                        $('#mainMsg').show().html(app._errorDiv);
                      }
                    },
                  });
                  
                  return false;
                }
              },
//              cancel : {
//                text: 'Close',
//              }
            },
          });
        }
      });
  },
/*
################################################################################
########################## SIGNATURE PROCESSING FUNCTIONS    ###################
################################################################################
*/
  _setConfirmSignatureModal: function(elem,key,title,confirmText){
      var self    = elem;
      var index   = key.replace(/tnt-sig-/g,'');
      var child   = isNaN(index) ? '0' : index;
      
      var imageData = app._imgData[key];
      if(imageData.length > 0){
        //Get nearby hidden image from the DOM near the textbox
        var img       = $(self).siblings('.signature-img:eq(' + child + ')');

        //Make image visible to the user
        img.css('display','inline-block');

        //Have image's data correspond to the jSignature created
        img.attr('src',imageData);

        //Have hidden input store the image's data URI 
        img.siblings('.signature-img-data:eq(' + child + ')').val(imageData);

        //Hide the font picker dropdown menu and display the keyboard button
        img.siblings('.signature:eq(' + child + ')').css('display','none');
      } else {
        var newFont = app._defaultData[key].font;
        $(self).siblings('.signature-img:eq(' + child + ')').css('display','none');
        $(self).siblings('a.signature:eq(' + child + ')').css('font-family',newFont);
        $(self).siblings('input.signature-txt-data:eq(' + child + ')').val(app._defaultData[key].text);
        $(self).siblings('a.signature:eq(' + child + ')').text(app._defaultData[key].text);
      }
      
      $(elem).css('display','none');
      var buttonText = 'Apply Signature&nbsp;&nbsp;&nbsp;<i class="fa fa-mouse-pointer"></i>&nbsp;&nbsp;';
      $('button.' + key).html(buttonText);
      $('button.tnt-initials').html('Apply Initials&nbsp;&nbsp;&nbsp;<i class="fa fa-mouse-pointer"></i>&nbsp;&nbsp;');
      $(elem).html(buttonText);
      app._determineIfAllSigned();
  },
  _fetchImageFromDiv: function(key,canvasParent,inputName){
    var imageData  =  textData = '';
    var font       =  'Source Sans Pro';
    var nativeData = $(canvasParent).jSignature('getData','native');
    if(typeof(nativeData) !== 'undefined' && nativeData.length > 0){
      imageData = $(canvasParent).jSignature('getData');
    } else {
      textData  = $('input[name="' + inputName + '"]').val();
      font      = $('input[name="' + inputName + '"]').css('font-family');
    }
    
    app._imgData[key]     = imageData;
    app._defaultData[key] = {text: textData, font: font};
    $('#' + key + '_font').val(font);
  },
/*
################################################################################
########################## HELPER FUNCTIONS    #################################
################################################################################
*/
  _getNumOccupants: function(){
    var numTenants    = $('input[id*="tenant_lease_full_name"]').length;
    var numOccupants  = $('input[id*="occupant_name"]').length;
    var filterVal     = app._emptyDefault.toLowerCase();
    for(var i = 0; i < numOccupants; i++){
      var val       = $('input[id*="occupant_name"]:eq(' + i + ')').val();
      numTenants   += val.trim().length > 0 && val.replace(/\s+/g,' ').trim().toLowerCase() != filterVal ? 1 : 0;
    }
    $('input[id*="num_occupants_"]').val(numTenants);
  },
  _formErrorDiv: function(error){
    var html = '<div class="alert alert-danger text-center" role="alert"><span class="icon glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>&nbsp;';
    html    += 'You are missing ';
    
    var needsAnd = false;
    var errKeys  = Object.keys(error);
    if(errKeys.indexOf('initial_tenant_initials') !== -1 || errKeys.indexOf('initial_tenant_initials_hiddenSig') !== -1){
      html += 'your initials ';
      needsAnd   = true;
    }
    
    if(errKeys.indexOf('initial_tenant_signature') !== -1 || errKeys.indexOf('initial_tenant_signature_hiddenSig') !== -1){
      html  = needsAnd ? (html + 'and ') : html;
      html += 'your signature ';
    }
    
    html += '</div>';
    return html;
  },
  _getFontsSerialized: function(){
      var fontObj = {};
      for(var k in app._defaultData){
          if(k.match(/tnt-sig/g) !== null){
            var token = k.replace('tnt-sig-','');
            var index = isNaN(token) ? 0 : parseInt(token);
            fontObj['fontSig[' + index + ']'] = app._defaultData[k]['font'];
          }
      }
      
      return Object.keys(fontObj).length > 0 ? '&' + $.param(fontObj,true) : '';
  },
  _determineIfAllSigned: function(){
    var signaturesNeeded = $('span.sign-box').length;
    var signsFormed      = 0;
    for(var i = 0; i < signaturesNeeded; i++){
      var valid             = true;
      var numHiddenImages   = $('span.sign-box:eq(' + i + ') input[name*="_hiddenSig"]').length;
      for(var j = 0; j < numHiddenImages; j++){
        var imData   = $('span.sign-box:eq(' + i + ') input[name*="_hiddenSig"]:eq(' + j + ')').val();
        var textData = $('span.sign-box:eq(' + i + ') input[name*="_hiddenTxtSig"]:eq(' + j + ')').val();
        if(imData.length === 0 && textData.length === 0){
          valid = false;
          break;
        }
      }
      signsFormed += (valid) ? 1 : 0;
    }
    var disabled = signsFormed < signaturesNeeded;
    
    $('button.reportDoc').prop('disabled',disabled);
  },
  _getProrateAmount: function(){
    var moveinDate  = $('#move_in_date').val();
    var prorateType = $('#prorate').val();
    var baseRent    = $('#monthly_rent_\\[0\\]').val();
    AJAX({
      url    : app._urlProrate,
      data    : {id: $('#application_id').val(), moveinDate: moveinDate, prorateType:prorateType, baseRent: baseRent},
      success: function(prorateAmountAjaxData){
        if(prorateAmountAjaxData != ''){ 
          //$('#monthly_rent_\\[1\\]').val(prorateAmountAjaxData.signAgreement[prorateType]);
          app._setRentAmounts(prorateAmountAjaxData.signAgreement[prorateType]);
          
          app._calculateMoveInCosts();
          app._changeProrateType(prorateAmountAjaxData.signAgreement);
          INPUTMASK();
        }
      }
    });
  },
  _changeProrateType: function(prorateAmountAjaxData){
    $('#prorate').unbind('change').on('change', function(){
      var val = $(this).val();
      app._setRentAmounts(prorateAmountAjaxData[val]);
//      $('#monthly_rent_\\[1\\]').val(prorateAmountAjaxData[val]);
      app._calculateMoveInCosts();
      INPUTMASK();
    });
  },
  _setRentAmounts: function(prorateData){
    var deposit       = prorateData.length > 0 ? prorateData[0].amount.replace(/\$|\,|\(|\)/g,'') : $('#sec_dep_\\[0\\]').val().replace(/\$|\,|\(|\)/g,'');
    var showNextMonth = prorateData.length > 2 ? 'table-row' : 'none';
    var rent          = prorateData.length > 1 ?  prorateData[1].amount.replace(/\$|\,|\(|\)/g,'') : $('#monthly_rent_\\[0\\]').val().replace(/\$|\,|\(|\)/g,'');
    var nextRent      = prorateData.length > 2 ?  prorateData[2].amount.replace(/\$|\,|\(|\)/g,'') : $('#monthly_rent_\\[0\\]').val().replace(/\$|\,|\(|\)/g,'');
    
    $('#sec_dep_\\[0\\]').val(deposit);
    $('#monthly_rent_\\[1\\]').val(rent);
    $('#next_month_rent_\\[0\\]').val(showNextMonth === 'table-row' ? nextRent : '');
    $('#next_month_rent_\\[0\\]').attr('value',showNextMonth === 'table-row' ? nextRent: ''); 
    $('#next_month_entry').css('display',showNextMonth);
  },
  _calculateMoveInCosts: function(){
    var numInputs    = $('#move_in_costs_table input.decimal:visible:not(#total_cost_\\[0\\],#next_month_rent_\\[0\\])').length;
    var sum          = 0;
    for(var i = 0; i < numInputs; i++){
      var val = $('#move_in_costs_table input.decimal:visible:not(#total_cost_\\[0\\],#next_month_rent_\\[0\\]):eq(' + i + ')').val().replace(/\$|\,|\)|\(/g,'').trim();
      val     = isNaN(parseFloat(val)) ? 0 : parseFloat(val);
      sum    += val;
    }

    $('input[id*="total_cost_"]').val(sum);
  }
};

$(function(){ app.main(); });// Start to initial the main function
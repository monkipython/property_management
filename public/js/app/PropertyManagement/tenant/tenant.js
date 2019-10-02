var app = {
  _url: '/tenant', 
  _index:     'tenantView', 
  _urlReport: '/tenantExport',
  _urlFullBilling: '/fullbilling',
  _urlLateCharge: '/latecharge',
  _urlMassiveBilling: '/massivebilling',
  _urlUploadAgreement: '/tenantUploadAgreement',
  _urlUploadApplication: '/tenantUploadApplication',
  _urlTenantRemark: '/tenantRemark',
  _urlMoveOut: '/moveOut',
  _urlMoveOutUndo: '/moveOutUndo',
  _urlTenantEvictionProcess: '/tenantEvictionProcess',
  _gridTable: '#gridTable',
  _fullBillingIndex: 0,
  _otherMemberIndex: 0,
  main: function(){
    this.grid();
    this.lateCharge();
    this.create();
    this.massiveBilling();
  },
//------------------------------------------------------------------------------
  grid: function(){
    AJAX({
      url    : app._url,
      data: {op: 'column'},
      success: function(ajaxData){
        window.operateEvents = {
          'click .edit': function (e, value, row, index) { app.edit(row); },
          'click .editRemark': function(e,value,row,index) { app._editRemark(row); },
          'click .documentUpload': function (e, value, row, index) { app._documentUpload(row); },
          'click .pdfDownload': function (e, value, row, index) { app._pdfDownload(row); },
          'click .fullBilling': function (e, value, row, index) { app._fullBilling(row); },
          'click .delete': function(e,value,row,index) {app._deleteTenant(row);},
          'click .moveOut': function(e,value,row,index) {app._moveOut(row);},
          'click .moveOutUndo': function(e,value,row,index) {app._moveOutUndo(row);}
        };
        GRID({
          id             : app._gridTable,
          url            : app._url,
          urlReport      : app._urlReport,
          columns        : ajaxData.columns,
          fixedColumns   : false,
          fixedNumber    : 1,
          sortName       : 'prop',
          reportList     : ajaxData.reportList,
          dateColumns    : ['move_in_date', 'move_out_date']
        });
        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
          var id          = args.id;
          var sData       = {};
          sData[name]     = args[name];
          if(name == 'spec_code' && oldValue == 'E' && args[name] != 'E') {
            // If user changes spec_code from eviction to something else
            CONFIRM({
              title: '<b class="text-red"><i class="fa fa-arrow-left"></i> EVICTION PROCESS REMOVAL CONFIRMATION</b>',
              content: ' Are you sure you want to undo the eviction?',
              buttons: {
                Confirm: {
                  action: function(){
                    app._ajaxGridUpdate(id, sData);
                  },
                  btnClass: 'btn-info',
                },
                Cancel: {
                  btnClass: 'btn-danger'
                }
              }
            }); 
          }else if(name == 'spec_code' && args[name] == 'E') {
            // If user changes tenant spec_code to eviction then open tenant_eviction_process create
            CONFIRM({
              title: '<b class="text-red"><i class="fa fa-fw fa-user-times"></i> CREATE TENANT EVICTION PROCESS</b>',
              content: function(){
                var self = this;
                AJAX({
                  url     : app._urlTenantEvictionProcess + '/create',
                  dataType: 'HTML',
                  success: function(createPropAjaxData){
                    self.$content.html(createPropAjaxData);
                    INPUTMASK();
                    var formId = '#evictionProcessForm';
                    $(formId).unbind('submit').on('submit', function(){
                      sData['attorney'] = $('#attorney').val();
                      app._ajaxGridUpdate(id, sData, self);
                      return false;
                    });
                  }
                });
              },
              boxWidth:'500px',
              buttons: {
                cancel: {text: 'Close'}
              },
            });
          }else {
            app._ajaxGridUpdate(id, sData);
          }
        });
        
        $(app._gridTable).on('click-cell.bs.table',function(e,field,value,row,$element){
          switch(field){
            case 'agreement'  : app._viewAgreement(row); break;
            case 'application': app._viewApplication(row); break;
          }
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
  create: function() {
    $('#new').on('click', function() {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-address-card-o"></i> CREATE TENANT</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._url + '/create',
            dataType: 'HTML',
            success: function(createPropAjaxData){
              self.$content.html(createPropAjaxData);
              INPUTMASK();
              AUTOCOMPLETE();
              var formId = '#tenantForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._url, 
                  type: 'POST',
                  success: function(updateAjaxData){
                    GRIDREFRESH(app._gridTable);
                    self.setBoxWidth('500px');
                    self.$content.html(updateAjaxData.msg);
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'60%',
        buttons: {
          cancel: {text: 'Close'}
        },
      });
      return false;
    });
  },
  edit: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-address-card-o"></i> EDIT TENANT</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._url + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            AUTOCOMPLETE();
            var formId = '#tenantForm';
            $(formId).unbind('submit').on('submit', function(){
              var oldSpecCode = row.spec_code;
              var specCodeVal = $('#spec_code').val();
              if(oldSpecCode == 'E' && specCodeVal != 'E') {
                CONFIRM({
                  title: '<b class="text-red"><i class="fa fa-arrow-left"></i> UNDO EVICTION PROCESS CONFIRMATION</b>',
                  content: ' Are you sure you want to undo the eviction?',
                  buttons: {
                    Confirm: {
                      action: function(){
                        app._ajaxSubmitUpdate(id, formId);
                      },
                      btnClass: 'btn-info',
                    },
                    Cancel: {
                      btnClass: 'btn-danger'
                    }
                  }
                }); 
              }else if(oldSpecCode != 'E' && specCodeVal == 'E') {
                // If user changes tenant spec_code to eviction then open tenant_eviction_process create
                CONFIRM({
                  title: '<b class="text-red"><i class="fa fa-fw fa-user-times"></i> CREATE TENANT EVICTION PROCESS</b>',
                  content: function(){
                    var self = this;
                    AJAX({
                      url     : app._urlTenantEvictionProcess + '/create',
                      dataType: 'HTML',
                      success: function(createPropAjaxData){
                        self.$content.html(createPropAjaxData);
                        INPUTMASK();
                        var evictionFormId = '#evictionProcessForm';
                        $(evictionFormId).unbind('submit').on('submit', function(){
                          var sData        = JSON.parse(JSON.stringify($(formId).serialize()));
                          var attorneyData = JSON.parse(JSON.stringify($(evictionFormId).serialize()));
                          sData = sData + '&' + attorneyData;
                          app._ajaxSubmitUpdate(id, formId, sData, self);
                          return false;
                        });
                      }
                    });
                  },
                  boxWidth:'500px',
                  buttons: {
                    cancel: {text: 'Close'}
                  },
                });
              }else {
                app._ajaxSubmitUpdate(id, formId);
              }
              return false;
            });            
          }
        });
      },
      onContentReady: function () {
        AUTOCOMPLETE();
      },
      boxWidth:'90%',
      buttons: {
        cancel: {text: 'Close'}
      }
    });
  },
  lateCharge: function() {
    $('#lateCharge').on('click', function(e) {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> Generate Massive Late Charge</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._urlLateCharge + '/create',
            dataType: 'HTML',
            success: function(lateChargeAjaxData){
              self.$content.html(lateChargeAjaxData);
              INPUTMASK();
              AUTOCOMPLETE();
              var formId = '#lateChargeForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._urlLateCharge,
                  type: 'POST',
                  success: function(storeAjaxData){
                    // GRIDREFRESH(app._gridTable);
                    
                    self.setBoxWidth('500px');
                    self.$content.html(storeAjaxData.msg);
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600px',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
  massiveBilling: function() {
    $('#massive').on('click', function(e) {
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-money"></i> Generate Massive Billing</b>',
        content: function(){
          var self = this;
          AJAX({
            url     : app._urlMassiveBilling + '/create',
            dataType: 'HTML',
            success: function(billingAjaxData){
              self.$content.html(billingAjaxData);
              INPUTMASK();
              // $('.propOption').on('change', function() {
              //   app._onPropOptionChange(this.value, self);
              // });
              var formId = '#massiveForm';
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._urlMassiveBilling,
                  type: 'POST',
                  success: function(storeAjaxData){
                    // GRIDREFRESH(app._gridTable);
                    self.setBoxWidth('500px');
                    self.$content.html(storeAjaxData.msg);
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'600',
        buttons: {
          cancel: {text: 'Close'}
        }
      });
      return false;
    });
  },
  _editRemark: function(row){
    var id = row.id;
    var self = CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-address-card-o"></i> ADD / EDIT TENANT REMARK</b>',
      content: function(){
        AJAX({
          url: app._urlTenantRemark + '/create',
          data: 'tenant_id=' + id + '&fromGrid=true',
          success: function(createAjaxData){
            self.$content.html(createAjaxData.html);
            INPUTMASK();
            AUTOCOMPLETE();
            app._onRemarkClickLoad(id);
            $('#formContainer #remarkList li.nav-item a:first').trigger('click');
          }
        });
      },
      boxWidth:'50%',
      buttons: {
        cancel: {text: 'Close'}
      }
    });
  },
  _onRemarkClickLoad: function (id) {
    $('#formContainer').unbind('click').on('click', '#remarkList li.nav-item a', function(e){
      e.preventDefault();
      var formId = '#tenantRemarkForm';
      var requestData  = 'tenant_id=' + id + '&fromGrid=false';
      var remarkId     = $(this).attr('id');
      var route        = remarkId ? app._urlTenantRemark + '/' + id + '/edit' : app._urlTenantRemark + '/create';
      requestData     += remarkId ? '&remark_tnt_id=' + remarkId : '';
      AJAX({
        url: route,
        data: requestData,
        success: function(responseData){
          $(formId).html(responseData.html);
          INPUTMASK();
          app._deleteRemark();
          app._bindSubmitButton(id, remarkId);
        }
      });
    });
  },
  _deleteRemark: function() {
    $('#delete').on('click', function(e) {
      e.preventDefault();
      var remarkTntId = $('#remark_tnt_id').val();
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE REMARK</b>',
        content: ' Are you sure you want to remove this remark?',
        buttons: {
          confirm: function(){
            AJAX({
              url : app._urlTenantRemark + '/' + remarkTntId,
              type: 'DELETE',
              success: function(ajaxData){
                if(ajaxData.remarkList) {
                  $('#remarkList').html(ajaxData.remarkList);
                  $('#tenantRemarkForm').html(ajaxData.form);
                  INPUTMASK();
                  app._bindSubmitButton('','');
                }
              }
            });
          },
          cancel: function(){
          }
        }
      });
    });
  },
  _bindSubmitButton: function(id, remarkId) {
    var formId = '#tenantRemarkForm';
    var submitRoute = remarkId ? app._urlTenantRemark + '/' + id : app._urlTenantRemark;
    var method      = remarkId ? 'PUT' : 'POST';
    $(formId).unbind('submit').on('submit',function(){
      AJAXSUBMIT(formId,{
        url: submitRoute,
        type: method,
        success: function(ajaxData){
          if(ajaxData.remarkList) {
            $('#remarkList').html(ajaxData.remarkList);
            $(formId).html(ajaxData.form);
            INPUTMASK();
          }
        }
      });
      return false;
    });
  },
  _viewAgreement: function(row){
    if(row.agreement){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW TENANT AGREEMENT</b>',
        content: function(){
          var self = this;
          //Fetch agreement document from credit check application associated with the tenant
          AJAX({
            url    : app._urlUploadAgreement,
            data    : {id: row.application_id},
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              //Preview files
              self.$content.html(editUploadAjaxData);
              var formId = '#rejectForm';
              //Handle Rejection Requests
              $(formId).unbind('submit').on('submit', function(){
                AJAXSUBMIT(formId, {
                  url:   app._urlUploadAgreement + '/' + row.application_id,
                  type: 'PUT',
                  success: function(updateAjaxData){
                    dd(updateAjaxData);
                    self.setBoxWidth('500px');
                    self.$content.html(updateAjaxData.msg);
                    GRIDREFRESH(app._gridTable);
                  }
                });
                return false;
              });
            }
          });
        },
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        },
        onContentReady: function(){
          AUTOCOMPLETE();
          INPUTMASK();
        }
      });
    }
  },
  _viewApplication: function(row){
    if(row.application){
      CONFIRM({
        title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> VIEW CREDIT CHECK APPLICATION</b>',
        content: function(){
          var self = this;
          //Fetch Credit Check Application Document from Credit Check App associated with tenant
          AJAX({
            url    : app._urlUploadApplication,
            data    : {id: row.application_id},
            dataType: 'HTML',
            success: function(editUploadAjaxData){
              //Preview files
              self.$content.html(editUploadAjaxData);
            }
          });
        },
        boxWidth:'90%',
        buttons: {
          cancel: {text: 'Close'}
        },
        onContentReady: function(){
          AUTOCOMPLETE();
          INPUTMASK();
        }
      });
    }
  },
  _pdfDownload: function(row){
    console.log("pdf download");
  },
/*******************************************************************************
 ************************ HELPER FUNCTION **************************************
 ******************************************************************************/


/*******************************************************************************
 ************************ click-cell.bs.table SECTION **************************
 ******************************************************************************/
  _documentUpload: function(row){
    var id = row.application_id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-users"></i> TENANT FILE UPLOAD</b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._urlUploadAgreement + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            app._onUploadChange(id);
          }
        });
      },
      boxWidth:'90%',
      buttons: {
        cancel: {
          text: 'Close',
          action: function(){
            GRIDREFRESH(app._gridTable);
          }
        }
      }
    }); 
  },
  _onUploadChange: function(id) {
    $('.uploadSelect').on('change', function() {
      var selectedVal = $(this).val();
      var url = selectedVal == 'agreement' ? app._urlUploadAgreement : app._urlUploadApplication;
      var $findUploader = $('#fine-uploader');
      if($findUploader.children().length > 0) {
        $findUploader.empty();
        if(selectedVal == '') {
          return;
        }
      }
      UPLOAD(
        {},
        url, 
        {op:'CreditCheckUpload', type:selectedVal, foreign_id: id}
      );
    });
  },
  _fullBilling: function(row) {
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-fw fa-address-card-o"></i> TENANT FULL BILLING </b>',
      content: function(){
        var self = this;
        AJAX({
          url    : app._urlFullBilling + '/' + id + '/edit',
          success: function(editUploadAjaxData){
            var titles = ['Service#', 'Service Description', 'Amount', 'Post Date', 'Start Date', 'End Date'];
            app._otherMemberIndex = editUploadAjaxData.totalOtherMember;
            app._fullBillingIndex = editUploadAjaxData.totalFullBilling;
            self.$content.html(editUploadAjaxData.html);
            app._addFullBillingTitle(titles);
            $('select.readonly option:not(:selected)').attr('disabled', 'disabled');
            $('#moreBilling,#moreOtherMember').unbind('click').on('click', function(){
              if($(this).attr('id') == 'moreBilling'){
                var fullBilling = Helper.replaceArrayKeyId(editUploadAjaxData.fullBilling.emptyField, app._fullBillingIndex);
                $('#fullBilling').append(fullBilling);
                app._addFullBillingTitle(titles);
                var emptyFieldRows = $('.emptyField');
                var billingRemoveDoms = $('.billingRemove');
                emptyFieldRows.eq(emptyFieldRows.length-1).attr('data-key', app._fullBillingIndex);
                billingRemoveDoms.eq(billingRemoveDoms.length-1).attr('data-key', app._fullBillingIndex);
                $('#stop_date\\[' + app._fullBillingIndex + '\\]').val('12/31/9999');
                app._bindBillingStopDate();
                app._fullBillingIndex++;
              } else {
                var otherMember = Helper.replaceArrayKeyId(editUploadAjaxData.otherMember, app._otherMemberIndex);
                $('#otherMember').append(otherMember);
                var emptyFieldRows = $('.otherMemberEmptyField');
                var memberRemoveDoms = $('.memberRemove');
                emptyFieldRows.eq(emptyFieldRows.length-1).attr('data-key', app._otherMemberIndex);
                memberRemoveDoms.eq(memberRemoveDoms.length-1).attr('data-key', app._otherMemberIndex);
                app._otherMemberIndex++;
              }
              INPUTMASK();
              AUTOCOMPLETE();
              app._destroyBilling(id, titles);
            });
            INPUTMASK();
            AUTOCOMPLETE();
            app._destroyBilling(id, titles);
            app._updateFullbilling(self, id);
          }
        });
      },
      onContentReady: function(){
        app._bindBillingStopDate();  
      },
      boxWidth:'98%',
      buttons: {
        cancel: {text: 'Close'}
      }
    }); 
  },
  _updateFullbilling: function(self, id){
    var formId = '#tenantBillingForm';
    $(formId).unbind('submit').on('submit', function(){
      AJAXSUBMIT(formId, {
        url:   app._urlFullBilling + '/' + id,
        type: 'PUT',
        success: function(updateAjaxData){
          // console.log(updateAjaxData);
          GRIDREFRESH(app._gridTable);
          self.setBoxWidth('500px');
          self.$content.html(updateAjaxData.msg);
        }
      });
      return false;
    });
  },
  _deleteTenant: function(row){
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-trash"></i> DELETE TENANT</b>',
      content: ' Are you sure you want to remove this tenant.',
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
  _destroyBilling: function(id, titles){
    $('.billingRemove,.memberRemove').unbind('click').on('click', function(e){
      var obj = $(this);
      var objClass = obj.attr('class');
      var dataKey = obj.attr("data-key");
      var removeDom = objClass === 'billingRemove' ? $('#fullBilling div[data-key='+dataKey+']') : $('#otherMember div[data-key='+dataKey+']');
      var idDome = removeDom.find(':input[type="hidden"]');
      
      if(parseInt(idDome.eq(0).val()) > 0){
        var confirmTitle = objClass === 'billingRemove' ? 'DELETE FULL BILLING' : 'DELETE MEMBER';
        var confirmContent = objClass === 'billingRemove' ? 'Are you sure you want to remove this billing?' : 'Are you sure you want to remove this member?';
        // Call delete full billing 
        CONFIRM({
          title: '<b class="text-red"><i class="fa fa-trash"></i>'+confirmTitle+'</b>',
          content: confirmContent,
          buttons: {
            confirm: function(){
              AJAX({
                url : app._urlFullBilling + '/' + idDome.eq(0).val(),
                data: {op: objClass,'tenant_id': id},
                type: 'DELETE',
                success: function(data){
                  removeDom.remove();
                  app._addFullBillingTitle(titles);
                  if(objClass === 'billingRemove'){
                    app._fullBillingIndex--;
                  } else {
                    app._otherMemberIndex--;
                  }
                }
              });
            },
            cancel: function(){
            }
          }
        });
      }else{
        removeDom.remove();
        app._addFullBillingTitle(titles);
      }
    });
  },
  _addFullBillingTitle: function(titles) {
    var fullBillingLength = $('#fullBilling > div').length;
    var firstRowTitle = $('#fullBilling > div .fullBillingTitle').length;
    if(fullBillingLength > 0 && firstRowTitle == 0) {
      $('#fullBilling div:first-child > div').each(function(i){
        $(this).prepend('<h4 class="fullBillingTitle">' + titles[i] + '</h4>');
      });
    }
  },
//------------------------------------------------------------------------------
_moveOut: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-arrow-right"></i> MOVE OUT TENANT</b>',
      content: function(){
        var self = this;
        AJAX({
          url: app._urlMoveOut + '/' + id + '/edit',
          dataType: 'HTML',
          success: function(editUploadAjaxData){
            self.$content.html(editUploadAjaxData);
            INPUTMASK();
            AUTOCOMPLETE();
            var formId = '#tenantMoveOutForm';
            $(formId).unbind('submit').on('submit', function(){
              AJAXSUBMIT(formId, {
                url:   app._urlMoveOut,
                type: 'POST',
                success: function(updateAjaxData){
                  GRIDREFRESH(app._gridTable);
                  self.$content.html(updateAjaxData.msg);
                }
              });
              return false;
            });  
          }
        });
      },
      buttons: {
        Close: {
          btnClass: 'btn-secondary'
        }
      }
    }); 
  },
//------------------------------------------------------------------------------
  _moveOutUndo: function(row){
    var id = row.id;
    CONFIRM({
      title: '<b class="text-red"><i class="fa fa-arrow-left"></i> UNDO MOVE OUT CONFIRMATION</b>',
      content: ' Are you sure you want to undo the move out?',
      buttons: {
        Confirm: {
          action: function(){
            AJAX({
              url:  app._urlMoveOutUndo,
              data: {'tenant_id': id},
              type: 'POST',
              success : function(storeMoveOutAjaxData){
                GRIDREFRESH(app._gridTable);
              }
            });
          },
          btnClass: 'btn-info',
        },
        Cancel: {
          btnClass: 'btn-danger'
        }
      }
    }); 
  },
//------------------------------------------------------------------------------
  _ajaxGridUpdate: function(id, sData, self='') {
    AJAX({
      url    : app._url + '/' + id,
      data   : sData,
      type   : 'PUT',
      success: function(updateAjaxData){
        if(updateAjaxData.error !== undefined){
          ALERT({content: updateAjaxData.error.popupMsg});
        }else{
          GRIDREFRESH(app._gridTable);
        }
        if(self) {
          self.close();
        }
      }
    });
  },
//------------------------------------------------------------------------------
  _ajaxSubmitUpdate: function(id, formId, sData=null, self='') {
    AJAXSUBMIT(formId, {
      url:   app._url + '/' + id,
      type: 'PUT',
      data: sData,
      success: function(updateAjaxData){
        if(updateAjaxData.error !== undefined){
          ALERT({content: updateAjaxData.error.popupMsg});
        }else{
          GRIDREFRESH(app._gridTable);
        }
        if(self) {
          self.close();
        }
      }
    });
  },
//------------------------------------------------------------------------------
  _bindBillingStopDate: function(){
    $('#fullBilling .row select[name*="schedule"]').unbind('change').on('change',function(){
      var parent    = $(this).parents('div.row');
      var dataKey   = parent.attr('data-key');
        
      var schedule  = $(this).val();
      var stopDate  = schedule === 'M' ? '12/31/9999' : moment().endOf('month').format('MM/DD/YYYY');
      var dateInput = '#fullBilling div.row[data-key=' + dataKey + '] input[name*="stop_date"]';
      $(dateInput).val(stopDate);
    });
  },
};

// Start to initial the main function
$(function(){ app.main(); });
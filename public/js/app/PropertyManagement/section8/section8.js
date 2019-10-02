var app = {
	_url: '/section8',
    _urlReport: '/section8Export',
	_index: 'section_8_view',
	_gridTable: '#gridTable',
	main: function(){
		this.grid();
		this.create();
	},
//------------------------------------------------------------------------------
	grid: function(){
		AJAX({
			url: app._url,
			data: {op:'column'},
			success: function(ajaxData){
				window.operateEvents = {
          			'click .edit': function (e, value, row, index) { app.edit(row); }
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
		          dateColumns    : ['first_inspection_date', 'second_inspection_date', 'third_inspection_date'],
		        });
		        $(app._gridTable).on('editable-save.bs.table', function (e, name, args, oldValue, $el) {
		          var id = args.id;
		          var sData = {};
		          sData[name] = args[name];
		          AJAX({
		            url    : app._url + '/' + id,
		            data   : sData,
		            type   : 'PUT',
		            success: function(updateAjaxData){
		              if(updateAjaxData.error !== undefined){
		                $el[0].innerHTML = oldValue;
		              }
		              Helper.displayGridEditErrorMessage(updateAjaxData);
		            }
		          });
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
	edit: function(row){
		var id = row.id;
	    CONFIRM({
	      title: '<b class="text-red"><i class="fa fa-fw fa-exclamation-triangle"></i> EDIT INSPECTION</b>',
	      content: function(){
	        var self = this;
	        AJAX({
	          url    : app._url + '/' + id + '/edit',
	          dataType: 'HTML',
	          success: function(editUploadAjaxData){
	            self.$content.html(editUploadAjaxData);
	            INPUTMASK();
	            AUTOCOMPLETE();
	            var formId = '#section8Form';
	            $(formId).unbind('submit').on('submit', function(){
	              AJAXSUBMIT(formId, {
	                url:   app._url + '/' + id,
	                type: 'PUT',
	                success: function(updateAjaxData){
	                  GRIDREFRESH(app._gridTable);
	                }
	              });
	              return false;
	            });
	          }
	        });
	      },
	      boxWidth:'70%',
	      buttons: {
	        cancel: {text: 'Close'}
	      }
	    }); 
	},
	create: function(){
		$('#new').on('click', function() {
	      CONFIRM({
	        title: '<b class="text-red"><i class="fa fa-fw fa-exclamation-triangle"></i> CREATE INSPECTION</b>',
	        content: function(){
	          var self = this;
	          AJAX({
	            url     : app._url + '/create',
	            dataType: 'HTML',
	            success: function(createPropAjaxData){
	              self.$content.html(createPropAjaxData);
	              INPUTMASK();
	              AUTOCOMPLETE();   
	              var formId = '#section8Form';
	              $(formId).unbind('submit').on('submit', function(){
	                AJAXSUBMIT(formId, {
	                  url:   app._url, 
	                  type: 'POST',
	                  success: function(updateAjaxData){
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
	        boxWidth:'70%',
	        buttons: {
	          cancel: {text: 'Close'}
	        }
	      });
	      return false;
	    });
	},
};


// Start to initial the main function
$(function(){ app.main(); });
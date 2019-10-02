var app = {
  _url: '/dashboard',
  _minToRefresh: 5,
  _timer: 0,
  _sleepInterval: 1,
  _chartRoutes: [],
  main: function(){
    app._initChartRoutes();
    app._bindEvents();
    this.getGraph();
  },
  _initChartRoutes: function(){
    app._minToRefresh = $('#refreshRate').val();
    app._chartRoutes = [];
    var len          = $('.chart-checkbox').length;
    for(var i = 0; i < len; i++){
      var route = $('.chart-checkbox:eq(' + i + ')').val();
      var types = typeof($('.chart-checkbox:eq(' + i + ')').attr('data-types')) != 'undefined' ?  $('.chart-checkbox:eq(' + i + ')').attr('data-types').split(',') : [];
      app._chartRoutes.push({route:route,types:types});
    }
  },
  _bindEvents: function(){
    $('#refreshRate').unbind('change').on('change',function(e){
      app._minToRefresh = e.target.value.length > 0 ? parseFloat(e.target.value) : 0;
      app._timer        = app._minToRefresh * 60 * 1000;
      if(app._minToRefresh == 0){
        clearTimeout(app._timer);
      }
    });
  },
  getGraph: function(){
    var len = app._chartRoutes.length;
    for(var i = 0; i < len; i++){
      (function(index){
        var idx        = index;
        var route      = app._chartRoutes[idx];
        var types      = route.types;
        var numTypes   = types.length;
        var tabListId  = route.route + 'tabList';
        
        var type       = numTypes > 0 ? $('#' + tabListId + ' li.active a').first().attr('href').replace(new RegExp('^#' + route.route,'g'),'') : '';
        var chartId    = route.route + 'Chart' + type;
        var parentId   = route.route + 'Parent' + type; 
        var formId     = $('#' + parentId).attr('data-form');
        
        var dataString = numTypes > 0 ? '&op=graph&type=' + type : '&op=graph';
        app._loadGraph(route.route,chartId,parentId,$('#'+formId).serialize() + dataString,index === len - 1);
        //var sleep      = setTimeout(function(){},app._sleepInterval * 1000);
      }(i));
    }
    
    $('a[data-toggle="tab"]').on('shown.bs.tab',function(e){
      var target     = $(e.target).attr('href');
      var route      = $(this).parents('ul').first().attr('id').replace(/^#|tabList/g,'');
      var type       = target.replace(new RegExp('^#' + route,'g'),'');
      var chartId    = route + 'Chart' + type;
      var parentId   = route + 'Parent' + type; 
      var formId     = $('#' + parentId).attr('data-form');
      var dataString = '&op=graph&type=' + type;
      app._loadGraph(route,chartId,parentId,$(formId).serialize() + dataString,false);
    });
  },
  _instantiateRefresh: function(){
    var interval      = parseFloat(app._minToRefresh) * 60 * 1000;
    if(interval > 0){
      app._timer        = setTimeout(function(){
        app._timer        = setTimeout(function(){app.getGraph();},interval);
      },interval);
    } else {
      clearTimeout(app._timer);
    }
  },
  _loadGraph: function(route,chartId,parentId,dataString,refreshGraph){
    var momentTransform = function(cfg){
      cfg.data.labels = cfg.data.labels.map(function(v){return moment(v,'YYYY-MM-DD');});
      return cfg;
    };
    
    var currencyTransform = function(cfg,axisKey){
      cfg.options['scales'][axisKey][0]['ticks'] = {
        callback: function(value,index,values){
          return app._formatCurrency(value);
        }
      };
      cfg.options['tooltips'] = {
        callbacks: {
          label: function(t,d){
            var xLabel = d.datasets[t.datasetIndex].label;
            var yLabel = app._formatCurrency(t.xLabel);
            return xLabel.replace(/s$/,'') + ': ' + yLabel;
          }
        }
      };
      
      cfg.options.plugins.datalabels['formatter'] = function(value,context){
        return app._formatCurrency(value);
      };
      return cfg;
    };

    AJAX({
      url: '/' + route,
      data: dataString,
      success:function(responseData){
        app._refreshCanvas(chartId,parentId);
        var ctx = document.getElementById(chartId).getContext('2d');
        var cfg = responseData.options;
        if(responseData.transformLabels){
          var transformFn = responseData.transformLabels;
          cfg.plugins = [ChartDataLabels];
          switch(transformFn){
            case 'toMoment': cfg = momentTransform(cfg); break;
            case 'currencyXAxes': cfg = currencyTransform(cfg,'xAxes'); break;
            default: break;
          }
        }
        var chart = new Chart(ctx,cfg);
        if(refreshGraph){
          app._instantiateRefresh();
        }
      }
    });
  },
  _refreshCanvas: function(id,parent){
    $('#' + id).remove();
    $('#' + parent).append('<canvas id="' + id + '"></canvas>');
  },
  _formatCurrency: function(num){
    return parseInt(num).toLocaleString('en-US',{style:'currency',currency:'USD'});
  }
};

$(function(){app.main();});




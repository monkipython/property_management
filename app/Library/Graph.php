<?php
namespace App\Library;
use App\Library\{Helper,TableName as T};
class Graph {
  private static $_defaultLabelSettings =[
    'anchor'   => 'end',
    'align'    => 'end',
    'font'     => [
      'size' => 12,
    ]
  ];
//------------------------------------------------------------------------------
  /**
   * Example: 
   *  $options = Graph::getGraphSettings([
        'labels'     => ['Fresno','Pomona','Newport','Long Beach'],
        'dataset'    => [['label'=>'Vacancies','data'=>[3,4,5,12],'params'=>['backgroundColor'=>'rgba(132, 44, 23, 0.5)']]]],
        'title'      => 'Vacancy By City',
        'graphType'  => 'bar',
        'xLabel'     => 'City',
        'yLabel'     => 'Unit Vacancies',
        'legend'     => ['position'=>'left'],
        'xTicks'     => [
          'autoSkip'    => false,
          'minRotation' => 90,
          'maxRotation' => 90,
        ],
        'datalabels' => [
          'anchor'  => 'end',
          'align'   => 'end',
          'font'    => [
            'size' => 12,
          ]
        ],
        'options'    => [
          'elements' => [
            'rectangle' => [
              'borderWidth' => 2,
            ]
          ]
        ]
     ]);
   */
  public static function getGraphSettings($data){
    //Labels for the graph (i.e. the names of the xLabels or categories in a bar chart)
    $labels         = !empty($data['labels']) ? $data['labels'] : [];
    //Type of Chart (i.e. Line, Bar, HorizontalBar, Scatter, Timeseries, PI, Donut, etc.)
    $graphType      = !empty($data['graphType']) ? $data['graphType'] : 'bar';
    //Settings for labeling the data on the plot
    $labelSettings  = !empty($data['datalabels']) ? $data['datalabels'] : self::$_defaultLabelSettings;
    //Actual Graph Data, capable of plotting multiple datasets in a single chart. 
    //(i.e a categorical bar chart with two bars per category)
    $datasets       = !empty($data['dataset']) ? $data['dataset'] : [];
    //Title for the X Axis
    $xLabel         = !empty($data['xLabel']) ? $data['xLabel'] : '';
    //Title for the Y Axis
    $yLabel         = !empty($data['yLabel']) ? $data['yLabel'] : '';
    //Title of the plot / chart
    $title          = !empty($data['title']) ? $data['title'] : '';
    //Legend Settings, i.e (the orientation of the legend in the canvas)
    $legend         = !empty($data['legend']) ? $data['legend'] : ['position'=>'top','labels'=>['fontSize'=>14]];
    //Additional Graph Options to be passed to Chart.js
    $optionParams   = !empty($data['options']) ? $data['options'] : [];
    $graphData      = [];
    foreach($datasets as $v){
      $dataset = [];
      
      //Get Label for Dataset that can be applied to the legend
      $dataset['label']    = !empty($v['label']) ? $v['label'] : '';
      //Get data (i.e. y values) for the dataset
      $dataset['data']     = !empty($v['data']) ? $v['data'] : [];
      $params              = !empty($v['params']) ? $v['params'] : [];
      
      //Set additional styling and canvas options
      foreach($params as $k=>$val){
        $dataset[$k] = $val;
      }
      
      $dataset['backgroundColor'] = !empty($dataset['backgroundColor']) ? $dataset['backgroundColor'] : 'rgba(255, 99, 132, 0.5)';
      $dataset['borderColor']     = !empty($dataset['borderColor']) ? $dataset['borderColor'] : 'rgb(255, 99, 132)';
      $dataset['borderWidth']     = !empty($dataset['borderWidth']) ? $dataset['borderWidth'] : 1;
      $graphData[]                = $dataset;
    }
    
    
    
    $settings    = [
      'scales' => [
        'yAxes' => [
          [
            'scaleLabel' => [
              'display'     => true,
              'labelString' => $yLabel,
              'fontSize'    => 16,
            ]
          ]
        ],
        'xAxes' => [
          [
            'scaleLabel' => [
              'display'     => true,
              'labelString' => $xLabel,
              'fontSize'    => 16,
            ]
          ]
        ]
      ],
      'legend'  => $legend,
      'title'   => [
        'display'  => true,
        'fontSize' => 24,
        'text'     => $title,
      ],
      'plugins' => [
        'datalabels' => $labelSettings,
      ]
    ];
    
    //Set x and y tick settings options if present (i.e font-size, step-interval, and orientation settings)
    if(!empty($data['xTicks'])){
      $settings['scales']['xAxes'][0]['ticks'] = $data['xTicks'];
    }
    
    if(!empty($data['yTicks'])){
      $settings['scales']['yAxes'][0]['ticks'] = $data['yTicks'];
    }
    
    foreach($optionParams as $k=>$v){
      $settings[$k] = $v;
    }
    
    $options  = [
      'responsive'            => true,
      'maintainAspectRatio'   => true,
      'type'                  => $graphType,
      'data'                  => [
        'labels'    => $labels,
        'datasets'  => $graphData,
      ],
      'options'     => $settings,
    ];
    //Return Graph Configuration
    return $options;
  }
}


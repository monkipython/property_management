<?php

return [
  /*
  |--------------------------------------------------------------------------
  | Default Filesystem Disk
  |--------------------------------------------------------------------------
  |
  | Here you may specify the default filesystem disk that should be used
  | by the framework. The "local" disk, as well as a variety of cloud
  | based disks are available to your application. Just store away!
  |
  */

  'default' => env('FILESYSTEM_DRIVER', 'local'),

  /*
  |--------------------------------------------------------------------------
  | Default Cloud Filesystem Disk
  |--------------------------------------------------------------------------
  |
  | Many applications store files both locally and in the cloud. For this
  | reason, you may specify a default "cloud" driver here. This driver
  | will be bound as the Cloud disk implementation in the container.
  |
  */

  'cloud' => env('FILESYSTEM_CLOUD', 's3'),

  /*
  |--------------------------------------------------------------------------
  | Filesystem Disks
  |--------------------------------------------------------------------------
  |
  | Here you may configure as many filesystem "disks" as you wish, and you
  | may even configure multiple disks of the same driver. Defaults have
  | been setup for each driver as an example of the required options.
  |
  | Supported Drivers: "local", "ftp", "sftp", "s3", "rackspace"
  |
  */

  'disks' => [
    'elasticSearchView'=>[
      'driver' => 'local',
      'root' => storage_path('app/elasticSearchView'),
    ],
    'local' => [
      'driver' => 'local',
      'root' => storage_path('app'),
    ],
    'RPS' => [
      'driver' => 'local',
      'root'   => (env('APP_ENV') == 'production') ? '/home/apache/rps/' : '/home/www/rps/test/',
    ],
    'public' => [
      'driver' => 'local',
      'root' => storage_path('app/public'),
      'url' => env('APP_URL').'/storage',
      'visibility' => 'public',
    ],
    's3' => [
      'driver' => 's3',
      'key' => env('AWS_ACCESS_KEY_ID'),
      'secret' => env('AWS_SECRET_ACCESS_KEY'),
      'region' => env('AWS_DEFAULT_REGION'),
      'bucket' => env('AWS_BUCKET'),
      'url' => env('AWS_URL'),
    ],
################################################################################
##########################     SFTP SECTION    #################################  
################################################################################  
    'sftpFarmer'=>[
      'driver'   => 'sftp',
      'host'     => 'sftp.fmb.com',
      'username' => 'pama2',
      'password' => '00pnAELD',
      'root'     => '/',
      'timeout'  => 10,
    ],
    'sftpEastWest'=>[
      'driver'   => 'sftp',
      'host'     => 'fts.eastwestbank.com',
      'username' => 'pamaftp',
      'password' => '3wc$gD5B',
      'root'     => '/Home/PAMA/',
      'timeout'  => 10,
    ],
    'sftpNanoBanc'=>[
      'driver'   => 'ftp',
      'host'     => 'sftp.nanobanc.com',
      'port'     => 990,
      'username' => 'Pama',
      'password' => 'pdgm234f',
      'root'     => '/Home/pama/',
      'timeout'  => 10,
      'ssl' => false,
      'ignorePassiveAddress' => true,
    ],
    'sftpTorryPines'=>[
      'driver'   => 'sftp',
      'host'     => '199.71.239.200',
      'username' => 'NijjarRealty_KZhang',
      'password' => '16Kar6709!',
      'root'     => '/',
      'timeout'  => 10,
    ],
    'sftpMechanicsBank'=>[
      'driver'   => 'sftp',
      'host'     => 'fs2.crbnk.com',
      'username' => 'pamamgmt',
      'password' => 'd!Hi:/+s2C',
      'root'     => '/Home/pamamgmt',
      'timeout'  => 10,
    ],   
    
  ],
];

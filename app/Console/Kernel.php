<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel{
  /**
   * The Artisan commands provided by your application.
   *
   * @var array
   */
  protected $commands = [
//    'App\Console\CreditCheck\MigrateData',
    'App\Console\CreditCheck\ReportCreditCheckDaily',
    'App\Console\CreditCheck\ReportMoveIn',
    'App\Console\CreditCheck\FixIssue',
    'App\Console\InsertToElastic\InsertToElastics',
    'App\Console\CreditCheck\RentalAgreement',
    ##### REPORT #####
    'App\Console\Report\VacancyReport\VacancyReport',
    'App\Console\Report\ViolationReport\ViolationReport',
    'App\Console\Report\Section8Report\Section8Report',
    'App\Console\Report\MoveOutReport\MoveOutReport',
//    'App\Console\Report\ReadyMoveInReport\ReadyMoveInReport',
    ##### GENERATE TASK #####
    'App\Console\GeneralTask\Reindex',
    'App\Console\GeneralTask\ReindexByWhere',
    'App\Console\GeneralTask\DeleteElasticByWhere',
    'App\Console\GeneralTask\TenantBillingError',
//    'App\Console\GeneralTask\GenerateDotRent',
    'App\Console\GeneralTask\RefreshTenantRent',
    'App\Console\GeneralTask\RefreshLatestTenantBilling',
    'App\Console\GeneralTask\RefreshPendingLastRentRaise',
    'App\Console\GeneralTask\FindPercentageChangeBilling',
    'App\Console\GeneralTask\GetTenantBillingCount',
    'App\Console\GeneralTask\GetTenantNoBilling',
    ##### PROPERTY MANAGEMENT #####
    'App\Console\PropertyManagement\TenantMassiveBilling',
    'App\Console\PropertyManagement\TenantStatementByGroup',
    'App\Console\PropertyManagement\TenantUpdateStatus',
    ##### MIGRATION     #####
    'App\Console\GeneralTask\MigrateRentRaise',
    'App\Console\GeneralTask\MigrateTenantBaseRent',
    'App\Console\GeneralTask\MigrateLastRaiseDate',
    'App\Console\GeneralTask\MigrateTenantBaseRentCsv',
    'App\Console\GeneralTask\MigrateTntTransViewDb',
    'App\Console\GeneralTask\ResyncTenantBilling',
    ##### CLEAN UP TRANSACTION #####
    'App\Console\GeneralTask\CleanTransaction',
    ##### UPLOAD #####
//    'App\Console\BankRec\BankRecUpload'
    ##### CASHIER CHECK #####
    'App\Console\AccountPayable\CashierCheck',
  ];

  /**
   * Define the application's command schedule.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
   * @return void
   */
  protected function schedule(Schedule $schedule){
//    $schedule->command('report:creditCheckDaily')->dailyAt('12:00');
    $schedule->command('report:creditCheckDaily')->dailyAt('16:00');
    $schedule->command('report:creditCheckDaily')->dailyAt('19:30');
    
//    $schedule->command('report:movein')->dailyAt('12:00');
    $schedule->command('report:movein')->dailyAt('16:00');
    $schedule->command('report:movein')->dailyAt('19:30');
    
    $schedule->command('report:moveout')->dailyAt('16:00');
    $schedule->command('report:moveout')->dailyAt('19:30');
    
    $schedule->command('report:vacancy')->dailyAt('7:00');
    $schedule->command('report:violation')->dailyAt('7:00');
    
    $schedule->command('tenant:massiveBilling')->monthlyOn('27', '23:50');
    $schedule->command('tenant:statementByGroup')->dailyAt('5:00');
    $schedule->command('tenant:updateStatus')->dailyAt('01:00');
    $schedule->command('general:RefreshTenantRent')->dailyAt('02:00');
    
    ##### CASHIER CHECK PRINTING #####
//    $schedule->command('accoutPayable:cashierCheck')->everyFiveMinutes();
    
    
    ##### REINDEX ALL DATA IN ELASTIC #####
//    $schedule->command('general:elasticReindex')->monthlyOn('27', '21:50');
    ##### FOR TESTING SECTION #####
//    $schedule->command('report:vacancy')->everyMinute();
    
    ##### FOR ONE TIME RUN ONLY #####
//    $schedule->command('general:elasticReindex credit_check_view')->monthlyOn('22', '2:50');
  }

  /**
   * Register the commands for the application.
   *
   * @return void
   */
  protected function commands(){
    $this->load(__DIR__.'/Commands');

    require base_path('routes/console.php');
  }
}

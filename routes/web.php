<?php
Route::group(['middleware' => ['IsLogin', 'CheckPermission', 'TrackUpdateStore','TrackRent']], function () {
  ### ACCOUNT SECTION ###
  Route::resource('/profile',       'Account\ProfileController',              ['only'=>['index', 'edit', 'store', 'update', 'show', 'create']]);
  Route::resource('/role',          'Account\RoleController',                 ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/account',       'Account\AccountController',              ['only'=>['index', 'update', 'create', 'destroy']]);
  Route::resource('/permission',    'Account\PermissionController',           ['only'=>['index', 'edit', 'update', 'show', 'store']]);

  ### CREDIT CHECK SECTION ###
  Route::resource('/creditCheck',       'CreditCheck\CreditCheckController',               ['only'=>['index', 'edit', 'store', 'update', 'show', 'create']]);
  Route::resource('/uploadAgreement',   'CreditCheck\Upload\AgreementController',      ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/uploadApplication', 'CreditCheck\Upload\ApplicationController',    ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/movein',            'CreditCheck\MoveIn\MoveInController',             ['only'=>['edit', 'store']]);
  Route::resource('/prorate',           'CreditCheck\MoveIn\ProrateController',            ['only'=>['index', 'edit', 'store', 'update', 'show', 'create']]);
  Route::resource('/transferTenant',    'CreditCheck\TransferTenant\TransferTenantController',       ['only'=>['index', 'edit', 'store', 'update', 'show', 'create']]);
  Route::resource('/creditCheckReport', 'CreditCheck\Report\ReportController',       ['only'=>['index']]);
  
  ### COMPANY ###
  Route::resource('/company',       'PropertyManagement\Company\CompanyController',              ['only' => ['index', 'edit', 'update', 'create', 'store', 'destroy']]);
          
  ### PROPERTY SECTION ###
  Route::resource('/prop',          'PropertyManagement\Prop\PropController',                    ['only' => ['index', 'edit', 'update', 'create', 'store']]);
  Route::resource('/massiveProp',   'PropertyManagement\Prop\Massive\MassivePropController',     ['only' => ['create', 'store']]);
  Route::resource('/propExport',    'PropertyManagement\Prop\ExportCsv\PropExportController',    ['only'=>['index']]);
  
  ### Section 8 SECTION ###
  Route::resource('/section8',      'PropertyManagement\Section8\Section8Controller',                 ['only' => ['index','edit','update','create','store']]);
  Route::resource('/section8Export','PropertyManagement\Section8\ExportCsv\Section8ExportController', ['only' => ['index']]);
  
  ### Rent Raise SECTION ###
  Route::resource('/rentRaise',          'PropertyManagement\RentRaise\RentRaiseController',          ['only' => ['index','edit','create','update','store']]);
  Route::resource('/pastRentRaiseNotice','PropertyManagement\RentRaise\PastRentRaiseNotice\PastRentRaiseNoticeController',['only'=>['index','show']]);
  Route::resource('/uploadRentRaise',    'PropertyManagement\RentRaise\Upload\UploadRentRaiseController', ['only'=>['index','edit','store','show','create','destroy']]);
  Route::resource('/rentRaiseExport',    'PropertyManagement\RentRaise\ExportCsv\RentRaiseExportController',   ['only' => ['index']]);
  Route::resource('/rentRaiseTenantInfo','PropertyManagement\RentRaise\Autocomplete\RentRaiseTenantInfoController',['only'=>['index']]);
  
  ### UNIT SECTION ###
  Route::resource('/unit',          'PropertyManagement\Unit\UnitController',                   ['only' => ['index','edit','store','update','show','create','destroy']]);
  Route::resource('/unitDate',      'PropertyManagement\Unit\UnitDate\UnitDateController',      ['only'=>['index','edit','store','update','show','create']]);
  Route::resource('/unitFeatures',  'PropertyManagement\Unit\UnitFeature\UnitFeatureController',['only'=>['index','edit','store','update','show','create']]);
  Route::resource('/unitHist',      'PropertyManagement\Unit\UnitHist\UnitHistController',      ['only'=>['index','edit','store','update','show','create']]);
  Route::resource('/unitExport',    'PropertyManagement\Unit\ExportCsv\UnitExportController',   ['only'=>['index']]);
  
  ### BANK SECTION ###
  Route::resource('/bank',          'PropertyManagement\Bank\BankController',                    ['only' => ['index', 'edit', 'update', 'create', 'store']]);
  Route::resource('/bankExport',    'PropertyManagement\Bank\ExportCsv\BankExportController',    ['only' => ['index']]);
  
  ### GROUP SECTION ###
  Route::resource('/group',           'PropertyManagement\Group\GroupController',                  ['only' => ['index', 'edit', 'update', 'create', 'store']]);
  Route::resource('/groupExport',     'PropertyManagement\Group\ExportCsv\GroupExportController',  ['only' => ['index']]);
  Route::resource('/uploadGroupFile', 'PropertyManagement\Group\Upload\GroupFileController',       ['only'=>['index', 'edit', 'store', 'show', 'destroy']]);

  ### TRUST SECTION ###
  Route::resource('/trust',         'PropertyManagement\Trust\TrustController',                  ['only' => ['index', 'edit', 'update', 'create', 'store']]);
  Route::resource('/trustExport',   'PropertyManagement\Trust\ExportCsv\TrustExportController',  ['only' => ['index']]);

  ### VIOLATION SECTION ###
  Route::resource('/violation',     'PropertyManagement\Violation\ViolationController',                  ['only' => ['index', 'edit', 'update', 'create', 'store']]);
  Route::resource('/violationExport','PropertyManagement\Violation\ExportCsv\ViolationExportController', ['only'=>['index']]);
  Route::resource('/uploadViolation', 'PropertyManagement\Violation\Upload\ViolationFileController',     ['only'=>['index', 'edit', 'store', 'show', 'destroy']]);
 
  ### AUTOCOMPLETE SECTION ###
  Route::resource('/autocomplete', 	'Autocomplete\AutocompleteController',    ['only'=>['show']]);
  Route::resource('/autocompletev2', 	'Autocomplete\AutocompleteV2Controller',    ['only'=>['show']]);
  
  Route::resource('/filter',        'Filter\OptionFilterController',          ['only'=>['show']]);
  Route::resource('/download',      'Download\DownloadController',            ['only'=>['show']]);
//  Route::resource('/upload',        'Upload\UploadController',                ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/generatePDF',   'GeneratePDF\GeneratePDFController',      ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);

  ### TENANT BALANCE SECTION ###
  Route::resource('/tenantBalance',   'PropertyManagement\TenantBalance\TenantBalanceController',              ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  
  ### TENANT SECTION ###
  Route::resource('/tenant',          'PropertyManagement\Tenant\TenantController',                            ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/fullbilling',     'PropertyManagement\Tenant\FullBilling\FullBillingController',           ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/massivebilling',  'PropertyManagement\Tenant\MassiveBilling\MassiveBillingController',     ['only'=>['store', 'create']]);
  Route::resource('/latecharge',      'PropertyManagement\Tenant\LateCharge\LateChargeController',             ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/tenantExport',    'PropertyManagement\Tenant\ExportCsv\TenantExportController',            ['only'=>['index']]);
  Route::resource('/moveOut',         'PropertyManagement\Tenant\MoveOut\MoveOutController',                   ['only'=>['store', 'edit']]);
  Route::resource('/moveOutUndo',     'PropertyManagement\Tenant\MoveOutUndo\MoveOutUndoController',           ['only'=>['store']]);
  Route::resource('/tenantUploadAgreement','PropertyManagement\Tenant\Upload\AgreementController',       ['only'=>['index','store','show','edit']]);
  Route::resource('/tenantUploadApplication','PropertyManagement\Tenant\Upload\ApplicationController',   ['only'=>['index','store','show','update']]);
  Route::resource('/tenantMoveOutProcess', 'PropertyManagement\TenantMoveOutProcess\TenantMoveOutProcessController', ['only'=>['index', 'edit', 'update']]);
  Route::resource('/tenantMoveOutProcessExport', 'PropertyManagement\TenantMoveOutProcess\ExportCsv\TenantMoveOutProcessExportController', ['only' => ['index']]);
  Route::resource('/uploadMoveOutReport', 'PropertyManagement\TenantMoveOutProcess\Upload\MoveOutReportController', ['only'=>['index', 'show']]);
  Route::resource('/uploadMoveOutFile',   'PropertyManagement\TenantMoveOutProcess\Upload\MoveOutFileController',   ['only'=>['index', 'edit', 'store', 'show', 'destroy']]);
  Route::resource('/tenantDepositRefund',  'PropertyManagement\TenantMoveOutProcess\TenantDepositRefund\TenantDepositRefundController', ['only'=>['store', 'edit']]);
  Route::resource('/tenantDepositRefundUndo',  'PropertyManagement\TenantMoveOutProcess\TenantDepositRefundUndo\TenantDepositRefundUndoController', ['only'=>['store']]);
  Route::resource('/tenantRemark',    'PropertyManagement\Tenant\TenantRemark\TenantRemarkController',   ['only'=>['edit', 'update', 'create', 'store', 'destroy']]);
  Route::resource('/tenantEvictionProcess', 'PropertyManagement\TenantEvictionProcess\TenantEvictionProcessController', ['only'=>['index', 'create', 'edit', 'update', 'destroy']]);
  Route::resource('/tenantEvictionProcessExport', 'PropertyManagement\TenantEvictionProcess\ExportCsv\TenantEvictionProcessExportController', ['only' => ['index']]);
  Route::resource('/tenantEvictionProcessReport', 'PropertyManagement\TenantEvictionProcess\Report\ReportController',       ['only'=>['index', 'create']]);
  Route::resource('/tenantEvictionEvent', 'PropertyManagement\TenantEvictionProcess\TenantEvictionEvent\TenantEvictionEventController', ['only'=>['edit', 'update', 'create', 'store']]);
  Route::resource('/uploadTenantEvictionEvent', 'PropertyManagement\TenantEvictionProcess\Upload\UploadTenantEvictionEventController', ['only'=>['index', 'show', 'store','destroy']]);
  
  ### REPORT SECTION ###
  Route::resource('/report',             'Report\ReportController',                                  ['only'=>['index']]);
  Route::resource('/cashRecReport',      'Report\CashRecReport\CashRecReportController',             ['only'=>['index', 'create']]);
  Route::resource('/delinquencyReport',  'Report\DelinquencyReport\DelinquencyReportController',     ['only'=>['index', 'create']]);
  Route::resource('/evictionReport',     'Report\EvictionReport\EvictionReportController',           ['only'=>['index', 'create']]);
  Route::resource('/managerMoveinReport','Report\ManagerMoveinReport\ManagerMoveinReportController', ['only'=>['index', 'create']]);
  Route::resource('/rentRollReport',     'Report\RentRollReport\RentRollReportController',           ['only'=>['index', 'create']]);
  Route::resource('/tenantBalanceReport','Report\TenantBalanceReport\TenantBalanceReportController', ['only'=>['index', 'create']]);
  Route::resource('/vacancyReport',      'Report\VacancyReport\VacancyReportController',             ['only'=>['index', 'create']]);
  Route::resource('/supervisorReport',   'Report\SupervisorReport\SupervisorReportController',       ['only'=>['index', 'create']]);
  Route::resource('/lateFeeReport',      'Report\LateFeeReport\LateFeeReportController',             ['only'=>['index', 'create']]);
  Route::resource('/violationReport',    'Report\ViolationReport\ViolationReportController',         ['only'=>['index', 'create']]);
  Route::resource('/tenantStatusReport', 'Report\TenantStatusReport\TenantStatusReportController',   ['only'=>['index', 'create']]);
  Route::resource('/moveOutReport',      'Report\MoveOutReport\MoveOutReportController',             ['only'=>['index', 'create']]);
  Route::resource('/section8Report',     'Report\Section8Report\Section8ReportController',           ['only'=>['index', 'create']]);
  Route::resource('/generalLedgerReport','Report\GeneralLedgerReport\GeneralLedgerReportController',                     ['only'=>['index', 'create']]);
  Route::resource('/trailBalanceReport', 'Report\TrailBalanceReport\TrailBalanceReportController',                     ['only'=>['index', 'create']]);
  Route::resource('/fundTransferReport', 'Report\FundTransferReport\FundTransferReportController',   ['only'=>['index', 'create']]);
  Route::resource('/checkRegisterReport','Report\CheckRegisterReport\CheckRegisterReportController', ['only'=>['index','create']]);
  Route::resource('/rentRaiseSummaryReport','Report\RentRaiseSummaryReport\RentRaiseSummaryReportController',['only'=>['index','create']]);
  Route::resource('/readyMoveInReport',  'Report\ReadyMoveInReport\ReadyMoveInReportController',     ['only'=>['index','create']]);
  Route::resource('/lateChargeReport',   'Report\LateChargeReport\LateChargeReportController',       ['only'=>['index','create']]);
  Route::resource('/tenantAmountOwedReport','Report\TenantAmountOwedReport\TenantAmountOwedReportController', ['only'=>['index','create']]);
  
  ### ACCOUNTING REPORT ###
  Route::resource('/accountingReport',      'Report\AccountingReport\AccountingReportController',   ['only'=>['index', 'create', 'store', 'edit', 'update', 'destroy']]);
  Route::resource('/accountingReportGroup', 'Report\AccountingReport\AccountingReportGroup\AccountingReportGroupController', ['only'=>['create', 'store', 'edit', 'update', 'destroy']]);
  Route::resource('/accountingReportList',  'Report\AccountingReport\AccountingReportList\AccountingReportListController',   ['only'=>['create', 'store', 'edit', 'update', 'destroy']]); 
  Route::resource('/dragDropGroup',         'Report\AccountingReport\DragDropGroup\DragDropGroupController',   ['only'=>['update']]);
  Route::resource('/dragDropList',          'Report\AccountingReport\DragDropList\DragDropListController',   ['only'=>['update']]);
  Route::resource('/accountingReportDefaultTemplate',  'Report\AccountingReport\AccountingReportDefaultTemplate\AccountingReportDefaultTemplateController',   ['only'=>['index', 'create', 'store', 'edit', 'show']]);
  Route::resource('/balanceSheetReport',    'Report\AccountingReport\BalanceSheetReport\BalanceSheetReportController',  ['only'=>['index','store','edit','show']]);
  Route::resource('/operatingStatementReport', 'Report\AccountingReport\OperatingStatementReport\OperatingStatementReportController', ['only'=>['index','store','edit','show']]);

  ### DASHBOARD SECTION ### 
  Route::resource('/dashboard',          'Dashboard\DashboardController',                            ['only'=>['index']]);
  
  ### ACCOUNT PAYABLE SECTION ###
  Route::resource('/accountPayableBankInfo',  'AccountPayable\Autocomplete\BankInfoController',      ['only'=>['index']]);
  
  /// DEBIT EXPENSE TRANSACTION
  Route::resource('/debitExpenseTransaction',       'AccountPayable\DebitExpenseTransaction\DebitExpenseTransactionController',  ['only'=>['index', 'create', 'store']]);
  Route::resource('/debitExpenseTransactionUpload', 'AccountPayable\DebitExpenseTransaction\DebitExpenseTransactionUpload\DebitExpenseTransactionUploadController',  ['only'=>['create', 'store']]);
  /// FUNDS TRANSFER ///
  Route::resource('/fundsTransfer',   'AccountPayable\FundsTransfer\FundsTransferController',        ['only'=>['index','create','store']]);
  
  /// INSURANCE SECTION ///
  Route::resource('/insurance',       'AccountPayable\Insurance\InsuranceController',              ['only' => ['index', 'edit', 'update', 'create', 'store', 'destroy']]);
  Route::resource('/uploadInsurance', 'AccountPayable\Insurance\Upload\UploadInsuranceController', ['only' => ['edit', 'store', 'show', 'destroy']]);
  Route::resource('/autoBankInfo',    'AccountPayable\Insurance\Autocomplete\AutoBankInfoController', ['only'=>['index']]);	
  Route::resource('/insuranceExport', 'AccountPayable\Insurance\ExportCsv\InsuranceExportController', ['only' => ['index']]);	
  Route::resource('/approveInsurance','AccountPayable\Insurance\SubmitToApproval\SubmitToApprovalInsuranceController', ['only'=>['create', 'store']]);
  Route::resource('/insuranceUpload', 'AccountPayable\Insurance\InsuranceUpload\InsuranceUploadController', ['only'=>['index','create','store']]);
  
  /// VENDORS SECTION ///
  Route::resource('/vendors',        'AccountPayable\Vendors\VendorsController',              ['only' => ['index', 'edit', 'update', 'create', 'store','destroy']]);
  Route::resource('/uploadVendors',  'AccountPayable\Vendors\Upload\UploadVendorsController', ['only' => ['edit', 'store', 'show', 'destroy']]);
  Route::resource('/autoBankInfo',    'AccountPayable\Insurance\Autocomplete\AutoBankInfoController', ['only'=>['index']]);	
  Route::resource('/insuranceExport', 'AccountPayable\Insurance\ExportCsv\InsuranceExportController', ['only' => ['index']]);	
  Route::resource('/approveInsurance','AccountPayable\Insurance\SubmitToApproval\SubmitToApprovalInsuranceController', ['only'=>['create', 'store']]);
  /// PENDING CHECK SECTION ///
  Route::resource('/pendingCheck',       'AccountPayable\PendingCheck\PendingCheckController',              ['only' => ['index', 'edit', 'update', 'create', 'store', 'destroy']]);
  Route::resource('/uploadPendingCheck', 'AccountPayable\PendingCheck\Upload\UploadPendingCheckController', ['only' => ['index', 'edit', 'store', 'show', 'destroy']]);
  Route::resource('/approvePendingCheck','AccountPayable\PendingCheck\SubmitToApproval\SubmitToApprovalPendingCheckController', ['only'=>['index','store']]);
  Route::resource('/pendingCheckUpload', 'AccountPayable\PendingCheck\PendingCheckUpload\PendingCheckUploadController',         ['only'=>['index','create','store']]);
  /// PROP TAX SECTION ///
  Route::resource('/propTax',       'AccountPayable\PropTax\PropTaxController',              ['only' => ['index', 'edit', 'update', 'create', 'store', 'destroy']]);
  Route::resource('/uploadPropTax', 'AccountPayable\PropTax\Upload\UploadPropTaxController', ['only' => ['edit', 'store', 'show', 'destroy']]);
  Route::resource('/approvePropTax','AccountPayable\PropTax\SubmitToApproval\SubmitToApprovalPropTaxController', ['only'=>['create', 'store']]);
  Route::resource('/propTaxExport', 'AccountPayable\PropTax\ExportCsv\PropTaxExportController', ['only' => ['index']]);
  /// UTIL PAYMENT SECTION ///
  Route::resource('/utilPayment',       'AccountPayable\UtilPayment\UtilPaymentController',              ['only' => ['index', 'edit', 'update', 'create', 'store']]);
  Route::resource('/uploadUtilPayment', 'AccountPayable\UtilPayment\Upload\UploadUtilPaymentController', ['only' => ['edit', 'store', 'show', 'destroy']]);
  /// VENDOR BUSINESS LICENSE SECTION ///
  Route::resource('/businessLicense',         'AccountPayable\BusinessLicense\BusinessLicenseController', ['only'=>['index','create','store','edit','update','destroy']]);
  Route::resource('/uploadBusinessLicense',   'AccountPayable\BusinessLicense\Upload\UploadBusinessLicenseController', ['only'=>['index','edit','store','update','show','update','destroy']]);
  Route::resource('/businessLicenseStorePayment','AccountPayable\BusinessLicense\StorePayment\BusinessLicenseStorePaymentController',['only'=>['create','store']]);
  Route::resource('/businessLicenseExport',   'AccountPayable\BusinessLicense\ExportCsv\BusinessLicenseExportController',            ['only'=>['index']]);
  
  /// MAINTENANCE SECTION ///
  Route::resource('/maintenance',             'AccountPayable\Maintenance\MaintenanceController',         ['only'=>['index','create','store','edit','update','destroy']]);
  Route::resource('/uploadMaintenance',       'AccountPayable\Maintenance\Upload\UploadMaintenanceController',['only'=>['index','edit','store','update','show','destroy']]);
  Route::resource('/approveMaintenance',      'AccountPayable\Maintenance\SubmitToApproval\SubmitToApprovalMaintenanceController',['only'=>['create','store']]);
  Route::resource('/maintenanceResetControlUnit','AccountPayable\Maintenance\ResetControlUnit\MaintenanceResetControlUnitController',['only'=>['create','store']]);
  
  /// MANAGEMENT FEE SECTION ///
  Route::resource('/managementFee',           'AccountPayable\ManagementFee\ManagementFeeController',     ['only'=> ['index','create','store','edit','update','destroy']]);
  Route::resource('/approveManagementFee',    'AccountPayable\ManagementFee\SubmitToApproval\SubmitToApprovalManagementFeeController', ['only'=>['index','create','store']]);
  Route::resource('/managementFeeExport',     'AccountPayable\ManagementFee\ExportCsv\ManagementFeeExportController',  ['only'=>['index']]);
  
  ### UTIL PAYMENT SECTION ###
  Route::resource('/utilPayment',       'AccountPayable\UtilPayment\UtilPaymentController',              ['only' => ['index','create','store','edit','update','destroy']]);
  Route::resource('/uploadUtilPayment', 'AccountPayable\UtilPayment\Upload\UploadUtilPaymentController', ['only' => ['edit', 'store', 'show', 'destroy']]);
  Route::resource('/utilPaymentStorePayment', 'AccountPayable\UtilPayment\StorePayment\UtilPaymentStorePaymentController', ['only'=>['index','create','store']]);
  
  /// VENDOR GARDEN HOA SECTION ///
  Route::resource('/gardenHoa',               'AccountPayable\GardenHoa\GardenHoaController',        ['only'=>['index','create','store','edit','update','destroy']]);
  Route::resource('/uploadGardenHoa',         'AccountPayable\GardenHoa\Upload\UploadGardenHoaController', ['only'=>['index','edit','update','show','store','destroy']]);
  Route::resource('/gardenHoaExport',         'AccountPayable\GardenHoa\ExportCsv\GardenHoaExportController', ['only'=>['index']]);
  Route::resource('/submitGardenHoa',         'AccountPayable\GardenHoa\SubmitToApproval\SubmitToApprovalGardenHoaController', ['only'=>['index','create','store']]);
  
  /// VENDOR MORTGAGE SECTION ///
  Route::resource('/mortgage',                'AccountPayable\Mortgage\MortgageController',          ['only'=>['index','create','store','edit','update','destroy']]);
  Route::resource('/uploadMortgage',          'AccountPayable\Mortgage\Upload\UploadMortgageController', ['only'=>['index','edit','update','show','store','destroy']]);
  Route::resource('/mortgageExport',          'AccountPayable\Mortgage\ExportCsv\MortgageExportController', ['only'=>['index']]);
  Route::resource('/approveMortgage',         'AccountPayable\Mortgage\SubmitToApproval\SubmitToApprovalMortgageController', ['only'=>['create','store']]);
  Route::resource('/mortgageUpload',          'AccountPayable\Mortgage\MortgageUpload\MortgageUploadController',             ['only'=>['index','create','store']]);
  
  /// APPROVAL SECTION ///
  Route::resource('/approval',                'AccountPayable\Approval\ApprovalController',               ['only'=>['index','create','store','edit','update','destroy']]);
  Route::resource('/uploadApproval',          'AccountPayable\Approval\Upload\UploadApprovalController',  ['only'=>['index','edit','update','show','store','destroy']]);
  Route::resource('/approveOrReject',         'AccountPayable\Approval\ApproveOrReject\ApproveOrRejectController',  ['only'=>['create','store']]);
  Route::resource('/requestApproval',         'AccountPayable\Approval\RequestApproval\RequestApprovalController',  ['only'=>['create','store']]);
  Route::resource('/printCheck',              'AccountPayable\Approval\PrintCheck\PrintCheckController',  ['only'=>['create','store']]);
  Route::resource('/record',                  'AccountPayable\Approval\Record\RecordController', ['only'=>['create','store']]);
  Route::resource('/printCashierCheck',       'AccountPayable\Approval\PrintCashierCheck\PrintCashierCheckController',['only'=>['create','store']]);
  Route::resource('/approvalRequestDropdown', 'AccountPayable\Approval\ApprovalRequestDropdown\ApprovalRequestDropdownController', ['only'=>['index']]);
//  Route::resource('/recordApproval',          'AccountPayable\Approval\ ',  ['only'=>['create','store']]);
  
  /// APPROVAL HISTORY SECTION ///
  Route::resource('/approvalHistory',         'AccountPayable\ApprovalHistory\ApprovalHistoryController', ['only'=>['index']]);	
  Route::resource('/uploadApprovalHistory',   'AccountPayable\ApprovalHistory\Upload\UploadApprovalHistoryController', ['only'=>['index','show']]);
  Route::resource('/approvalHistoryExport',   'AccountPayable\ApprovalHistory\ExportCsv\ApprovalHistoryExportController', ['only'=>['index']]);
  
  /// VOID CHECK SECTION ///
  Route::resource('/voidCheck',               'AccountPayable\VoidCheck\VoidCheckController', ['only'=>['index', 'show', 'destroy']]);
  
  /// JOURNAL ENTRY ///
  Route::resource('/journalEntry',            'AccountPayable\JournalEntry\JournalEntryController', ['only'=>['index', 'store']]);
  
  ### ACCOUNT RECEIVABLE SECTION ### 
  Route::resource('/cashRec',            'AccountReceivable\CashRec\CashRecController',                     ['only'=>['index']]);
  Route::resource('/ledgerCard',         'AccountReceivable\CashRec\LedgerCard\LedgerCardController',       ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/postInvoice',        'AccountReceivable\CashRec\PostInvoice\PostInvoiceController',     ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/postPayment',        'AccountReceivable\CashRec\PostPayment\PostPaymentController',     ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/bankInfo',           'AccountReceivable\CashRec\Autocomplete\BankInfoController',       ['only'=>['index']]);
  Route::resource('/tenantInfo',         'AccountReceivable\CashRec\Autocomplete\TenantInfoController',     ['only'=>['index']]);
  Route::resource('/ledgerCardFix',      'AccountReceivable\CashRec\LedgerCardFix\LedgerCardFixController', ['only'=>['index', 'show', 'store']]);
  Route::resource('/ledgerCardExport',   'AccountReceivable\CashRec\LedgerCard\LedgerCardExportController', ['only'=>['index']]);
  Route::resource('/exportLedgerCard',   'AccountReceivable\CashRec\LedgerCard\ExportLedgerCardController', ['only'=>['index']]);
  Route::resource('/depositRefund',      'AccountReceivable\CashRec\DepositRefund\DepositRefundController', ['only'=>['create', 'store']]);
  Route::resource('/depositCheck',       'AccountReceivable\CashRec\DepositCheck\DepositCheckController',   ['only'=>['create', 'store']]);
  Route::resource('/rpsView',            'AccountReceivable\CashRec\RpsView\RpsViewController',                       ['only'=>['index']]);
  Route::resource('/rpsCheckOnly',       'AccountReceivable\CashRec\RpsCheckOnly\RpsCheckOnlyController',             ['only'=>['store', 'create', 'destroy']]);
  Route::resource('/rpsCreditCheck',     'AccountReceivable\CashRec\RpsCreditCheck\RpsCreditCheckController',         ['only'=>['store', 'create', 'destroy']]);
  Route::resource('/rpsTenantStatement', 'AccountReceivable\CashRec\RpsTenantStatement\RpsTenantStatementController', ['only'=>['store', 'create', 'destroy']]);
  Route::resource('/rpsDeleteFile',      'AccountReceivable\CashRec\RpsDeleteFile\RpsDeleteFileController',           ['only'=>['store', 'create', 'destroy']]);
  Route::resource('/paymentUpload',      'AccountReceivable\CashRec\PaymentUpload\PaymentUploadController',           ['only'=>['store', 'create', 'destroy']]);
  Route::resource('/invoiceUpload',      'AccountReceivable\CashRec\InvoiceUpload\InvoiceUploadController',           ['only'=>['store','create','destroy']]);
  Route::resource('/depositUpload',      'AccountReceivable\CashRec\DepositUpload\DepositUploadController', ['only'=>['store','create','destroy']]);
  // THIS ROUTE FOR RPA TENANT SCAN
  Route::resource('/tenantStatement',    'AccountReceivable\CashRec\TenantStatement\TenantStatementController',       ['only'=>['index', 'edit', 'store', 'update', 'show', 'create']]);
  Route::resource('/batchDelete',        'AccountReceivable\BatchDelete\BatchDeleteController',                       ['only'=>['index', 'show', 'destroy']]);
  
  /// GL CHART SECTION ///
  Route::resource('/glchart',            'AccountReceivable\GlChart\GlChartController',              ['only' => ['index', 'edit', 'update', 'create', 'store', 'destroy']]);
  Route::resource('/glChartReport',      'AccountReceivable\GlChart\Report\ReportController',        ['only'=>['index']]);
  
  /// SERVICE SECTION ///
  Route::resource('/service',            'AccountReceivable\Service\ServiceController',              ['only' => ['index', 'edit', 'update', 'create', 'store', 'destroy']]);
  Route::resource('/serviceReport',      'AccountReceivable\Service\Report\ReportController',        ['only' => ['index']]);

  ### BANK RECONCILIATION SECTION ###
  Route::resource('/trustBankInfo',      'BankRec\Autocomplete\TrustBankInfoController',             ['only' => ['index']]);
  Route::resource('/bankRec',            'BankRec\BankRecController',                                ['only' => ['index', 'edit', 'update', 'create', 'store', 'destroy']]);
  Route::resource('/clearCompleteTrans', 'BankRec\ClearCompleteTransaction\ClearCompleteTransactionController', ['only'=>['index','edit','update','create','store','destroy']]);
  Route::resource('/clearedTrans',       'BankRec\ClearedTransaction\ClearedTransactionController',  ['only' => ['index']]);
  Route::resource('/bankTrans',          'BankRec\BankTransaction\BankTransactionController',        ['only' => ['index']]);
});

Route::group(['middleware' => ['AlreadyLogin']], function () {
  Route::resource('/',              'Account\LoginController',                ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/login',         'Account\LoginController',                ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
  Route::resource('/register',      'Account\RegisterController',             ['only'=>['index', 'edit', 'store', 'update', 'show', 'create', 'destroy']]);
});

Route::resource('/tenantLogin',   'Account\TenantLoginController',     ['only'=>['index','store','edit','update']]);
Route::resource('/signAgreement',     'CreditCheck\Agreement\SignAgreementController', ['only'=>['index','edit','update','store']]);
Route::resource('/passwordReset', 'Account\PasswordResetController',       ['only'=>['index', 'store', 'edit', 'update']]);
Route::resource('/logout',        'Account\LogoutController',               ['only'=>['index']]);


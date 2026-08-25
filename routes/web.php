<?php


use App\Http\Controllers\BranchController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ServicePointController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StaffCategoryController;
use App\Http\Controllers\SupplierIndustryController;
use App\Http\Controllers\SupplierSubCategoryController;
use App\Http\Controllers\ItemUnitController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemBulkUploadController;
use App\Http\Controllers\PackageBulkUploadController;
use App\Http\Controllers\ItemImportanceCategoryController;
use App\Http\Controllers\PatientCategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ContractorProfileController;
use App\Http\Controllers\ContractorProfileBulkUploadController;
use App\Http\Controllers\InsuranceCompanyController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\SubGroupController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BulkUploadController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompletedClientsController;
use App\Http\Controllers\DailyVisitsController;
use App\Http\Controllers\VisitArchivesController;
use App\Http\Controllers\ServiceChargeController;
use App\Http\Controllers\ContractorServiceChargeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\LocalPaymentController;

use App\Http\Controllers\PackageTrackingController;
use App\Http\Controllers\PackageSalesController;
use App\Http\Controllers\BalanceHistoryController;
use App\Http\Controllers\BusinessBalanceHistoryController;
use App\Http\Controllers\ContractorBalanceHistoryController;
use App\Http\Controllers\ServiceDeliveryController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\MoneyTrackingController;
use App\Http\Controllers\SuspenseAccountController;
use App\Http\Controllers\PaymentReviewController;
use App\Http\Controllers\ServiceQueueController;
use App\Http\Controllers\ServiceDeliveryQueueController;
use App\Http\Controllers\TestingController;
use App\Http\Controllers\AutomatedTestController;
use App\Http\Controllers\MaturationPeriodController;
use App\Http\Controllers\ServiceChargeMaturationPeriodController;
use App\Http\Controllers\PaymentMethodAccountController;
use App\Http\Controllers\InventoryModuleConfigController;
use App\Http\Controllers\InventoryContextController;
use App\Http\Controllers\GoodsReceivedNoteController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryFulfillmentController;
use App\Http\Controllers\InventoryApprovedPoolController;
use App\Http\Controllers\InventoryRecordUsageController;
use App\Http\Controllers\InventoryCrashCartController;
use App\Http\Controllers\InventoryPickRouteController;
use App\Http\Controllers\InventoryInternalReplenishmentController;
use App\Http\Controllers\InventorySettingsController;
use App\Http\Controllers\InventoryDailyConsumptionController;
use App\Http\Controllers\InventoryOrderController;
use App\Http\Controllers\InventoryIncomingRfqController;
use App\Http\Controllers\InventorySuppliedQuotationController;
use App\Http\Controllers\InventoryPurchaseOrderController;
use App\Http\Controllers\InventorySupplierQuotationController;
use App\Http\Controllers\InventoryStockTransferController;
use App\Http\Controllers\InventoryGoodsReturnController;
use App\Http\Controllers\InventoryReportsController;
use App\Http\Controllers\InventoryStockCountController;
use App\Http\Controllers\InventoryEscrowController;
use App\Http\Controllers\BankScheduleController;
use App\Http\Controllers\WithdrawalSettingController;
use App\Http\Controllers\BusinessWithdrawalSettingController;
use App\Http\Controllers\CashTraySettingsController;
use App\Http\Controllers\ClinicalModuleSettingsController;
use App\Http\Controllers\WithdrawalRequestController;
use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\ThirdPartyPayerController;
use App\Http\Controllers\CreditNoteWorkflowController;
use App\Http\Controllers\CreditNoteWorkflowBulkUploadController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ServicePointSupervisorController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\CreditLimitChangeRequestController;
use App\Http\Controllers\ThirdPartyPayerDashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;






/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', 'login');


// Third-party payer authentication routes (public)
Route::prefix('third-party-payer')->name('third-party-payer.')->group(function () {
    Route::get('/login', [App\Http\Controllers\ThirdPartyPayerAuth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\ThirdPartyPayerAuth\LoginController::class, 'login']);
    Route::post('/logout', [App\Http\Controllers\ThirdPartyPayerAuth\LoginController::class, 'logout'])->name('logout');
});

// Third-party payer dashboard routes (protected)
Route::middleware(['auth:third_party_payer'])->prefix('third-party-payer-dashboard')->name('third-party-payer-dashboard.')->group(function () {
    Route::get('/', [ThirdPartyPayerDashboardController::class, 'index'])->name('index');
    Route::get('/balance-statement', [ThirdPartyPayerDashboardController::class, 'balanceStatement'])->name('balance-statement');
});

// Cashier authentication routes (public)
Route::prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/login', [App\Http\Controllers\CashierAuth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\CashierAuth\LoginController::class, 'login']);
    Route::post('/logout', [App\Http\Controllers\CashierAuth\LoginController::class, 'logout'])->name('logout');
});

// Cashier dashboard routes (protected)
Route::middleware(['auth', 'cashier'])->prefix('cashier-dashboard')->name('cashier-dashboard.')->group(function () {
    Route::get('/', [App\Http\Controllers\CashierDashboardController::class, 'index'])->name('index');
});

// Route::get("makePayment",[PaymentController::class,"makePayment"])->name("makePayment");    

Route::middleware(['auth', 'verified'])->group(function () {

    // Route for the getting the data feed
    // Route::get('/json-data-feed', [DataFeedController::class, 'getDataFeed'])->name('json_data_feed');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/yo-payment-test', [DashboardController::class, 'testYoPayment'])->name('dashboard.yo-payment-test');
    Route::post('/dashboard/testing-environment-reset', [DashboardController::class, 'clearTestingEnvironment'])
        ->name('dashboard.testing-environment-reset')
        ->middleware('throttle:5,1');

    // Automated Tests
    Route::get('/automated-tests', [AutomatedTestController::class, 'index'])->name('automated-tests.index');
    Route::post('/automated-tests/run', [AutomatedTestController::class, 'run'])->name('automated-tests.run');

    Route::impersonate();



    Route::resource("businesses", BusinessController::class);
    Route::resource("branches", BranchController::class);
    Route::resource("support", SupportController::class);
    Route::resource("transactions", TransactionController::class);
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::get('/accounts-receivable', [AccountsReceivableController::class, 'index'])->name('accounts-receivable.index');
    Route::resource("users", UserController::class);
    Route::resource("roles", RoleController::class);
    Route::resource("departments", DepartmentController::class);
    Route::resource("titles", TitleController::class);
    Route::resource("staff-categories", StaffCategoryController::class)->only(['index']);
    Route::resource("supplier-industries", SupplierIndustryController::class)->only(['index']);
    Route::resource("supplier-sub-categories", SupplierSubCategoryController::class)->only(['index']);
    Route::resource("qualifications", QualificationController::class);
    Route::resource("rooms", RoomController::class);
    Route::resource("service-points", ServicePointController::class);
    Route::resource("service-queues", ServiceQueueController::class)->except(['create', 'store']);
    
    // Additional service queue routes
    Route::post('/service-queues/{serviceQueue}/start', [ServiceQueueController::class, 'startProcessing'])->name('service-queues.start');
    Route::post('/service-queues/{serviceQueue}/complete', [ServiceQueueController::class, 'complete'])->name('service-queues.complete');
    Route::post('/service-queues/{serviceQueue}/cancel', [ServiceQueueController::class, 'cancel'])->name('service-queues.cancel');
    Route::get('/service-queues/service-point/{servicePoint}/stats', [ServiceQueueController::class, 'getStats'])->name('service-queues.stats');
Route::get('/service-queues/service-point/{servicePoint}/queues', [ServiceQueueController::class, 'getServicePointQueues'])->name('service-queues.service-point-queues');

// Service Delivery Queue routes
Route::post('/service-delivery-queues/{serviceDeliveryQueue}/move-to-partially-done', [ServiceDeliveryQueueController::class, 'moveToPartiallyDone'])->name('service-delivery-queues.move-to-partially-done');
Route::post('/service-delivery-queues/{serviceDeliveryQueue}/move-to-completed', [ServiceDeliveryQueueController::class, 'moveToCompleted'])->name('service-delivery-queues.move-to-completed');

// Client Details routes
Route::get('/service-points/{servicePoint}/client/{clientId}/details', [ServicePointController::class, 'clientDetails'])->name('service-points.client-details');
Route::post('/service-points/{servicePoint}/client/{clientId}/update-statuses', [ServicePointController::class, 'updateClientItemStatuses'])->name('service-points.update-client-statuses');
Route::post('/service-points/{servicePoint}/client/{clientId}/update-statuses-and-process', [ServicePointController::class, 'updateStatusesAndProcessMoneyMovements'])->name('service-points.update-statuses-and-process-money');
Route::get('/service-delivery-queues/service-point/{servicePointId}/items', [ServiceDeliveryQueueController::class, 'getServicePointItems'])->name('service-delivery-queues.service-point-items');
Route::get('/service-delivery-queues/service-point/{servicePointId}/pending', [ServiceDeliveryQueueController::class, 'showPendingItems'])->name('service-delivery-queues.pending');
Route::get('/service-delivery-queues/service-point/{servicePointId}/completed', [ServiceDeliveryQueueController::class, 'showCompletedItems'])->name('service-delivery-queues.completed');

// Queue reset routes (for testing)
Route::post('/service-delivery-queues/service-point/{servicePointId}/reset', [ServiceDeliveryQueueController::class, 'resetServicePointQueues'])->name('service-delivery-queues.reset-service-point');
    
    Route::resource("sections", SectionController::class);
    Route::resource("item-units", ItemUnitController::class);
    
    // Items bulk operations (must come BEFORE items resource route)
    // Goods & Services Bulk Upload Routes
Route::get('/items/bulk-upload', [ItemBulkUploadController::class, 'index'])->name('items.bulk-upload');
Route::get('/items/bulk-upload/template', [ItemBulkUploadController::class, 'downloadTemplate'])->name('items.bulk-upload.template');
Route::post('/items/bulk-upload/import', [ItemBulkUploadController::class, 'import'])->name('items.bulk-upload.import');
Route::get('/items/bulk-upload/filtered-data', [ItemBulkUploadController::class, 'getFilteredData'])->name('items.bulk-upload.filtered-data');
Route::get('/items/bulk-upload/validation-guide', function() {
    return view('items.bulk-upload-validation-guide');
})->name('items.bulk-upload.validation-guide');

// Packages & Bulk Items Upload Routes
Route::get('/package-bulk-upload', [PackageBulkUploadController::class, 'index'])->name('package-bulk-upload.index');
Route::get('/package-bulk-upload/template', [PackageBulkUploadController::class, 'downloadTemplate'])->name('package-bulk-upload.template');
Route::post('/package-bulk-upload/import', [PackageBulkUploadController::class, 'import'])->name('package-bulk-upload.import');
    
    Route::get('/items/filtered-data', [ItemController::class, 'getFilteredData'])->name('items.filtered-data');
    Route::get('/items/generate-code', [ItemController::class, 'generateCode'])->name('items.generate-code');
    Route::resource("items", ItemController::class);
    
    Route::resource("groups", GroupController::class);
    Route::resource("patient-categories", PatientCategoryController::class);
    Route::resource("suppliers", SupplierController::class);
    Route::resource("contractor-profiles", ContractorProfileController::class);
    
    // Contractor Profile bulk operations
    Route::get('/contractor-profiles/bulk-upload', [ContractorProfileBulkUploadController::class, 'index'])->name('contractor-profiles.bulk-upload');
    Route::get('/contractor-profiles/bulk-upload/template', [ContractorProfileBulkUploadController::class, 'downloadTemplate'])->name('contractor-profiles.bulk-upload.template');
    Route::post('/contractor-profiles/bulk-upload/import', [ContractorProfileBulkUploadController::class, 'import'])->name('contractor-profiles.bulk-upload.import');
    Route::get('/contractor-profiles/bulk-upload/users', [ContractorProfileBulkUploadController::class, 'getUsers'])->name('contractor-profiles.bulk-upload.users');
    
    // Settings (includes Insurance Companies)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/countries-exchange-rates', [SettingsController::class, 'countriesIndex'])->name('settings.countries.index');

    // Superadmin currency & country management (settings tabs)
    Route::post('/settings/countries', [SettingsController::class, 'storeCountry'])->name('settings.countries.store');
    Route::put('/settings/countries/{country}', [SettingsController::class, 'updateCountry'])->name('settings.countries.update');
    Route::delete('/settings/countries/{country}', [SettingsController::class, 'destroyCountry'])->name('settings.countries.destroy');
    Route::post('/settings/vendor-service-charge-defaults', [SettingsController::class, 'updateVendorServiceChargeDefaults'])
        ->name('settings.vendor-service-charge-defaults.update');
    Route::get('/settings/kashtre', [CashTraySettingsController::class, 'edit'])->name('settings.kashtre.edit');
    Route::put('/settings/kashtre', [CashTraySettingsController::class, 'update'])->name('settings.kashtre.update');
    Route::get('/settings/clinical-module', [ClinicalModuleSettingsController::class, 'edit'])->name('settings.clinical-module.edit');
    Route::put('/settings/clinical-module', [ClinicalModuleSettingsController::class, 'update'])->name('settings.clinical-module.update');

    // Insurance Companies routes (redirect index to settings)
    Route::get('/insurance-companies', function() {
        return redirect()->route('settings.index', ['tab' => 'insurance-companies']);
    })->name('insurance-companies.index');
    Route::get('/insurance-companies/create', [InsuranceCompanyController::class, 'create'])->name('insurance-companies.create');
    Route::post('/insurance-companies', [InsuranceCompanyController::class, 'store'])->name('insurance-companies.store');
    Route::get('/insurance-companies/{insuranceCompany}', [InsuranceCompanyController::class, 'show'])->name('insurance-companies.show');
    Route::resource("stores", StoreController::class);
    Route::resource("item-importance-categories", ItemImportanceCategoryController::class)->only(['index']);
    Route::resource("suppliers", SupplierController::class);
    Route::resource("contractor-profiles", ContractorProfileController::class);
    Route::resource("sub-groups", SubGroupController::class);
    Route::resource("service-charges", ServiceChargeController::class);
    Route::get('/service-charges/get-entities', [ServiceChargeController::class, 'getEntities'])->name('service-charges.get-entities');
    Route::resource("contractor-service-charges", ContractorServiceChargeController::class);
    Route::resource("admins", AdminController::class);
    
    // Maturation Periods Settings (Kashtre only)
    Route::get('maturation-periods', [MaturationPeriodController::class, 'indexLivewire'])->name('maturation-periods.index');
    Route::get('maturation-periods/check-account', [MaturationPeriodController::class, 'checkAccount'])->name('maturation-periods.check-account');
    Route::get('maturation-periods/system-defaults/edit', [MaturationPeriodController::class, 'editSystemDefaults'])->name('maturation-periods.system-defaults.edit');
    Route::put('maturation-periods/system-defaults', [MaturationPeriodController::class, 'updateSystemDefaults'])->name('maturation-periods.system-defaults.update');
    Route::resource("maturation-periods", MaturationPeriodController::class)->except(['index']);
    Route::post("maturation-periods/{maturationPeriod}/toggle-status", [MaturationPeriodController::class, 'toggleStatus'])->name('maturation-periods.toggle-status');

    Route::resource('service-charge-maturation-periods', ServiceChargeMaturationPeriodController::class)->except(['index']);
    Route::post('service-charge-maturation-periods/{service_charge_maturation_period}/toggle-status', [ServiceChargeMaturationPeriodController::class, 'toggleStatus'])
        ->name('service-charge-maturation-periods.toggle-status');

    // Inventory Module Config (Kashtre admin only)
    Route::resource("inventory-module-configs", InventoryModuleConfigController::class);
    Route::post("inventory-module-configs/{inventoryModuleConfig}/toggle-status", [InventoryModuleConfigController::class, 'toggleStatus'])->name('inventory-module-configs.toggle-status');
    Route::post("inventory-module-configs/{inventoryModuleConfig}/enter-inventory", [InventoryModuleConfigController::class, 'enterInventory'])->name('inventory-module-configs.enter-inventory');
    Route::post('inventory-context/exit', [InventoryContextController::class, 'exit'])->name('inventory.context.exit');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/receive', [InventoryController::class, 'receive'])->name('receive');
        Route::get('/receive/create', [GoodsReceivedNoteController::class, 'create'])->name('receive.create');
        Route::get('/receive/bulk-upload', [GoodsReceivedNoteController::class, 'bulkUpload'])->name('receive.bulk-upload');
        Route::get('/receive/bulk-template', [GoodsReceivedNoteController::class, 'downloadBulkTemplate'])->name('receive.bulk-template');
        Route::get('/receive/items-reference', [GoodsReceivedNoteController::class, 'downloadItemsReference'])->name('receive.items-reference');
        Route::post('/receive/bulk-import', [GoodsReceivedNoteController::class, 'bulkImport'])->name('receive.bulk-import');
        Route::get('/receive/catalogue-lines', [GoodsReceivedNoteController::class, 'catalogueLines'])->name('receive.catalogue-lines');
        Route::post('/receive', [GoodsReceivedNoteController::class, 'store'])->name('receive.store');
        Route::get('/receive/{goodsReceivedNote}', [GoodsReceivedNoteController::class, 'show'])->name('receive.show');
        Route::post('/receive/{goodsReceivedNote}/submit', [GoodsReceivedNoteController::class, 'submit'])->name('receive.submit');
        Route::post('/receive/{goodsReceivedNote}/approve', [GoodsReceivedNoteController::class, 'approve'])->name('receive.approve');
        Route::post('/receive/{goodsReceivedNote}/reject', [GoodsReceivedNoteController::class, 'reject'])->name('receive.reject');
        Route::get('/monitor', [InventoryController::class, 'monitor'])->name('monitor');
        Route::get('/monitor/items/{item}/history', [InventoryController::class, 'stockHistory'])->name('monitor.history');
        Route::get('/fulfillment', [InventoryFulfillmentController::class, 'index'])->name('fulfillment.index');
        Route::get('/fulfillment/{fulfillmentLine}/pick-route', [InventoryPickRouteController::class, 'show'])->name('fulfillment.pick-route');
        Route::get('/approved-pool', [InventoryApprovedPoolController::class, 'index'])->name('approved-pool.index');
        Route::get('/usage', [InventoryRecordUsageController::class, 'index'])->name('usage.index');
        Route::get('/usage/{usageEvent}', [InventoryRecordUsageController::class, 'show'])->name('usage.show');
        Route::post('/usage/{usageEvent}/retry-billing', [InventoryRecordUsageController::class, 'retryBilling'])->name('usage.retry-billing');
        Route::post('/usage/{usageEvent}/collect-payment', [InventoryRecordUsageController::class, 'collectPayment'])->name('usage.collect-payment');
        Route::get('/crash-carts', [InventoryCrashCartController::class, 'index'])->name('crash-carts.index');
        Route::post('/crash-carts/{store}/deploy', [InventoryCrashCartController::class, 'deploy'])->name('crash-carts.deploy');
        Route::post('/crash-carts/{store}/reconcile', [InventoryCrashCartController::class, 'reconcile'])->name('crash-carts.reconcile');
        Route::post('/crash-carts/{store}/ready', [InventoryCrashCartController::class, 'ready'])->name('crash-carts.ready');
        Route::get('/replenishment/create', [InventoryInternalReplenishmentController::class, 'create'])->name('replenishment.create');
        Route::post('/replenishment', [InventoryInternalReplenishmentController::class, 'store'])->name('replenishment.store');
        Route::get('/stock-counts', [InventoryStockCountController::class, 'index'])->name('stock-counts.index');
        Route::get('/stock-counts/create', [InventoryStockCountController::class, 'create'])->name('stock-counts.create');
        Route::post('/stock-counts', [InventoryStockCountController::class, 'store'])->name('stock-counts.store');
        Route::get('/stock-counts/{stockCount}', [InventoryStockCountController::class, 'show'])->name('stock-counts.show');
        Route::post('/stock-counts/{stockCount}/submit', [InventoryStockCountController::class, 'submit'])->name('stock-counts.submit');
        Route::post('/stock-counts/{stockCount}/approve', [InventoryStockCountController::class, 'approve'])->name('stock-counts.approve');
        Route::post('/stock-counts/{stockCount}/reject', [InventoryStockCountController::class, 'reject'])->name('stock-counts.reject');
        Route::get('/escrow', [InventoryEscrowController::class, 'index'])->name('escrow.index');
        Route::post('/escrow/write-off', [InventoryEscrowController::class, 'writeOff'])->name('escrow.write-off');
        Route::get('/consumption', [InventoryDailyConsumptionController::class, 'index'])->name('consumption.index');
        Route::get('/consumption/export/excel', [InventoryDailyConsumptionController::class, 'exportExcel'])
            ->name('consumption.export.excel');
        Route::get('/consumption/export/pdf', [InventoryDailyConsumptionController::class, 'exportPdf'])
            ->name('consumption.export.pdf');
        Route::get('/consumption/items/{item}/months/{month}', [InventoryDailyConsumptionController::class, 'showMonth'])
            ->name('consumption.month')
            ->where('month', '[0-9]{4}-[0-9]{2}');
        Route::get('/consumption/items/{item}/days/{date}', [InventoryDailyConsumptionController::class, 'showDay'])
            ->name('consumption.day')
            ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::get('/orders', [InventoryOrderController::class, 'index'])->name('orders.index');
        Route::get('/incoming-rfqs', [InventoryIncomingRfqController::class, 'index'])->name('incoming-rfqs.index');
        Route::get('/incoming-rfqs/{invitation}', [InventoryIncomingRfqController::class, 'show'])->name('incoming-rfqs.show');
        Route::get('/incoming-rfqs/{invitation}/pdf', [InventoryIncomingRfqController::class, 'pdf'])->name('incoming-rfqs.pdf');
        Route::post('/incoming-rfqs/{invitation}/quotation', [InventoryIncomingRfqController::class, 'storeQuotation'])->name('incoming-rfqs.quotation.store');
        Route::get('/supplied-quotations', [InventorySuppliedQuotationController::class, 'index'])->name('supplied-quotations.index');
        Route::get('/supplied-quotations/{quotation}', [InventorySuppliedQuotationController::class, 'show'])->name('supplied-quotations.show');
        Route::get('/orders/how-it-works', [InventoryOrderController::class, 'howItWorks'])->name('orders.how-it-works');
        Route::get('/orders/create', [InventoryOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [InventoryOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [InventoryOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/calculations', [InventoryOrderController::class, 'calculations'])->name('orders.calculations');
        Route::post('/orders/{order}/submit', [InventoryOrderController::class, 'submit'])->name('orders.submit');
        Route::post('/orders/{order}/approve', [InventoryOrderController::class, 'approve'])->name('orders.approve');
        Route::post('/orders/{order}/reject', [InventoryOrderController::class, 'reject'])->name('orders.reject');
        Route::post('/orders/{order}/create-transfer', [InventoryOrderController::class, 'createTransfer'])->name('orders.create-transfer');
        Route::get('/orders/{order}/receive', [InventoryOrderController::class, 'receive'])->name('orders.receive');
        Route::get('/orders/{order}/pdf', [InventoryOrderController::class, 'pdf'])->name('orders.pdf');
        Route::post('/orders/{order}/regenerate', [InventoryOrderController::class, 'regenerate'])->name('orders.regenerate');
        Route::post('/orders/{order}/quotations', [InventorySupplierQuotationController::class, 'store'])->name('orders.quotations.store');
        Route::post('/orders/{order}/rfq-suppliers', [InventorySupplierQuotationController::class, 'invite'])->name('orders.rfq-suppliers.invite');
        Route::get('/orders/{order}/quotations/compare', [InventorySupplierQuotationController::class, 'compare'])->name('orders.quotations.compare');
        Route::post('/orders/{order}/quotations/awards', [InventorySupplierQuotationController::class, 'saveAwards'])->name('orders.quotations.awards.store');
        Route::post('/orders/{order}/quotations/line-comments', [InventorySupplierQuotationController::class, 'saveLineComments'])->name('orders.quotations.line-comments.store');
        Route::post('/orders/{order}/purchase-orders/generate-accepted', [InventoryPurchaseOrderController::class, 'generateAccepted'])->name('orders.purchase-orders.generate-accepted');
        Route::get('/orders/{order}/purchase-orders/preview-awards', [InventoryPurchaseOrderController::class, 'previewFromAwards'])->name('orders.purchase-orders.preview-awards');
        Route::post('/orders/{order}/purchase-orders/generate-awards', [InventoryPurchaseOrderController::class, 'generateFromAwards'])->name('orders.purchase-orders.generate-awards');
        Route::post('/quotations/{quotation}/accept', [InventorySupplierQuotationController::class, 'accept'])->name('quotations.accept');
        Route::post('/quotations/{quotation}/reject', [InventorySupplierQuotationController::class, 'reject'])->name('quotations.reject');
        Route::post('/quotations/{quotation}/purchase-order', [InventoryPurchaseOrderController::class, 'createFromQuotation'])->name('quotations.purchase-order');
        Route::get('/purchase-orders', [InventoryPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/{purchaseOrder}', [InventoryPurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::get('/purchase-orders/{purchaseOrder}/pdf', [InventoryPurchaseOrderController::class, 'pdf'])->name('purchase-orders.pdf');
        Route::post('/purchase-orders/{purchaseOrder}/issue', [InventoryPurchaseOrderController::class, 'issue'])->name('purchase-orders.issue');
        Route::get('/purchase-orders/{purchaseOrder}/receive', [InventoryPurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::get('/settings', [InventorySettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [InventorySettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/approvers', [InventorySettingsController::class, 'updateApprovers'])->name('settings.approvers.update');
        Route::put('/settings/evaluation-committee', [InventorySettingsController::class, 'updateEvaluationCommittee'])->name('settings.evaluation-committee.update');
        Route::put('/settings/capabilities', [InventorySettingsController::class, 'updateCapabilities'])->name('settings.capabilities.update');
        Route::get('/approvers', fn () => redirect()->route('inventory.settings.edit', ['tab' => 'approvers']))->name('approvers');
        Route::put('/approvers', [InventorySettingsController::class, 'updateApprovers'])->name('approvers.update');
        Route::get('/transfers', [InventoryStockTransferController::class, 'index'])->name('transfers.index');
        Route::get('/transfers/create', [InventoryStockTransferController::class, 'create'])->name('transfers.create');
        Route::post('/transfers', [InventoryStockTransferController::class, 'store'])->name('transfers.store');
        Route::get('/transfers/{transfer}', [InventoryStockTransferController::class, 'show'])->name('transfers.show');
        Route::post('/transfers/{transfer}/submit', [InventoryStockTransferController::class, 'submit'])->name('transfers.submit');
        Route::post('/transfers/{transfer}/approve', [InventoryStockTransferController::class, 'approve'])->name('transfers.approve');
        Route::post('/transfers/{transfer}/receive', [InventoryStockTransferController::class, 'receive'])->name('transfers.receive');
        Route::post('/transfers/{transfer}/reject', [InventoryStockTransferController::class, 'reject'])->name('transfers.reject');
        Route::get('/returns', [InventoryGoodsReturnController::class, 'index'])->name('returns.index');
        Route::get('/returns/create', [InventoryGoodsReturnController::class, 'create'])->name('returns.create');
        Route::post('/returns', [InventoryGoodsReturnController::class, 'store'])->name('returns.store');
        Route::get('/returns/{returnNote}', [InventoryGoodsReturnController::class, 'show'])->name('returns.show');
        Route::post('/returns/{returnNote}/submit', [InventoryGoodsReturnController::class, 'submit'])->name('returns.submit');
        Route::get('/reports', [InventoryReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/aging', [InventoryReportsController::class, 'aging'])->name('reports.aging');
        Route::get('/reports/reorder', [InventoryReportsController::class, 'reorder'])->name('reports.reorder');
        Route::get('/reports/valuation', [InventoryReportsController::class, 'valuation'])->name('reports.valuation');
        Route::get('/reports/shrinkage', [InventoryReportsController::class, 'shrinkage'])->name('reports.shrinkage');
        Route::get('/reports/demand', [InventoryReportsController::class, 'demand'])->name('reports.demand');
        Route::get('/reports/classification', [InventoryReportsController::class, 'classification'])->name('reports.classification');
        Route::get('/network', [InventoryController::class, 'network'])->name('network');
    });
    
    // Payment Method Account Transactions
    Route::get("payment-method-accounts/{paymentMethodAccount}/transactions", [PaymentMethodAccountController::class, 'transactions'])->name('payment-method-accounts.transactions');
    Route::get("payment-method-accounts/{paymentMethodAccount}/transactions/{transaction}", [PaymentMethodAccountController::class, 'show'])->name('payment-method-accounts.transactions.show');
    
    // Bank Schedules
    Route::get("bank-schedules", function () {
        return view('bank-schedules.index-livewire');
    })->name('bank-schedules.index');
    Route::resource("bank-schedules", BankScheduleController::class)->only(['show']);
    
    // AJAX helper for fetching branches by business (session auth)
    Route::get('/ajax/branches', function (Request $request) {
        $businessId = $request->query('business_id');
        if (!$businessId) {
            return response()->json([], 400);
        }
        $branches = \App\Models\Branch::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name']);
        return response()->json($branches);
    })->name('ajax.branches');
    
    // Credit Note Workflow Settings (Kashtre only)
    Route::get('credit-note-workflows/bulk-upload', [CreditNoteWorkflowBulkUploadController::class, 'index'])->name('credit-note-workflows.bulk-upload.index');
    Route::get('credit-note-workflows/bulk-upload/template', [CreditNoteWorkflowBulkUploadController::class, 'downloadTemplate'])->name('credit-note-workflows.bulk-upload.template');
    Route::post('credit-note-workflows/bulk-upload/import', [CreditNoteWorkflowBulkUploadController::class, 'import'])->name('credit-note-workflows.bulk-upload.import');

    Route::resource("credit-note-workflows", CreditNoteWorkflowController::class);
    
    // Service Point Supervisors
    Route::resource("service-point-supervisors", ServicePointSupervisorController::class);
    
    // Service Delivery Queue Reassignment (Supervisors only)
    Route::post("service-delivery-queues/{serviceDeliveryQueue}/reassign", [ServiceDeliveryQueueController::class, 'reassignItem'])->name('service-delivery-queues.reassign');
    
    // Withdrawal Settings
    Route::resource("withdrawal-settings", WithdrawalSettingController::class);
    Route::resource("business-withdrawal-settings", BusinessWithdrawalSettingController::class);
    Route::resource("withdrawal-requests", WithdrawalRequestController::class);
    Route::post("withdrawal-requests/{withdrawalRequest}/approve", [WithdrawalRequestController::class, 'approve'])->name('withdrawal-requests.approve');
    Route::post("withdrawal-requests/{withdrawalRequest}/reject", [WithdrawalRequestController::class, 'reject'])->name('withdrawal-requests.reject');
    
    // Business Settings (for all businesses)
    Route::get('business-settings/edit', [BusinessSettingsController::class, 'edit'])->name('business-settings.edit');
    Route::put('business-settings', [BusinessSettingsController::class, 'update'])->name('business-settings.update');
    
    // Third Party Payers
    Route::resource("third-party-payers", ThirdPartyPayerController::class);
    Route::post('/third-party-payers/{thirdPartyPayer}/update-excluded-items', [ThirdPartyPayerController::class, 'updateExcludedItems'])->name('third-party-payers.update-excluded-items');
    
    // Third Party Vendors (for non-Kashtre businesses - shows connected vendors)
    Route::get('/third-party-vendors', [\App\Http\Controllers\ThirdPartyVendorsController::class, 'index'])->name('third-party-vendors.index');
    Route::get('/third-party-vendors/{vendorId}', [\App\Http\Controllers\ThirdPartyVendorsController::class, 'show'])->name('third-party-vendors.show');
    Route::get('/third-party-vendors/{vendorId}/balance-statement', [\App\Http\Controllers\ThirdPartyVendorsController::class, 'balanceStatement'])->name('third-party-vendors.balance-statement');
    Route::post('/third-party-vendors/{vendorId}/block', [\App\Http\Controllers\ThirdPartyVendorsController::class, 'block'])->name('third-party-vendors.block');
    Route::post('/third-party-vendors/{vendorId}/reactivate', [\App\Http\Controllers\ThirdPartyVendorsController::class, 'reactivate'])->name('third-party-vendors.reactivate');
    Route::post('/third-party-vendors/{vendorId}/create-payer', [\App\Http\Controllers\ThirdPartyVendorsController::class, 'createPayer'])->name('third-party-vendors.create-payer');
    
    // Testing routes (Admin only) - Rate limited to prevent abuse
    Route::post('/testing/clear-data', [TestingController::class, 'clearData'])
        ->name('testing.clear-data')
        ->middleware('throttle:5,1'); // Max 5 requests per minute
    
    Route::get('/clients/completed', [CompletedClientsController::class, 'index'])->name('clients.completed');
    Route::get('/clients/{client}/completed-items', [CompletedClientsController::class, 'showCompletedItems'])->name('clients.completed-items');
    Route::post('/clients/search-existing', [ClientController::class, 'searchExistingClient'])->name('clients.search-existing');
    Route::resource("clients", ClientController::class);
    Route::post('/clients/{client}/update-payment-methods', [ClientController::class, 'updatePaymentMethods'])->name('clients.update-payment-methods');
    Route::post('/clients/{client}/update-payment-phone', [ClientController::class, 'updatePaymentPhone'])->name('clients.update-payment-phone');
    Route::post('/clients/{client}/update-services-category', [ClientController::class, 'updateServicesCategory'])->name('clients.update-services-category');
    Route::post('/clients/{client}/update-excluded-items', [ClientController::class, 'updateExcludedItems'])->name('clients.update-excluded-items');
    Route::post('/clients/{client}/admit', [ClientController::class, 'admit'])->name('clients.admit');
    Route::post('/clients/{client}/discharge', [ClientController::class, 'discharge'])->name('clients.discharge');
    
    // Visits
    Route::get('/daily-visits', [DailyVisitsController::class, 'index'])->name('daily-visits.index');
    Route::get('/visit-archives/{recordType}', [VisitArchivesController::class, 'index'])->name('visit-archives.index');
    Route::view('/test-livewire', 'test-livewire')->name('test-livewire');

// Invoice routes
Route::post('/invoices/service-charge', [InvoiceController::class, 'serviceCharge'])->name('invoices.service-charge');
Route::post('/invoices/package-adjustment', [InvoiceController::class, 'calculatePackageAdjustment'])->name('invoices.package-adjustment');
Route::post('/invoices/balance-adjustment', [InvoiceController::class, 'calculateBalanceAdjustment'])->name('invoices.balance-adjustment');
// Local development override for mobile money payments
if (app()->environment('local')) {
    Route::post('/invoices/mobile-money-payment', [LocalPaymentController::class, 'processMobileMoneyPayment'])->name('invoices.mobile-money-payment');
} else {
    Route::post('/invoices/mobile-money-payment', [InvoiceController::class, 'processMobileMoneyPayment'])->name('invoices.mobile-money-payment');
}
Route::post('/invoices/reinitiate-failed-transaction', [InvoiceController::class, 'reinitiateFailedTransaction'])->name('invoices.reinitiate-failed-transaction');
Route::post('/invoices/reinitiate-failed-invoice', [InvoiceController::class, 'reinitiateFailedInvoice'])->name('invoices.reinitiate-failed-invoice');

// Receipt testing route (remove in production)
Route::post('/invoices/{invoice}/send-receipts', [InvoiceController::class, 'sendReceipts'])->name('invoices.send-receipts');
Route::post('/invoices/{invoice}/manually-complete', [InvoiceController::class, 'manuallyCompleteTransaction'])->name('invoices.manually-complete');
Route::get('/test-mail-config', [InvoiceController::class, 'testMail'])->name('test-mail-config');

// Balance Statement Routes
Route::get('/balance-statement', [BalanceHistoryController::class, 'index'])->name('balance-statement.index');
Route::get('/balance-statement/{clientId}', [BalanceHistoryController::class, 'show'])->name('balance-statement.show');
Route::get('/balance-statement/{clientId}/pay-back', [BalanceHistoryController::class, 'showPayBack'])->name('balance-statement.pay-back.show');
Route::get('/balance-statement/{clientId}/pp-entries', [BalanceHistoryController::class, 'getPPEntries'])->name('balance-statement.pp-entries');
Route::post('/balance-statement/{clientId}/pay-back/summary', [BalanceHistoryController::class, 'showPaymentSummary'])->name('balance-statement.pay-back.summary');
Route::post('/balance-statement/{clientId}/pay-back', [BalanceHistoryController::class, 'payBack'])->name('balance-statement.pay-back');

// Business Balance Statement Routes
Route::get('/business-balance-statement', [BusinessBalanceHistoryController::class, 'index'])->name('business-balance-statement.index');
Route::get('/business-balance-statement/{business}', [BusinessBalanceHistoryController::class, 'show'])->name('business-balance-statement.show');

// Kashtre (Super Business) Balance Statement Routes
Route::get('/kashtre-balance-statement', [BusinessBalanceHistoryController::class, 'kashtreStatement'])->name('kashtre-balance-statement.index');
Route::get('/kashtre-balance-statement/show', [BusinessBalanceHistoryController::class, 'kashtreStatementShow'])->name('kashtre-balance-statement.show');

// Contractor Balance Statement Routes
Route::get('/contractor-balance-statement', [ContractorBalanceHistoryController::class, 'index'])->name('contractor-balance-statement.index');
Route::get('/contractor-balance-statement/{contractorProfile}', [ContractorBalanceHistoryController::class, 'show'])->name('contractor-balance-statement.show');

// Contractor Withdrawal Request Routes
Route::get('/contractor-withdrawal-requests/{contractorProfile}', [App\Http\Controllers\ContractorWithdrawalRequestController::class, 'index'])->name('contractor-withdrawal-requests.index');
Route::get('/contractor-withdrawal-requests/create/{contractorProfile}', [App\Http\Controllers\ContractorWithdrawalRequestController::class, 'create'])->name('contractor-withdrawal-requests.create');
Route::post('/contractor-withdrawal-requests/{contractorProfile}', [App\Http\Controllers\ContractorWithdrawalRequestController::class, 'store'])->name('contractor-withdrawal-requests.store');
Route::get('/contractor-withdrawal-requests/show/{contractorWithdrawalRequest}', [App\Http\Controllers\ContractorWithdrawalRequestController::class, 'show'])->name('contractor-withdrawal-requests.show');
Route::post('/contractor-withdrawal-requests/{contractorWithdrawalRequest}/approve', [App\Http\Controllers\ContractorWithdrawalRequestController::class, 'approve'])->name('contractor-withdrawal-requests.approve');
Route::post('/contractor-withdrawal-requests/{contractorWithdrawalRequest}/reject', [App\Http\Controllers\ContractorWithdrawalRequestController::class, 'reject'])->name('contractor-withdrawal-requests.reject');

// Credit Limit Change Request Routes
Route::get('/credit-limit-requests', [CreditLimitChangeRequestController::class, 'index'])->name('credit-limit-requests.index');
Route::get('/credit-limit-requests/create', [CreditLimitChangeRequestController::class, 'create'])->name('credit-limit-requests.create');
Route::post('/credit-limit-requests', [CreditLimitChangeRequestController::class, 'store'])->name('credit-limit-requests.store');
Route::get('/credit-limit-requests/{creditLimitChangeRequest}', [CreditLimitChangeRequestController::class, 'show'])->name('credit-limit-requests.show');
Route::post('/credit-limit-requests/{creditLimitChangeRequest}/approve', [CreditLimitChangeRequestController::class, 'approve'])->name('credit-limit-requests.approve');
Route::post('/credit-limit-requests/{creditLimitChangeRequest}/reject', [CreditLimitChangeRequestController::class, 'reject'])->name('credit-limit-requests.reject');

// Finance - Withdrawal Requests (Kashtre)
Route::get('/finance/withdrawals', function () {
    // Only accessible to Kashtre and authenticated users
    if (!auth()->check()) {
        abort(403);
    }
    return view('finance.withdrawals.index');
})->name('finance.withdrawals.index');
Route::get('/balance-statement/{clientId}/json', [BalanceHistoryController::class, 'getBalanceHistory'])->name('balance-statement.json');

// Temporary route to fix payment_method enum - REMOVE AFTER RUNNING
Route::get('/fix-payment-method-enum', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE business_balance_histories MODIFY COLUMN payment_method ENUM('account_balance', 'mobile_money', 'bank_transfer', 'v_card', 'p_card', 'insurance', 'credit_arrangement') NULL DEFAULT 'mobile_money'");
        return response()->json([
            'success' => true,
            'message' => 'Payment method enum updated successfully! The enum now includes: account_balance, mobile_money, bank_transfer, v_card, p_card, insurance, credit_arrangement'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
})->middleware('auth');

Route::get('/invoices/generate-number', [InvoiceController::class, 'generateInvoiceNumber'])->name('invoices.generate-number');
Route::post('/invoices/generate-invoice-number', [InvoiceController::class, 'generateInvoiceNumber'])->name('invoices.generate-invoice-number');
Route::post('/invoices/{invoice}/generate-quotation', [QuotationController::class, 'generateFromInvoice'])->name('invoices.generate-quotation');
Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
Route::get('/invoices/{invoice}/insurance-authorization', [InvoiceController::class, 'getInsuranceAuthorization'])->name('invoices.insurance-authorization');
Route::post('/invoices/{invoice}/complete-insurance-service-delivery', [InvoiceController::class, 'completeInsuranceServiceDelivery'])->name('invoices.complete-insurance-service-delivery');
Route::patch('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
Route::resource('invoices', InvoiceController::class);

// Quotation routes
Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
Route::patch('/quotations/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('quotations.update-status');
Route::patch('/quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
Route::patch('/quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
Route::resource('quotations', QuotationController::class);

// Package Tracking Routes
Route::get('/package-tracking/dashboard', [PackageTrackingController::class, 'dashboard'])->name('package-tracking.dashboard');
Route::post('/package-tracking/{packageTracking}/use-quantity', [PackageTrackingController::class, 'useQuantity'])->name('package-tracking.use-quantity');
Route::get('/clients/{client}/packages', [PackageTrackingController::class, 'clientPackages'])->name('package-tracking.client-packages');
Route::resource('package-tracking', PackageTrackingController::class)->except(['create', 'store', 'edit', 'update']);

// Package Sales Routes
Route::get('/package-sales/history', [PackageSalesController::class, 'history'])->name('package-sales.history');
Route::get('/package-sales/export', [PackageSalesController::class, 'export'])->name('package-sales.export');
Route::get('/package-sales/stats', [PackageSalesController::class, 'getStats'])->name('package-sales.stats');
Route::resource('package-sales', PackageSalesController::class)->except(['create', 'store', 'edit', 'update']);

// Service Delivery routes
Route::post('/service-delivery/deliver-item', [ServiceDeliveryController::class, 'deliverItem'])->name('service-delivery.deliver-item');
Route::post('/service-delivery/deliver-multiple', [ServiceDeliveryController::class, 'deliverMultipleItems'])->name('service-delivery.deliver-multiple');
Route::get('/service-delivery/pending/{invoice}', [ServiceDeliveryController::class, 'getPendingDelivery'])->name('service-delivery.pending');
Route::get('/service-delivery/statement/{invoice}', [ServiceDeliveryController::class, 'getDeliveryHistory'])->name('service-delivery.statement');

// Money Tracking routes
Route::get('/money-tracking/dashboard', [MoneyTrackingController::class, 'dashboard'])->name('money-tracking.dashboard');
Route::get('/money-tracking/client-account/{client}', [MoneyTrackingController::class, 'getClientAccount'])->name('money-tracking.client-account');

// Payment Review routes (for reviewing third-party payer payments)
Route::get('/payment-reviews', [PaymentReviewController::class, 'index'])->name('payment-reviews.index');
Route::get('/payment-reviews/{id}', [PaymentReviewController::class, 'show'])->name('payment-reviews.show');
Route::post('/payment-reviews/{id}/approve', [PaymentReviewController::class, 'approve'])->name('payment-reviews.approve');
Route::post('/payment-reviews/{id}/reject', [PaymentReviewController::class, 'reject'])->name('payment-reviews.reject');
Route::get('/payment-reviews/{id}/download-proof', [PaymentReviewController::class, 'downloadProof'])->name('payment-reviews.download-proof');
Route::get('/money-tracking/contractor-account/{contractor}', [MoneyTrackingController::class, 'getContractorAccount'])->name('money-tracking.contractor-account');
Route::get('/money-tracking/transfer-statement', [MoneyTrackingController::class, 'getTransferHistory'])->name('money-tracking.transfer-statement');
Route::get('/money-tracking/account-summary', [MoneyTrackingController::class, 'getAccountSummary'])->name('money-tracking.account-summary');
Route::post('/money-tracking/process-refund', [MoneyTrackingController::class, 'processRefund'])->name('money-tracking.process-refund');

// Suspense Accounts routes
Route::get('/suspense-accounts', [SuspenseAccountController::class, 'index'])->name('suspense-accounts.index');
Route::get('/suspense-accounts/{id}', [SuspenseAccountController::class, 'show'])->name('suspense-accounts.show');
Route::get('/suspense-accounts-api/data', [SuspenseAccountController::class, 'getSuspenseAccountsData'])->name('suspense-accounts.data');
    Route::get('/pos/item-selection/{client}', [TransactionController::class, 'itemSelection'])->name('pos.item-selection');
    
    // Payment Responsibility Routes
    Route::get('/payment-responsibility/{client}/pay', [TransactionController::class, 'showPaymentResponsibilityPayment'])->name('payment-responsibility.pay');
    Route::post('/payment-responsibility/{client}/pay', [TransactionController::class, 'processPaymentResponsibilityPayment'])->name('payment-responsibility.process');
    
    // Admin bulk operations
    Route::get('/admins/bulk/template', [AdminController::class, 'downloadTemplate'])->name('admins.bulk.template');
    Route::post('/admins/bulk/upload', [AdminController::class, 'bulkUpload'])->name('admins.bulk.upload');
    
    // Staff bulk operations
    Route::get('/users/bulk/template', [UserController::class, 'downloadTemplate'])->name('users.bulk.template');
    Route::post('/users/bulk/upload', [UserController::class, 'bulkUpload'])->name('users.bulk.upload');
    
    // Business bulk operations
    Route::get('/businesses/bulk/template', [BusinessController::class, 'downloadTemplate'])->name('businesses.bulk.template');
    Route::post('/businesses/bulk/upload', [BusinessController::class, 'bulkUpload'])->name('businesses.bulk.upload');
    
    // Branch bulk operations
    Route::get('/branches/bulk/template', [BranchController::class, 'downloadTemplate'])->name('branches.bulk.template');
    Route::post('/branches/bulk/upload', [BranchController::class, 'bulkUpload'])->name('branches.bulk.upload');
    
    Route::resource("audit-logs", AuditLogController::class);
    Route::post('/select-room', [RoomController::class, 'selectRoom'])->name('room.select');
    Route::post('/select-branch', [BranchController::class, 'selectBranch'])->name('branch.select');

    Route::prefix('bulk-upload')->group(function () {
        Route::get('/template', [BulkUploadController::class, 'generateTemplate'])->name('bulk.upload.template');
        Route::get('/form', [BulkUploadController::class, 'showUploadForm'])->name('bulk.upload.form');
        Route::post('/import-validations', [BulkUploadController::class, 'importTemplate'])->name('bulk.upload.import-validations');
    });

    // Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    // routes/web.php
    // Route::get('/users/{user:uuid}', [UserController::class, 'show']);

    Route::get('/test-mail-view', function () {
        return view('mail.bot'); // 
    });

    // System Test Routes (for comprehensive testing against Exquisite Test Life business)
    Route::prefix('system-tests')->name('system-tests.')->group(function () {
        Route::get('/runner', [\App\Http\Controllers\SystemTestController::class, 'runner'])->name('runner');
        Route::post('/run', [\App\Http\Controllers\SystemTestController::class, 'run'])->name('run');
        Route::get('/results/{testId}', [\App\Http\Controllers\SystemTestController::class, 'results'])->name('results');
    });
});

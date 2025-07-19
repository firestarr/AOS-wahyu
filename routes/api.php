<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\MaterialPlan;

// routes/api.php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Inventory\ItemCategoryController;
use App\Http\Controllers\Api\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Api\Inventory\ItemController;
use App\Http\Controllers\Api\Inventory\WarehouseController;
use App\Http\Controllers\Api\Inventory\WarehouseZoneController;
use App\Http\Controllers\Api\Inventory\WarehouseLocationController;
use App\Http\Controllers\Api\Inventory\ItemBatchController;
use App\Http\Controllers\Api\Inventory\StockTransactionController;
use App\Http\Controllers\Api\Inventory\StockAdjustmentController;
use App\Http\Controllers\Api\Inventory\CycleCountingController;
use App\Http\Controllers\Api\Inventory\ItemStockController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MaterialPlanningController;

// purchase order
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\PurchaseRequisitionController;
use App\Http\Controllers\Api\RequestForQuotationController;
use App\Http\Controllers\Api\VendorQuotationController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\VendorInvoiceController;
use App\Http\Controllers\Api\VendorContractController;
use App\Http\Controllers\Api\VendorEvaluationController;

// Sales Order
use App\Http\Controllers\Api\Sales\CustomerController;
use App\Http\Controllers\Api\Sales\SalesQuotationController;
use App\Http\Controllers\Api\Sales\SalesOrderController;
use App\Http\Controllers\Api\Sales\DeliveryController;
use App\Http\Controllers\Api\Sales\SalesInvoiceController;
use App\Http\Controllers\Api\Sales\SalesReturnController;
use App\Http\Controllers\Api\Sales\CustomerInteractionController;
use App\Http\Controllers\Api\Sales\SalesCommissionController;
use App\Http\Controllers\Api\Sales\SalesForecastController;

// Manufacturing
use App\Http\Controllers\Api\Manufacturing\ProductController;
use App\Http\Controllers\Api\Manufacturing\BOMController;
use App\Http\Controllers\Api\Manufacturing\BOMLineController;
use App\Http\Controllers\Api\Manufacturing\RoutingController;
use App\Http\Controllers\Api\Manufacturing\WorkCenterController;
use App\Http\Controllers\Api\Manufacturing\RoutingOperationController;
use App\Http\Controllers\Api\Manufacturing\WorkOrderController;
use App\Http\Controllers\Api\Manufacturing\WorkOrderOperationController;
use App\Http\Controllers\Api\Manufacturing\ProductionOrderController;
use App\Http\Controllers\Api\Manufacturing\ProductionConsumptionController;
use App\Http\Controllers\Api\Manufacturing\QualityInspectionController;
use App\Http\Controllers\Api\Manufacturing\QualityParameterController;
use App\Http\Controllers\Api\Manufacturing\MaintenanceScheduleController;

//Accounting
use App\Http\Controllers\Api\Accounting\ChartOfAccountController;
use App\Http\Controllers\Api\Accounting\AccountingPeriodController;
use App\Http\Controllers\Api\Accounting\JournalEntryController;
use App\Http\Controllers\Api\Accounting\CustomerReceivableController;
use App\Http\Controllers\Api\Accounting\ReceivablePaymentController;
use App\Http\Controllers\Api\Accounting\VendorPayableController;
use App\Http\Controllers\Api\Accounting\PayablePaymentController;
use App\Http\Controllers\Api\Accounting\TaxTransactionController;
use App\Http\Controllers\Api\Accounting\FixedAssetController;
use App\Http\Controllers\Api\Accounting\AssetDepreciationController;
use App\Http\Controllers\Api\Accounting\BudgetController;
use App\Http\Controllers\Api\Accounting\BankAccountController;
use App\Http\Controllers\Api\Accounting\BankReconciliationController;
use App\Http\Controllers\Api\Accounting\FinancialReportController;
use App\Http\Controllers\Api\Manufacturing\JobTicketController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth Routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/users', [UserController::class, 'index']);

    Route::prefix('items')->group(function () {
        // Existing item routes...
        // Di dalam grup route prefix 'items'
        Route::get('/{id}/prices-in-currencies', 'App\Http\Controllers\Api\Inventory\ItemController@getPricesInCurrencies');
        // Special item filters
        Route::get('/purchasable', 'App\Http\Controllers\Api\Inventory\ItemController@getPurchasableItems');
        Route::get('/sellable', 'App\Http\Controllers\Api\Inventory\ItemController@getSellableItems');

        // Item price routes
        Route::get('/{itemId}/prices', 'App\Http\Controllers\Api\Inventory\ItemPriceController@index');
        Route::post('/{itemId}/prices', 'App\Http\Controllers\Api\Inventory\ItemPriceController@store');
        Route::get('/{itemId}/prices/{id}', 'App\Http\Controllers\Api\Inventory\ItemPriceController@show');
        Route::put('/{itemId}/prices/{id}', 'App\Http\Controllers\Api\Inventory\ItemPriceController@update');
        Route::delete('/{itemId}/prices/{id}', 'App\Http\Controllers\Api\Inventory\ItemPriceController@destroy');
        // Routes for item prices
        Route::get('items/{id}/all-prices', [ItemController::class, 'showAllPrices']);
        Route::get('items/{id}/customer-price-matrix', [ItemController::class, 'customerPriceMatrix']);

        // Price calculation endpoints
        Route::get('/{itemId}/best-purchase-price', 'App\Http\Controllers\Api\Inventory\ItemPriceController@getBestPurchasePrice');
        Route::get('/{itemId}/best-sale-price', 'App\Http\Controllers\Api\Inventory\ItemPriceController@getBestSalePrice');
        Route::get('/{id}/document', 'App\Http\Controllers\Api\Inventory\ItemController@downloadDocument');
        // Update default prices
        Route::put('/{itemId}/default-prices', 'App\Http\Controllers\Api\Inventory\ItemPriceController@updateDefaultPrices');
        Route::get('/{id}/used-in-finished-goods', [ItemController::class, 'getUsedInFinishedGoods']);
    });

    // // Item Routes
    // Route::apiResource('items', ItemController::class);
    // Route::get('/items/stock-status', [ItemController::class, 'stockStatus']);

    // // Category Routes
    // Route::apiResource('item-categories', CategoryController::class);

    // // UOM Routes
    // //Route::apiResource('unit-of-measures', UnitOfMeasureController::class);

    // // Warehouse Routes
    // Route::apiResource('warehouses', WarehouseController::class);
    // Route::apiResource('warehouses.zones', WarehouseZoneController::class);
    // Route::apiResource('warehouses.zones.locations', WarehouseLocationController::class);

    // // Transaction Routes
    // Route::apiResource('stock-transactions', StockTransactionController::class);

    // // Adjustment Routes
    // Route::apiResource('stock-adjustments', StockAdjustmentController::class);
    // Route::patch('/stock-adjustments/{stock_adjustment}/approve', [StockAdjustmentController::class, 'approve']);
    // Route::patch('/stock-adjustments/{stock_adjustment}/cancel', [StockAdjustmentController::class, 'cancel']);

    // // Reports
    // Route::get('/reports/stock', [ReportController::class, 'stockReport']);
    // Route::get('/reports/movement', [ReportController::class, 'movementReport']);
    // Route::get('/reports/adjustment', [ReportController::class, 'adjustmentReport']);
    // Route::get('/reports/valuation', [ReportController::class, 'valuationReport']);

    // Item Category Routes
    Route::get('categories/tree', [ItemCategoryController::class, 'tree']);
    Route::resource('categories', ItemCategoryController::class);

    // Unit of Measure Routes
    Route::resource('uoms', UnitOfMeasureController::class);

    // Item Routes
    Route::get('items/stock-levels', [ItemController::class, 'stockLevelReport']);
    Route::post('items/{id}/update-stock', [ItemController::class, 'updateStock']);
    Route::resource('items', ItemController::class);

    // Warehouse Routes
    Route::get('warehouses/{id}/inventory', [WarehouseController::class, 'inventory']);
    Route::resource('warehouses', WarehouseController::class);

    // Warehouse Zone Routes
    Route::resource('warehouses/{warehouse_id}/zones', WarehouseZoneController::class);

    // Warehouse Location Routes
    Route::get('zones/{zone_id}/locations/{id}/inventory', [WarehouseLocationController::class, 'inventory']);
    Route::resource('zones/{zone_id}/locations', WarehouseLocationController::class);

    // Item Batch Routes
    Route::get('batches/near-expiry/{days?}', [ItemBatchController::class, 'nearExpiry']);
    Route::resource('items/{item_id}/batches', ItemBatchController::class);

    // Stock Transaction Routes
    Route::get('transactions/items/{item_id}/movement', [StockTransactionController::class, 'itemMovement']);
    Route::post('transactions/transfer', [StockTransactionController::class, 'transfer']);
    Route::resource('transactions', StockTransactionController::class);


    // Cycle Counting Routes
    Route::post('cycle-counts/generate', [CycleCountingController::class, 'generateTasks']);
    Route::post('cycle-counts/{id}/submit', [CycleCountingController::class, 'submit']);
    Route::post('cycle-counts/{id}/approve', [CycleCountingController::class, 'approve']);
    Route::post('cycle-counts/{id}/reject', [CycleCountingController::class, 'reject']);
    Route::resource('cycle-counts', CycleCountingController::class);

    // Vendors
    Route::apiResource('vendors', VendorController::class);
    Route::get('vendors/{vendor}/evaluations', [VendorController::class, 'evaluations']);
    Route::get('vendors/{vendor}/purchase-orders', [VendorController::class, 'purchaseOrders']);

    // Purchase Requisitions
    Route::apiResource('purchase-requisitions', PurchaseRequisitionController::class);
    Route::patch('purchase-requisitions/{purchaseRequisition}/status', [PurchaseRequisitionController::class, 'updateStatus']);

    // Request For Quotations
    Route::apiResource('request-for-quotations', RequestForQuotationController::class);
    Route::patch('request-for-quotations/{requestForQuotation}/status', [RequestForQuotationController::class, 'updateStatus']);

    // Vendor Quotations
    Route::apiResource('vendor-quotations', VendorQuotationController::class);
    Route::patch('vendor-quotations/{vendorQuotation}/status', [VendorQuotationController::class, 'updateStatus']);
    Route::post('vendor-quotations/create-from-rfq', [VendorQuotationController::class, 'createFromRFQ']);

    //Vemdor Quotation tambahan
    // Enhanced Vendor Quotations with Multi-Currency Support
    Route::prefix('vendor-quotations')->group(function () {
        // Basic CRUD operations
        Route::get('/', [VendorQuotationController::class, 'index']);
        Route::post('/', [VendorQuotationController::class, 'store']);
        Route::get('/{vendorQuotation}', [VendorQuotationController::class, 'show']);
        Route::put('/{vendorQuotation}', [VendorQuotationController::class, 'update']);
        Route::delete('/{vendorQuotation}', [VendorQuotationController::class, 'destroy']);

        // Status management
        Route::patch('/{vendorQuotation}/status', [VendorQuotationController::class, 'updateStatus']);

        // Multi-currency features
        Route::post('/{vendorQuotation}/convert-currency', [VendorQuotationController::class, 'convertCurrency']);
        Route::get('/compare/in-currency', [VendorQuotationController::class, 'compareInCurrency']);
        Route::get('/available-currencies', [VendorQuotationController::class, 'getAvailableCurrencies']);

        // Export functionality
        Route::get('/export', [VendorQuotationController::class, 'exportToExcel']);
        Route::get('/export/comparison', [VendorQuotationController::class, 'exportComparison']);

        // Template and import
        Route::get('/template/download', [VendorQuotationController::class, 'downloadTemplate']);
        Route::post('/import', [VendorQuotationController::class, 'importFromExcel']);
    });

    // Purchase Orders
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::patch('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus']);
    Route::post('purchase-orders/create-from-quotation', [PurchaseOrderController::class, 'createFromQuotation']);
    // Route untuk outstanding PO
    Route::get('purchase-orders/{purchaseOrder}/outstanding', [PurchaseOrderController::class, 'showOutstanding']);
    Route::get('purchase-orders/outstanding/all', [PurchaseOrderController::class, 'getAllOutstanding']);
    Route::get('purchase-orders/reports/outstanding-items', [PurchaseOrderController::class, 'outstandingItemsReport']);
    // New route for currency conversion
    Route::post('purchase-orders/{purchaseOrder}/convert-currency', [PurchaseOrderController::class, 'convertCurrency']);
    Route::get('purchase-orders/template/download', [PurchaseOrderController::class, 'downloadTemplate']);
    Route::post('purchase-orders/import', [PurchaseOrderController::class, 'importFromExcel']);
    Route::post('purchase-orders/export', [PurchaseOrderController::class, 'exportToExcel']);

    // Goods Receipts
    Route::prefix('goods-receipts')->group(function () {
        Route::get('/', 'App\Http\Controllers\API\GoodsReceiptController@index');
        Route::post('/', 'App\Http\Controllers\API\GoodsReceiptController@store');
        Route::get('/available-items', 'App\Http\Controllers\API\GoodsReceiptController@getAvailableItems');
        Route::get('/{goodsReceipt}', 'App\Http\Controllers\API\GoodsReceiptController@show');
        Route::put('/{goodsReceipt}', 'App\Http\Controllers\API\GoodsReceiptController@update');
        Route::delete('/{goodsReceipt}', 'App\Http\Controllers\API\GoodsReceiptController@destroy');
        Route::post('/{goodsReceipt}/confirm', 'App\Http\Controllers\API\GoodsReceiptController@confirm');
    });
    // Purchase Requisition - Vendor Management Routes
    Route::prefix('purchase-requisitions')->group(function () {
        Route::get('{purchaseRequisition}/vendor-recommendations', [PurchaseRequisitionController::class, 'getVendorRecommendations']);
        Route::post('{purchaseRequisition}/multi-vendor-rfq', [PurchaseRequisitionController::class, 'createMultiVendorRFQ']);
        Route::get('{purchaseRequisition}/procurement-path', [PurchaseRequisitionController::class, 'getProcurementPath']);
    });

    // Purchase Order - Create from PR Routes
    Route::prefix('purchase-orders')->group(function () {
        Route::post('create-from-pr', [PurchaseOrderController::class, 'createFromPR']);
        Route::post('create-split-from-pr', [PurchaseOrderController::class, 'createSplitPOFromPR']);
    });

    // Vendor Invoices
    Route::get('vendor-invoices/uninvoiced-receipts', [VendorInvoiceController::class, 'getUninvoicedReceipts']);
    Route::apiResource('vendor-invoices', VendorInvoiceController::class);
    Route::post('vendor-invoices/{vendorInvoice}/approve', [VendorInvoiceController::class, 'approve']);
    Route::post('vendor-invoices/{vendorInvoice}/pay', [VendorInvoiceController::class, 'pay']);
    Route::patch('vendor-invoices/{vendorInvoice}/status', [VendorInvoiceController::class, 'updateStatus']);

    // Vendor Contracts
    Route::apiResource('vendor-contracts', VendorContractController::class);
    Route::post('vendor-contracts/{vendorContract}/activate', [VendorContractController::class, 'activate']);
    Route::post('vendor-contracts/{vendorContract}/terminate', [VendorContractController::class, 'terminate']);

    // Vendor Evaluations
    Route::apiResource('vendor-evaluations', VendorEvaluationController::class);
    Route::get('vendor-performance', [VendorEvaluationController::class, 'vendorPerformance']);

    // Customer routes
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/{id}', [CustomerController::class, 'show']);
        Route::put('/{id}', [CustomerController::class, 'update']);
        Route::delete('/{id}', [CustomerController::class, 'destroy']);
        Route::get('/{id}/quotations', [CustomerController::class, 'quotations']);
        Route::get('/{id}/orders', [CustomerController::class, 'orders']);
        Route::get('/{id}/invoices', [CustomerController::class, 'invoices']);
    });

    // Sales Quotation routes
    Route::prefix('quotations')->group(function () {
        Route::get('/', [SalesQuotationController::class, 'index']);
        Route::post('/', [SalesQuotationController::class, 'store']);
        Route::get('/{id}', [SalesQuotationController::class, 'show']);
        Route::put('/{id}', [SalesQuotationController::class, 'update']);
        Route::delete('/{id}', [SalesQuotationController::class, 'destroy']);
        Route::post('/{id}/lines', [SalesQuotationController::class, 'addLine']);
        Route::put('/{id}/lines/{lineId}', [SalesQuotationController::class, 'updateLine']);
        Route::delete('/{id}/lines/{lineId}', [SalesQuotationController::class, 'removeLine']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/next-number', [SalesOrderController::class, 'getNextSalesOrderNumber']);
        Route::get('/', [SalesOrderController::class, 'index']);
        Route::post('/', [SalesOrderController::class, 'store']);
        Route::post('/from-quotation', [SalesOrderController::class, 'createFromQuotation']);
        Route::get('/{id}', [SalesOrderController::class, 'show']);
        Route::put('/{id}', [SalesOrderController::class, 'update']);
        Route::delete('/{id}', [SalesOrderController::class, 'destroy']);
        Route::post('/{id}/lines', [SalesOrderController::class, 'addLine']);
        Route::put('/{id}/lines/{lineId}', [SalesOrderController::class, 'updateLine']);
        Route::delete('/{id}/lines/{lineId}', [SalesOrderController::class, 'removeLine']);
        // Excel functionality routes (letakkan sebelum routes dengan parameter {id})
        Route::get('/excel/template', [SalesOrderController::class, 'downloadTemplate']);
        Route::post('/excel/import', [SalesOrderController::class, 'importFromExcel']);
        Route::get('/excel/export', [SalesOrderController::class, 'exportToExcel']);
        // Tax Calculation Features
        Route::post('/preview-tax', [SalesOrderController::class, 'previewTaxCalculation']); // Preview tax before creating
        Route::get('/applicable-taxes', [SalesOrderController::class, 'getApplicableTaxes']); // Get taxes for item+customer
    });

    // Tax Configuration Routes
    Route::group(['prefix' => 'taxes'], function () {
        // Tax Groups and Taxes
        Route::get('/groups', [TaxController::class, 'getTaxGroups']); // Get tax groups with taxes
        Route::get('/', [TaxController::class, 'getTaxes']); // Get all taxes (with type filter)
        Route::post('/', [TaxController::class, 'createTax']); // Create new tax
        
        // Tax Assignment
        Route::post('/assign-item', [TaxController::class, 'assignItemTaxes']); // Assign taxes to item
        Route::post('/assign-customer', [TaxController::class, 'assignCustomerTaxes']); // Assign default taxes to customer
        Route::post('/assign-vendor', [TaxController::class, 'assignVendorTaxes']); // Assign default taxes to vendor
        
        // Tax Information
        Route::get('/item/{itemId}', [TaxController::class, 'getItemTaxes']); // Get item tax configuration
        Route::get('/customer/{customerId}', [TaxController::class, 'getCustomerTaxes']); // Get customer default taxes
    });


    Route::prefix('deliveries')->group(function () {
        Route::get('/', [DeliveryController::class, 'index']);
        Route::post('/', [DeliveryController::class, 'store']);

        // ===== PENTING: Route spesifik HARUS sebelum route dengan {id} =====
        Route::get('outstanding-so', [DeliveryController::class, 'getOutstandingSalesOrders']);
        Route::get('outstanding-items/{soId}', [DeliveryController::class, 'getOutstandingItemsForDelivery']);
        Route::post('from-outstanding', [DeliveryController::class, 'storeFromOutstanding']);

        // Route dengan parameter {id} harus di akhir
        Route::get('/{id}', [DeliveryController::class, 'show']);
        Route::put('/{id}', [DeliveryController::class, 'update']);
        Route::delete('/{id}', [DeliveryController::class, 'destroy']);
        Route::post('/{id}/complete', [DeliveryController::class, 'complete']);
    });

    // Sales Invoice routes
    Route::prefix('invoices')->group(function () {
        Route::get('/', [SalesInvoiceController::class, 'index']);
        Route::post('/', [SalesInvoiceController::class, 'store']);
        Route::post('/from-order', [SalesInvoiceController::class, 'createFromOrder']);
        Route::post('/from-deliveries', [SalesInvoiceController::class, 'createFromDeliveries']);
        Route::delete('/{id}', [SalesInvoiceController::class, 'destroy']);
        Route::get('/{id}/payment-info', [SalesInvoiceController::class, 'paymentInfo']);
        // Add these routes BEFORE the {id} routes to ensure proper route matching
        Route::get('getDeliveriesForInvoicing', [SalesInvoiceController::class, 'getDeliveriesForInvoicing']);
        Route::get('getDeliveryLinesByItem', [SalesInvoiceController::class, 'getDeliveryLinesByItem']);
        // Then the normal {id} routes
        Route::get('/{id}', [SalesInvoiceController::class, 'show']);
        Route::put('/{id}', [SalesInvoiceController::class, 'update']);
    });

    // Sales Return routes
    Route::prefix('returns')->group(function () {
        Route::get('/', [SalesReturnController::class, 'index']);
        Route::post('/', [SalesReturnController::class, 'store']);
        Route::get('/{id}', [SalesReturnController::class, 'show']);
        Route::put('/{id}', [SalesReturnController::class, 'update']);
        Route::delete('/{id}', [SalesReturnController::class, 'destroy']);
        Route::post('/{id}/process', [SalesReturnController::class, 'process']);
    });

    // Customer Interaction routes
    Route::prefix('interactions')->group(function () {
        Route::get('/', [CustomerInteractionController::class, 'index']);
        Route::post('/', [CustomerInteractionController::class, 'store']);
        Route::get('/{id}', [CustomerInteractionController::class, 'show']);
        Route::put('/{id}', [CustomerInteractionController::class, 'update']);
        Route::delete('/{id}', [CustomerInteractionController::class, 'destroy']);
        Route::get('/customer/{customerId}', [CustomerInteractionController::class, 'getCustomerInteractions']);
    });

    // Sales Commission routes
    Route::prefix('commissions')->group(function () {
        Route::get('/', [SalesCommissionController::class, 'index']);
        Route::post('/', [SalesCommissionController::class, 'store']);
        Route::get('/{id}', [SalesCommissionController::class, 'show']);
        Route::put('/{id}', [SalesCommissionController::class, 'update']);
        Route::delete('/{id}', [SalesCommissionController::class, 'destroy']);
        Route::post('/calculate', [SalesCommissionController::class, 'calculateCommissions']);
        Route::get('/sales-person/{salesPersonId}', [SalesCommissionController::class, 'getSalesPersonCommissions']);
        Route::post('/mark-as-paid', [SalesCommissionController::class, 'markAsPaid']);
    });

    // Sales Forecast routes
    Route::prefix('forecasts')->group(function () {
        // First define the specific named routes
        Route::get('/accuracy', [SalesForecastController::class, 'getForecastAccuracy']);
        Route::get('/consolidated', [SalesForecastController::class, 'getConsolidatedForecast']);
        Route::get('/history', [SalesForecastController::class, 'getForecastHistory']);
        Route::post('/import', [SalesForecastController::class, 'importCustomerForecasts']);
        Route::post('/generate', [SalesForecastController::class, 'generateForecasts']);
        Route::post('/update-actuals', [SalesForecastController::class, 'updateActuals']);
        Route::get('/trend', [SalesForecastController::class, 'getForecastTrend']);
        Route::get('/volatility-summary', [SalesForecastController::class, 'getVolatilitySummary']);

        // Then define the generic routes
        Route::get('/', [SalesForecastController::class, 'index']);
        Route::post('/', [SalesForecastController::class, 'store']);

        // Finally define the parameter routes that will capture anything else
        Route::get('/{id}', [SalesForecastController::class, 'show']);
        Route::put('/{id}', [SalesForecastController::class, 'update']);
        Route::delete('/{id}', [SalesForecastController::class, 'destroy']);
    });

    // AI Excel Forecast Processing Routes
    Route::prefix('ai-excel-forecast')->group(function () {
        Route::post('/process', [App\Http\Controllers\Api\sales\AIExcelForecastController::class, 'processExcelWithAI']);
        Route::post('/save', [App\Http\Controllers\Api\sales\AIExcelForecastController::class, 'saveExtractedForecasts']);
        Route::get('/history', [App\Http\Controllers\Api\sales\AIExcelForecastController::class, 'getProcessingHistory']);
    });

    // Routes untuk Outstanding Sales Order
    Route::get('sales-orders/{id}/outstanding-items', 'Api\Sales\SalesOrderController@getOutstandingItems');
    Route::get('sales-orders/outstanding', 'Api\Sales\SalesOrderController@getAllOutstandingSalesOrders');




    // Routes untuk ItemStock
    Route::get('item-stocks', [ItemStockController::class, 'index']);
    Route::get('item-stocks/item/{itemId}', [ItemStockController::class, 'getItemStock']);
    Route::get('item-stocks/warehouse/{warehouseId}', [ItemStockController::class, 'getWarehouseStock']);
    Route::get('item-stocks/negative', [ItemStockController::class, 'getNegativeStocks']);
    Route::get('item-stocks/negative-stock-summary', [ItemStockController::class, 'getNegativeStockSummary']);
    Route::post('item-stocks/transfer', [ItemStockController::class, 'transferStock']);
    Route::post('item-stocks/adjust', [ItemStockController::class, 'adjustStock']);
    Route::post('item-stocks/reserve', [ItemStockController::class, 'reserveStock']);
    Route::post('item-stocks/release-reservation', [ItemStockController::class, 'releaseReservation']);


    // Routes untuk System Settings
    Route::get('settings', 'Api\Admin\SystemSettingController@index');
    Route::get('settings/group/{group}', 'Api\Admin\SystemSettingController@getByGroup');
    Route::get('settings/inventory', 'Api\Admin\SystemSettingController@getInventorySettings');
    Route::put('settings', 'Api\Admin\SystemSettingController@update');
    Route::put('settings/batch', 'Api\Admin\SystemSettingController@updateMultiple');
    Route::put('settings/inventory', 'Api\Admin\SystemSettingController@updateInventorySettings');

    // Item Category Routes
    Route::prefix('item-categories')->group(function () {
        Route::get('/', [ItemCategoryController::class, 'index']);
        Route::post('/', [ItemCategoryController::class, 'store']);
        Route::get('/{id}', [ItemCategoryController::class, 'show']);
        Route::put('/{id}', [ItemCategoryController::class, 'update']);
        Route::delete('/{id}', [ItemCategoryController::class, 'destroy']);
    });

    // Unit of Measure Routes
    Route::prefix('unit-of-measures')->group(function () {
        Route::get('/', [UnitOfMeasureController::class, 'index']);
        Route::post('/', [UnitOfMeasureController::class, 'store']);
        Route::get('/{id}', [UnitOfMeasureController::class, 'show']);
        Route::put('/{id}', [UnitOfMeasureController::class, 'update']);
        Route::delete('/{id}', [UnitOfMeasureController::class, 'destroy']);
    });

    // Item Routes
    Route::prefix('items')->group(function () {
        Route::get('/', [ItemController::class, 'index']);
        Route::post('/', [ItemController::class, 'store']);
        Route::get('/stock-status', [ItemController::class, 'getStockStatus']);
        Route::get('/{id}', [ItemController::class, 'show']);
        Route::put('/{id}', [ItemController::class, 'update']);
        Route::delete('/{id}', [ItemController::class, 'destroy']);
    });

    // Warehouse Routes
    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index']);
        Route::post('/', [WarehouseController::class, 'store']);
        Route::get('/{id}', [WarehouseController::class, 'show']);
        Route::put('/{id}', [WarehouseController::class, 'update']);
        Route::delete('/{id}', [WarehouseController::class, 'destroy']);
    });

    // Add these routes to your routes/api.php file inside the auth:sanctum middleware group

    // Stock Transaction Routes (Odoo-style)
    Route::prefix('stock-transactions')->group(function () {
        // Basic CRUD
        Route::get('/', [StockTransactionController::class, 'index']);
        Route::post('/', [StockTransactionController::class, 'store']);
        Route::get('/{id}', [StockTransactionController::class, 'show']);
        Route::put('/{id}', [StockTransactionController::class, 'update']);
        Route::delete('/{id}', [StockTransactionController::class, 'destroy']);

        // State management (like Odoo)
        Route::post('/{id}/confirm', [StockTransactionController::class, 'confirm']);
        Route::post('/{id}/cancel', [StockTransactionController::class, 'cancel']);

        // Bulk operations
        Route::post('/bulk-confirm', [StockTransactionController::class, 'bulkConfirm']);
        Route::get('/pending', [StockTransactionController::class, 'getPending']);

        // Transfer operations (simplified like Odoo)
        Route::post('/transfer', [StockTransactionController::class, 'transfer']);

        // Item transactions (new route)
        Route::get('/item/{itemId}', [StockTransactionController::class, 'itemTransactions']);

        // Item movement history
        Route::get('/item/{itemId}/movement', [StockTransactionController::class, 'itemMovement']);

        // Warehouse-specific transactions
        Route::get('/warehouse/{warehouseId}', [StockTransactionController::class, 'getWarehouseTransactions']);
    });

    // Stock Adjustment Routes
    Route::prefix('stock-adjustments')->group(function () {
        Route::get('/', [StockAdjustmentController::class, 'index']);
        Route::post('/', [StockAdjustmentController::class, 'store']);
        Route::get('/{id}', [StockAdjustmentController::class, 'show']);
        Route::patch('/{id}/approve', [StockAdjustmentController::class, 'approve']);
        Route::patch('/{id}/cancel', [StockAdjustmentController::class, 'cancel']);
    });

    // Stock Adjustment Routes
    Route::post('adjustments/{id}/submit', [StockAdjustmentController::class, 'submit']);
    Route::post('adjustments/{id}/approve', [StockAdjustmentController::class, 'approve']);
    Route::post('adjustments/{id}/reject', [StockAdjustmentController::class, 'reject']);
    Route::resource('adjustments', StockAdjustmentController::class);


    //tambahan
    // Stock Adjustment Routes
    Route::apiResource('stock-adjustments', StockAdjustmentController::class);
    Route::post('stock-adjustments/{id}/submit', [StockAdjustmentController::class, 'submit']);
    Route::post('stock-adjustments/{id}/approve', [StockAdjustmentController::class, 'approve']);
    Route::post('stock-adjustments/{id}/reject', [StockAdjustmentController::class, 'reject']);
    Route::post('stock-adjustments/{id}/process', [StockAdjustmentController::class, 'process']);

    // Manufacturing Module Routes
    // Products
    Route::apiResource('products', ProductController::class);

    // BOM
    Route::apiResource('boms', BOMController::class);
    Route::apiResource('boms/{bomId}/lines', BOMLineController::class);
    // Calculate potential yield from a specific material
    Route::post('/{bomId}/lines/{lineId}/calculate-yield', [BOMLineController::class, 'calculateYield']);

    // Calculate maximum possible production based on current stock
    Route::get('/{bomId}/maximum-yield', [BOMLineController::class, 'calculateMaximumYield']);

    // Create a yield-based BOM
    Route::post('/yield-based', [BOMController::class, 'createYieldBased']);

    // Routing
    Route::apiResource('routings', RoutingController::class);
    Route::apiResource('routings/{routingId}/operations', RoutingOperationController::class);

    // Work Centers
    Route::apiResource('work-centers', WorkCenterController::class);
    Route::get('work-centers/{workCenterId}/maintenance-schedules', [MaintenanceScheduleController::class, 'byWorkCenter']);

    // Work Orders
    Route::apiResource('work-orders', WorkOrderController::class);
    Route::apiResource('work-orders/{workOrderId}/operations', WorkOrderOperationController::class)
        ->except(['store', 'destroy']);
    Route::get('work-orders/next-number', [WorkOrderController::class, 'getNextWorkOrderNumber']);


    // Production Orders - Updated with separated flow
    Route::prefix('production-orders')->group(function () {
        // Basic CRUD
        Route::get('/next-number', [ProductionOrderController::class, 'getNextProductionNumber']);
        Route::get('/', [ProductionOrderController::class, 'index']);
        Route::post('/', [ProductionOrderController::class, 'store']);
        Route::get('/{id}', [ProductionOrderController::class, 'show']);
        Route::put('/{id}', [ProductionOrderController::class, 'update']);
        Route::delete('/{id}', [ProductionOrderController::class, 'destroy']);


        // Status management
        Route::patch('/{id}/status', [ProductionOrderController::class, 'updateStatus']);

        // New separated production flow endpoints
        Route::post('/{id}/issue-materials', [ProductionOrderController::class, 'issueMaterials']);
        Route::post('/{id}/start-production', [ProductionOrderController::class, 'startProduction']);
        Route::post('/{id}/complete', [ProductionOrderController::class, 'complete']);

        // Additional utility endpoints
        Route::get('/{id}/material-status', [ProductionOrderController::class, 'getMaterialStatus']);
        Route::get('/{id}/production-summary', [ProductionOrderController::class, 'getProductionSummary']);

        // Bulk operations
        Route::post('/bulk/issue-materials', [ProductionOrderController::class, 'bulkIssueMaterials']);
        Route::post('/bulk/start-production', [ProductionOrderController::class, 'bulkStartProduction']);

        // Reports
        Route::get('/reports/material-consumption', [ProductionOrderController::class, 'materialConsumptionReport']);
        Route::get('/reports/production-efficiency', [ProductionOrderController::class, 'productionEfficiencyReport']);

        // Nested consumption routes
        Route::prefix('{productionId}/consumptions')->group(function () {
            Route::get('/', [ProductionConsumptionController::class, 'index']);
            Route::post('/', [ProductionConsumptionController::class, 'store']);
            Route::get('/{consumptionId}', [ProductionConsumptionController::class, 'show']);
            Route::put('/{consumptionId}', [ProductionConsumptionController::class, 'update']);
            Route::delete('/{consumptionId}', [ProductionConsumptionController::class, 'destroy']);
        });
    });
    Route::apiResource('production-orders/{productionId}/consumptions', ProductionConsumptionController::class);

    // Maintenance Schedules
    Route::apiResource('maintenance-schedules', MaintenanceScheduleController::class);

    // Quality Control
    Route::apiResource('quality-inspections', QualityInspectionController::class);
    Route::apiResource('quality-inspections/{inspectionId}/parameters', QualityParameterController::class);
    Route::get('quality-parameters/categories', [QualityParameterController::class, 'categories']);
    Route::get('quality-inspections/by-reference/{referenceType}/{referenceId}', [QualityInspectionController::class, 'byReference']);
    Route::get('quality-parameters/items', [QualityParameterController::class, 'getItems']);

    Route::post('/material-planning/generate', [MaterialPlanningController::class, 'generateMaterialPlans']);
    Route::post('/material-planning/purchase-requisition', [MaterialPlanningController::class, 'generatePurchaseRequisitions']);
    Route::post('/material-planning/max-production', [MaterialPlanningController::class, 'calculateMaximumProduction']);
    // Tambahkan route untuk list material plans jika diperlukan
    Route::get('/material-planning', [MaterialPlanningController::class, 'index']);

    Route::delete('/material-planning/{id}', [MaterialPlanningController::class, 'destroy']);
    Route::post('/material-planning/work-orders', [MaterialPlanningController::class, 'generateWorkOrders']);

    // Add GET route for single material plan
    Route::get('/material-planning/{id}', [MaterialPlanningController::class, 'show']);
    Route::post('/material-planning/work-orders/period', [MaterialPlanningController::class, 'generateWorkOrdersByPeriod']);
    Route::post('/material-planning/purchase-requisition/period', [MaterialPlanningController::class, 'generatePurchaseRequisitionsByPeriod']);

    //MaterialPlanningMultyBOM
    Route::prefix('material-planning')->group(function () {
        // Basic CRUD
        Route::get('/', [MaterialPlanningController::class, 'index']);
        Route::get('/{id}', [MaterialPlanningController::class, 'show']);
        Route::delete('/{id}', [MaterialPlanningController::class, 'destroy']);

        // Core planning functions
        Route::post('/generate', [MaterialPlanningController::class, 'generateMaterialPlans']);
        Route::post('/calculate-max-production', [MaterialPlanningController::class, 'calculateMaximumProduction']);

        // BOM Analysis (NEW)
        Route::post('/bom-explosion', [MaterialPlanningController::class, 'getBOMExplosion']);

        // Purchase Requisition Generation
        Route::post('/generate-pr', [MaterialPlanningController::class, 'generatePurchaseRequisitions']);
        Route::post('/generate-pr-by-period', [MaterialPlanningController::class, 'generatePurchaseRequisitionsByPeriod']);

        // Work Order Generation
        Route::post('/generate-wo', [MaterialPlanningController::class, 'generateWorkOrders']);
        Route::post('/generate-wo-by-period', [MaterialPlanningController::class, 'generateWorkOrdersByPeriod']);
    });


    Route::get('items/{id}/prices-in-currencies', 'App\Http\Controllers\Api\Inventory\ItemController@getPricesInCurrencies');
    Route::get('customers/{id}/transactions-in-currency', 'App\Http\Controllers\Api\Sales\CustomerController@getTransactionsInCurrency');
    Route::get('vendors/{vendor}/transactions-in-currency', 'App\Http\Controllers\Api\VendorController@getTransactionsInCurrency');
    Route::put('vendors/{vendor}/preferred-currency', 'App\Http\Controllers\Api\VendorController@updatePreferredCurrency');
    // Accounting Module Routes
    Route::prefix('accounting')->group(function () {
        // Chart of Accounts
        Route::get('chart-of-accounts/hierarchy', [ChartOfAccountController::class, 'hierarchy']);
        Route::apiResource('chart-of-accounts', ChartOfAccountController::class);

        // Accounting Periods
        Route::get('accounting-periods/current', [AccountingPeriodController::class, 'current']);
        Route::apiResource('accounting-periods', AccountingPeriodController::class);

        // Journal Entries
        Route::post('journal-entries/{id}/post', [JournalEntryController::class, 'post']);
        Route::apiResource('journal-entries', JournalEntryController::class);

        // Customer Receivables
        Route::get('customer-receivables/aging', [CustomerReceivableController::class, 'aging']);
        Route::apiResource('customer-receivables', CustomerReceivableController::class);

        // Receivable Payments
        Route::apiResource('receivable-payments', ReceivablePaymentController::class);

        // Vendor Payables
        Route::get('vendor-payables/aging', [VendorPayableController::class, 'aging']);
        Route::apiResource('vendor-payables', VendorPayableController::class);

        // Payable Payments
        Route::apiResource('payable-payments', PayablePaymentController::class);

        // Tax Transactions
        Route::get('tax-transactions/summary', [TaxTransactionController::class, 'summary']);
        Route::apiResource('tax-transactions', TaxTransactionController::class);

        // Fixed Assets
        Route::apiResource('fixed-assets', FixedAssetController::class);

        // Asset Depreciations
        Route::post('fixed-assets/{id}/calculate-depreciation', [AssetDepreciationController::class, 'calculateDepreciation']);
        Route::apiResource('asset-depreciations', AssetDepreciationController::class);

        // Budgets
        Route::get('budgets/variance-report', [BudgetController::class, 'varianceReport']);
        Route::apiResource('budgets', BudgetController::class);

        // Bank Accounts
        Route::apiResource('bank-accounts', BankAccountController::class);

        // Bank Reconciliations
        Route::post('bank-reconciliations/{id}/finalize', [BankReconciliationController::class, 'finalize']);
        Route::apiResource('bank-reconciliations', BankReconciliationController::class);
        Route::apiResource('bank-reconciliations.lines', BankReconciliationController::class);

        // Financial Reports
        Route::prefix('reports')->group(function () {
            Route::get('trial-balance', [FinancialReportController::class, 'trialBalance']);
            Route::get('income-statement', [FinancialReportController::class, 'incomeStatement']);
            Route::get('balance-sheet', [FinancialReportController::class, 'balanceSheet']);
            Route::get('cash-flow', [FinancialReportController::class, 'cashFlow']);
            Route::get('accounts-receivable', [FinancialReportController::class, 'accountsReceivable']);
            Route::get('accounts-payable', [FinancialReportController::class, 'accountsPayable']);
        });
        // Currency Rates Management
        Route::get('currency-rates', [App\Http\Controllers\Api\CurrencyRateController::class, 'index']);
        Route::post('currency-rates', [App\Http\Controllers\Api\CurrencyRateController::class, 'store']);
        Route::get('currency-rates/{id}', [App\Http\Controllers\Api\CurrencyRateController::class, 'show']);
        Route::put('currency-rates/{id}', [App\Http\Controllers\Api\CurrencyRateController::class, 'update']);
        Route::delete('currency-rates/{id}', [App\Http\Controllers\Api\CurrencyRateController::class, 'destroy']);

        // Currency Converter utility
        Route::get('currency-rates/current-rate', [App\Http\Controllers\Api\CurrencyRateController::class, 'getCurrentRate']);
    });

    // PDF Order Capture Routes (FIXED - Exact Match Only for Items)
    Route::prefix('pdf-order-capture')->group(function () {
        // Main processing endpoint (ONLY EXTRACTS DATA - NO SO CREATION)
        Route::post('/', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'processPdf']);

        // FIXED: Create Sales Order from extracted data (separate step, exact match required)
        Route::post('/{id}/create-sales-order', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'createSalesOrderFromCapture']);

        // History and listing
        Route::get('/', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'index']);
        Route::get('/{id}', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'show']);

        // Actions
        Route::post('/{id}/retry', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'retry']);
        Route::delete('/{id}', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'destroy']);

        // File operations
        Route::get('/{id}/download', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'downloadFile']);

        // FIXED: Enhanced reprocessing with exact match validation
        Route::post('/{id}/reprocess-with-validation', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'reprocessWithValidation']);

        // Bulk operations
        Route::post('/bulk/retry', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'bulkRetry']);

        // Statistics and health check
        Route::get('/statistics/overview', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'getStatistics']);
        Route::get('/health/ai-service', [App\Http\Controllers\Api\PdfOrderCaptureController::class, 'checkAiServiceHealth']);
    });

    // Sales Module - Packing List Routes
    Route::prefix('sales')->group(function () {

        // Packing List CRUD operations
        Route::apiResource('packing-lists', 'App\Http\Controllers\Api\Sales\PackingListController');

        // Special packing list operations
        Route::post('packing-lists/from-delivery', 'App\Http\Controllers\Api\Sales\PackingListController@createFromDelivery');
        Route::put('packing-lists/{id}/complete', 'App\Http\Controllers\Api\Sales\PackingListController@completePacking');
        Route::put('packing-lists/{id}/ship', 'App\Http\Controllers\Api\Sales\PackingListController@markAsShipped');

        // Packing list utilities
        Route::get('packing-lists-available-deliveries', 'App\Http\Controllers\Api\Sales\PackingListController@getAvailableDeliveries');
        Route::get('packing-lists-progress', 'App\Http\Controllers\Api\Sales\PackingListController@getPackingProgress');

        // Integration with existing delivery routes
        Route::get('deliveries/{id}/packing-list', function ($deliveryId) {
            $packingList = \App\Models\Sales\PackingList::with(['packingListLines.item', 'customer'])
                ->where('delivery_id', $deliveryId)
                ->first();

            if (!$packingList) {
                return response()->json(['message' => 'No packing list found for this delivery'], 404);
            }

            return response()->json(['data' => $packingList], 200);
        });

        // Bulk operations
        Route::post('packing-lists/bulk-ship', function (\Illuminate\Http\Request $request) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'packing_list_ids' => 'required|array',
                'packing_list_ids.*' => 'exists:PackingList,packing_list_id'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $packingLists = \App\Models\Sales\PackingList::whereIn('packing_list_id', $request->packing_list_ids)
                ->where('status', \App\Models\Sales\PackingList::STATUS_COMPLETED)
                ->get();

            foreach ($packingLists as $packingList) {
                $packingList->update(['status' => \App\Models\Sales\PackingList::STATUS_SHIPPED]);
            }

            return response()->json([
                'message' => 'Packing lists marked as shipped successfully',
                'count' => $packingLists->count()
            ], 200);
        });
    });

    Route::prefix('manufacturing')->group(function () {
        Route::get('job-tickets/statistics', [JobTicketController::class, 'statistics']);
        Route::get('job-tickets/{id}/print', [JobTicketController::class, 'print']);
        Route::apiResource('job-tickets', JobTicketController::class);
    });
});

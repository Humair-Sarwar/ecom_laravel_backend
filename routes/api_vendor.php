<?php

use App\Http\Controllers\Vendor\AttributeController;
use App\Http\Controllers\Vendor\AuthController as VendorAuthController;
use App\Http\Controllers\Vendor\BrandController;
use App\Http\Controllers\Vendor\CategoryController;
use App\Http\Controllers\Vendor\MediaController;
use App\Http\Controllers\vendor\PolicyController;
use App\Http\Controllers\Vendor\ProductController;
use App\Http\Controllers\Vendor\SiteMenuController;
use App\Http\Controllers\Vendor\TermController;
use App\Models\SiteMenu;
use Illuminate\Support\Facades\Route;

// Public Vendor Auth Routes
Route::prefix('vendor/auth')->group(function () {
    Route::post('/login', [VendorAuthController::class, 'login']);
});

Route::prefix('vendor')->middleware(['auth:sanctum', 'user_type:2', 'set_tenant_schema'])->group(function (){
    Route::get('/auth/me', [VendorAuthController::class, 'me']);
    Route::post('/auth/switch-business', [VendorAuthController::class, 'switchBusiness']);
    Route::post('/auth/logout', [VendorAuthController::class, 'logout']);

    // Brand Routes
    Route::controller(BrandController::class)->group(function () {
        Route::get('/brands', 'index');
        Route::post('/brands', 'addNewBrand');
        Route::get('/brand/{id}', 'editBrand');
        Route::put('/brand/{id}', 'updateBrand');
        Route::delete('/brand/{id}', 'deleteBrand');
        Route::delete('/brands', 'DeleteSelectedBrands');
        Route::get('/brands/panel', 'brands');
        Route::put('/brand/status/update', 'brandStatusUpdate');
    });

    // Category Routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/category', [CategoryController::class, 'createCategory']);
    Route::get('/category/{id}', [CategoryController::class, 'editCategory']);
    Route::put('/category', [CategoryController::class, 'updateCategory']);
    Route::delete('/category/{id}', [CategoryController::class, 'deleteCategory']);
    Route::get('/categories/panel', [CategoryController::class, 'categories']);
    Route::get('/pos/categories', [CategoryController::class, 'posCategories']);

    // Product Routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/pos/products', [ProductController::class, 'posProducts']);
    Route::post('/product/step1', [ProductController::class, 'createProductStep1']);
    Route::put('/product/type/update', [ProductController::class, 'updateProductType']);
    Route::get('/product/type/get/{id}', [ProductController::class, 'getProductType']);
    Route::put('/product/create-step2', [ProductController::class, 'createProductStep2']);
    Route::get('/product/edit/step1/{id}', [ProductController::class, 'editProductStep1']);
    Route::put('/product/update/step1', [ProductController::class, 'updateProductStep1']);
    Route::get('/product/edit/step2/simple/{id}', [ProductController::class, 'editProductSimpleStep2']);
    Route::get('/product/attributes/get/{id}', [ProductController::class, 'loadAttributes']);
    Route::get('/product/terms/get/{id}', [ProductController::class, 'loadTerms']);
    Route::post('/product/attribute/terms/save', [ProductController::class, 'saveSelectedAttributeTerms']);
    Route::get('/products/{product_id}/attributes', [ProductController::class, 'getSelectedAttributeTerms']);
    Route::delete('products/attributes/delete', [ProductController::class, 'deleteProductAttribute']);
    Route::delete('products/terms/delete', [ProductController::class, 'deleteProductTerm']);
    Route::patch('products/attributes/update-sort-order', [ProductController::class, 'updateAttributeSortOrder']);
    Route::post('products/generate-variations', [ProductController::class, 'generateVariations']);
    Route::get('products/{id}/variations', [ProductController::class, 'getProductVariations']);
    Route::delete('/products/remove-term-variations', [ProductController::class, 'removeTermAndVariations']);
    Route::put('/products/variations/{id}', [ProductController::class, 'updateVariation']);
    Route::delete('/product/delete/{id}', [ProductController::class, 'deleteProduct']);
    Route::put('/product/publish/update', [ProductController::class, 'productPublishUpdate']);

    Route::post('/product/term/save-image', [ProductController::class, 'saveTermImageIdInProductTerms']);
    Route::post('/clear-term-image', [ProductController::class, 'clearTermImage']);
    Route::post('/sync-term-image-to-variations', [ProductController::class, 'syncTermImageToVariations']);

    // Attribute Routes
    Route::get('/attributes', [AttributeController::class, 'index']);
    Route::post('/attribute', [AttributeController::class, 'createAttribute']);
    Route::get('/attribute/edit/{id}', [AttributeController::class, 'editAttribute']);
    Route::put('/attribute/update', [AttributeController::class, 'updateAttribute']);
    Route::delete('/attribute/{id}', [AttributeController::class, 'deleteAttribute']);

    // Term Routes
    Route::get('/terms/{id}', [TermController::class, 'index']);
    Route::post('/term', [TermController::class, 'createTerm']);
    Route::get('/term/edit/{id}', [TermController::class, 'editTerm']);
    Route::put('/term/update', [TermController::class, 'updateTerm']);
    Route::delete('/term/{id}', [TermController::class, 'deleteTerm']);

    // Media Routes
    Route::get('/medias', [MediaController::class, 'index']);
    Route::post('/media', [MediaController::class, 'addNewMedia']);
    Route::post('/medias/uploads', [MediaController::class, 'addMultipleUploads']);
    Route::delete('/media/delete/{id}', [MediaController::class, 'deleteMedia']);

    // Policies Routes
    Route::controller(PolicyController::class)->group(function () {
        Route::get('/warranty-policy', 'warrantyPolicies')->middleware('vendor_permission:policies_view,warranty_view');
        Route::post('/warranty-policy/create', 'createWarrantyPolicy')->middleware('vendor_permission:warranty_add');
        Route::put('/warranty-policy/{id}', 'updateWarrantyPolicy')->middleware('vendor_permission:warranty_update');
        Route::delete('/warranty-policy/{id}', 'deleteWarranty')->middleware('vendor_permission:warranty_delete');
        Route::get('/warranties/panel', 'WarrantySelection');

        Route::get('/return-policy', 'returnPolicies')->middleware('vendor_permission:policies_view,return_view');
        Route::post('/return-policy/create', 'createReturnPolicy')->middleware('vendor_permission:return_add');
        Route::put('/return-policy/{id}', 'updateReturnPolicy')->middleware('vendor_permission:return_update');
        Route::delete('/return-policy/{id}', 'deleteReturn')->middleware('vendor_permission:return_delete');
        Route::get('/returns/panel', 'ReturnSelection');
    });


    // Site Settings -> Menu Json
    Route::controller(SiteMenuController::class)->group(function (){
        Route::get('/menu-json', 'getVendorMenu');
        Route::patch('/menu-json', 'save');
        
    });

    // Inventory Routes (Lab Items & Lab Item Usages)
    Route::controller(\App\Http\Controllers\Vendor\InventoryController::class)->group(function () {
        Route::get('/inventory/lab-items', 'labItems');
        Route::post('/inventory/lab-items', 'createLabItem');
        Route::get('/inventory/lab-items/{id}', 'showLabItem');
        Route::put('/inventory/lab-items/{id}', 'updateLabItem');
        Route::delete('/inventory/lab-items/{id}', 'deleteLabItem');
        Route::post('/inventory/lab-items/{id}/adjust-stock', 'adjustStock');
        Route::post('/inventory/lab-items/batch-update-category', 'batchUpdateCategory');

        Route::get('/inventory/lab-item-usages', 'labItemUsages');
        Route::post('/inventory/lab-item-usages', 'createLabItemUsage');
    });

    // Purchases & Suppliers Routes
    Route::controller(\App\Http\Controllers\Vendor\SupplierController::class)->group(function () {
        Route::get('/purchases/suppliers', 'index');
        Route::post('/purchases/suppliers', 'store');
        Route::get('/purchases/suppliers/{id}', 'show');
        Route::put('/purchases/suppliers/{id}', 'update');
        Route::delete('/purchases/suppliers/{id}', 'destroy');
    });

    // Supplier Ledger & Payment Routes
    Route::controller(\App\Http\Controllers\Vendor\SupplierLedgerController::class)->group(function () {
        Route::get('/purchases/payments', 'purchasePayments');
        Route::get('/purchases/suppliers/{id}/ledger', 'index');
        Route::post('/purchases/suppliers/{id}/make-payment', 'makePayment');
        Route::post('/purchases/suppliers/{id}/add-previous-balance', 'addPreviousBalance');
        Route::put('/purchases/suppliers/{id}/update-last-payment', 'updateLastPayment');
        Route::delete('/purchases/suppliers/{id}/delete-payment/{paymentId}', 'deletePayment');
    });

    // Purchase Order Routes
    Route::controller(\App\Http\Controllers\Vendor\PurchaseOrderController::class)->group(function () {
        Route::get('/purchases/orders', 'index');
        Route::get('/purchases/drafts', 'drafts');
        Route::delete('/purchases/drafts/{id}', 'destroyDraft');
        Route::get('/purchases/orders/search-products', 'searchProducts');
        Route::get('/purchases/orders/check-imei', 'checkImei');
        Route::post('/purchases/orders', 'store');
        Route::get('/purchases/orders/by-order-no/{orderNo}', 'showByOrderNo');
        Route::post('/purchases/orders/batch-by-nos', 'getMultipleOrders');
        Route::get('/purchases/orders/{id}', 'show');
        Route::put('/purchases/orders/{id}', 'update');
        Route::post('/purchases/orders/{id}/fulfill', 'receiveItems');
    });

    // Account Settings Routes
    Route::controller(\App\Http\Controllers\Vendor\AccountSettingController::class)->group(function () {
        Route::get('/settings/account', 'show');
        Route::post('/settings/account', 'update');
        Route::post('/settings/account/change-password', 'changePassword');
    });

    // Business Settings Routes
    Route::controller(\App\Http\Controllers\Vendor\BusinessSettingController::class)->group(function () {
        Route::get('/settings/business/general', 'showGeneral');
        Route::post('/settings/business/general', 'updateGeneral');

        Route::get('/settings/business/pos/terms', 'posTerms');
        Route::post('/settings/business/pos/terms', 'storePosTerm');
        Route::put('/settings/business/pos/terms/{id}', 'updatePosTerm');
        Route::patch('/settings/business/pos/terms/{id}/toggle-default', 'toggleDefaultPosTerm');
        Route::delete('/settings/business/pos/terms/{id}', 'deletePosTerm');

        Route::get('/settings/business/pos/order-types', 'posOrderTypes');
        Route::post('/settings/business/pos/order-types', 'storePosOrderType');
        Route::put('/settings/business/pos/order-types/{id}', 'updatePosOrderType');
        Route::patch('/settings/business/pos/order-types/{id}/toggle-default', 'toggleDefaultPosOrderType');
        Route::delete('/settings/business/pos/order-types/{id}', 'deletePosOrderType');

        Route::get('/settings/business/pos/order-processes', 'posOrderProcesses');
        Route::post('/settings/business/pos/order-processes', 'storePosOrderProcess');
        Route::put('/settings/business/pos/order-processes/{id}', 'updatePosOrderProcess');
        Route::patch('/settings/business/pos/order-processes/{id}/toggle-default', 'toggleDefaultPosOrderProcess');
        Route::delete('/settings/business/pos/order-processes/{id}', 'deletePosOrderProcess');

        Route::get('/settings/business/expense-types', 'expenseTypes');
        Route::post('/settings/business/expense-types', 'storeExpenseType');
        Route::put('/settings/business/expense-types/{id}', 'updateExpenseType');
        Route::patch('/settings/business/expense-types/{id}/toggle-default', 'toggleDefaultExpenseType');
        Route::delete('/settings/business/expense-types/{id}', 'deleteExpenseType');

        Route::get('/settings/business/email', 'showEmailSettings');
        Route::post('/settings/business/email', 'updateEmailSettings');
    });

    // Purchase Return Orders Routes
    Route::controller(\App\Http\Controllers\Vendor\PurchaseReturnController::class)->group(function () {
        Route::get('/purchases/returns', 'index');
        Route::get('/purchases/returns/lookup-receipt', 'lookupReceipt');
        Route::post('/purchases/returns', 'store');
        Route::get('/purchases/returns/{id}', 'show');
    });

    // Daily Expenses Routes
    Route::controller(\App\Http\Controllers\Vendor\DailyExpenseController::class)->group(function () {
        Route::get('/expenses/daily', 'index');
        Route::post('/expenses/daily', 'store');
        Route::put('/expenses/daily/{id}', 'update');
        Route::delete('/expenses/daily/{id}', 'destroy');
    });
});

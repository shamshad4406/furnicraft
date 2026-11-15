<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\PasswordResetController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryZoneController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\NewsletterController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PincodeController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductImportController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\QuotationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes (admin guard) — loaded with the /admin prefix, name "admin."
|--------------------------------------------------------------------------
*/

Route::middleware('guest.guard:admin')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.attempt');

    Route::get('forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')->name('password.email');

    Route::get('reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware(['auth:admin', 'account.active:admin'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')->name('dashboard');

    /* ------------------------------------------------- Phase 2: catalog */

    Route::resource('categories', CategoryController::class)->except('show');
    Route::patch('categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');

    Route::resource('brands', BrandController::class)->except('show');
    Route::patch('brands/{brand}/toggle', [BrandController::class, 'toggle'])->name('brands.toggle');

    // Import / export must be declared before the {product} wildcard routes.
    Route::get('products/import', [ProductImportController::class, 'index'])->name('products.import.index');
    Route::post('products/import', [ProductImportController::class, 'store'])->name('products.import.store');
    Route::get('products/export', [ProductImportController::class, 'export'])->name('products.export');
    Route::get('products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');

    Route::resource('products', ProductController::class);

    Route::prefix('products/{product}/images')->name('products.images.')->group(function () {
        Route::post('/', [ProductImageController::class, 'store'])->name('store');
        Route::post('reorder', [ProductImageController::class, 'reorder'])->name('reorder');
        Route::put('{image}', [ProductImageController::class, 'update'])->name('update');
        Route::patch('{image}/primary', [ProductImageController::class, 'primary'])->name('primary');
        Route::delete('{image}', [ProductImageController::class, 'destroy'])->name('destroy');
    });

    /* ------------------------------------------- Phase 4: marketing */

    Route::resource('coupons', CouponController::class);
    Route::patch('coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');

    /* ------------------------------------- Phase 5: orders & delivery */

    Route::controller(OrderController::class)->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', 'index')->name('index');
        // Before {order}, or "export" is read as an order token.
        Route::get('export', 'export')->name('export');
        Route::get('{order}', 'show')->name('show');
        Route::patch('{order}/status', 'updateStatus')->name('status');
        Route::patch('{order}/notes', 'updateNotes')->name('notes');
        Route::post('{order}/invoice', 'raiseInvoice')->name('invoice.raise');
        Route::get('{order}/invoice', 'invoice')->name('invoice');
    });

    Route::controller(ShipmentController::class)->name('shipments.')->group(function () {
        Route::get('shipments', 'index')->name('index');
        Route::post('orders/{order}/shipments', 'store')->name('store');
        Route::put('shipments/{shipment}', 'update')->name('update');
        Route::patch('shipments/{shipment}/status', 'updateStatus')->name('status');
        Route::delete('shipments/{shipment}', 'destroy')->name('destroy');
    });

    Route::controller(ReturnController::class)->name('returns.')->group(function () {
        Route::get('returns', 'index')->name('index');
        Route::get('returns/{return}', 'show')->name('show');
        Route::post('orders/{order}/returns', 'store')->name('store');
        Route::patch('returns/{return}/approve', 'approve')->name('approve');
        Route::patch('returns/{return}/reject', 'reject')->name('reject');
        Route::patch('returns/{return}/picked-up', 'pickUp')->name('pickup');
        Route::patch('returns/{return}/receive', 'receive')->name('receive');
        Route::patch('returns/{return}/close', 'close')->name('close');
        Route::patch('returns/{return}/refund', 'refund')->name('refund');
        Route::patch('returns/{return}/replace', 'replace')->name('replace');
    });

    /* --------------------------------- Phase 6: enquiry & quotation */

    Route::controller(EnquiryController::class)->prefix('enquiries')->name('enquiries.')->group(function () {
        Route::get('/', 'index')->name('index');
        // Before {enquiry}, or "create" is read as a token.
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{enquiry}', 'show')->name('show');
        Route::post('{enquiry}/reply', 'reply')->name('reply');
        Route::patch('{enquiry}/assign', 'assign')->name('assign');
        Route::patch('{enquiry}/close', 'close')->name('close');
        Route::patch('{enquiry}/reopen', 'reopen')->name('reopen');
        Route::patch('{enquiry}/note', 'updateNote')->name('note');
        Route::delete('{enquiry}', 'destroy')->name('destroy');
    });

    Route::controller(QuotationController::class)->prefix('quotations')->name('quotations.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{quotation}', 'show')->name('show');
        Route::get('{quotation}/print', 'print')->name('print');
        Route::put('{quotation}', 'update')->name('update');
        Route::put('{quotation}/items', 'updateItems')->name('items');
        Route::post('{quotation}/send', 'send')->name('send');
        Route::patch('{quotation}/accepted', 'markAccepted')->name('accepted');
        Route::patch('{quotation}/declined', 'markDeclined')->name('declined');
        Route::post('{quotation}/convert', 'convert')->name('convert');
        Route::delete('{quotation}', 'destroy')->name('destroy');
    });

    /* -------------------------------- Phase 7: inventory & reports */

    Route::controller(InventoryController::class)->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', 'index')->name('index');
        // Both before {product}, or they are read as a product.
        Route::get('movements', 'movements')->name('movements');
        Route::get('export', 'export')->name('export');
        Route::get('{product}', 'show')->name('show');
        Route::post('{product}/adjust', 'adjust')->name('adjust');
        Route::patch('{product}/threshold', 'updateThreshold')->name('threshold');
    });

    Route::resource('suppliers', SupplierController::class);
    Route::patch('suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])->name('suppliers.toggle');

    Route::controller(PurchaseOrderController::class)->prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{purchase}', 'show')->name('show');
        Route::put('{purchase}', 'update')->name('update');
        Route::put('{purchase}/items', 'updateItems')->name('items');
        Route::post('{purchase}/place', 'place')->name('place');
        Route::post('{purchase}/receive', 'receive')->name('receive');
        Route::patch('{purchase}/cancel', 'cancel')->name('cancel');
        Route::delete('{purchase}', 'destroy')->name('destroy');
    });

    Route::controller(CustomerController::class)->prefix('customers')->name('customers.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('export', 'export')->name('export');
        Route::get('{customer}', 'show')->name('show');
        Route::patch('{customer}/toggle', 'toggle')->name('toggle');
    });

    Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('gst', 'gst')->name('gst');
        Route::get('export', 'export')->name('export');
    });

    /* -------------------------------- Phase 8: marketing & reviews */

    Route::resource('banners', BannerController::class)->except('show');
    Route::patch('banners/{banner}/toggle', [BannerController::class, 'toggle'])->name('banners.toggle');
    Route::patch('banners/{banner}/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');

    Route::controller(ReviewController::class)->prefix('reviews')->name('reviews.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::patch('{review}/approve', 'approve')->name('approve');
        Route::patch('{review}/reject', 'reject')->name('reject');
        Route::patch('{review}/reply', 'reply')->name('reply');
        Route::patch('{review}/feature', 'feature')->name('feature');
        Route::delete('{review}', 'destroy')->name('destroy');
    });

    Route::controller(NewsletterController::class)->prefix('newsletter')->name('newsletter.')->group(function () {
        Route::get('/', 'index')->name('index');
        // Before {subscriber}, or "export" is read as a token.
        Route::get('export', 'export')->name('export');
        Route::patch('{subscriber}/unsubscribe', 'unsubscribe')->name('unsubscribe');
    });

    /* ----------------------------------- Phase 3: content & delivery */

    Route::resource('cms', CmsPageController::class)->except('show')->names('cms');
    Route::patch('cms/{cm}/toggle', [CmsPageController::class, 'toggle'])->name('cms.toggle');

    Route::get('delivery/zones', [DeliveryZoneController::class, 'index'])->name('delivery.zones.index');
    Route::post('delivery/zones', [DeliveryZoneController::class, 'store'])->name('delivery.zones.store');
    Route::put('delivery/zones/{zone}', [DeliveryZoneController::class, 'update'])->name('delivery.zones.update');
    Route::delete('delivery/zones/{zone}', [DeliveryZoneController::class, 'destroy'])->name('delivery.zones.destroy');

    Route::get('delivery/pincodes', [PincodeController::class, 'index'])->name('delivery.pincodes.index');
    Route::post('delivery/pincodes', [PincodeController::class, 'store'])->name('delivery.pincodes.store');
    Route::post('delivery/pincodes/import', [PincodeController::class, 'import'])->name('delivery.pincodes.import');
    Route::get('delivery/pincodes/export', [PincodeController::class, 'export'])->name('delivery.pincodes.export');
    Route::put('delivery/pincodes/{pincode}', [PincodeController::class, 'update'])->name('delivery.pincodes.update');
    Route::patch('delivery/pincodes/{pincode}/toggle', [PincodeController::class, 'toggle'])->name('delivery.pincodes.toggle');
    Route::delete('delivery/pincodes/{pincode}', [PincodeController::class, 'destroy'])->name('delivery.pincodes.destroy');

    // Staff management
    Route::resource('users', UserController::class)->except('show');
    Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

    // Roles & permissions
    Route::resource('roles', RoleController::class)->except('show');

    // Website settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    // Audit trail
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::get('login-history', [ActivityLogController::class, 'logins'])
        ->middleware('permission:activity.view')->name('activity.logins');

    // Own profile
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
});

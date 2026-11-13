<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\PasswordResetController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeliveryZoneController;
use App\Http\Controllers\Admin\PincodeController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductImportController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
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

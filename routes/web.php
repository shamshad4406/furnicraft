<?php

use App\Http\Controllers\Front\Auth\CustomerAuthController;
use App\Http\Controllers\Front\Auth\CustomerPasswordResetController;
use App\Http\Controllers\Front\CategoryController;
use App\Http\Controllers\Front\DeliveryController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\PageController;
use App\Http\Controllers\Front\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront routes (customer guard)
|--------------------------------------------------------------------------
| Admin routes live in routes/admin.php and are loaded under the /admin
| prefix with the "admin." name prefix.
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

/* ------------------------------------------------------------- catalog */

Route::get('categories', [CategoryController::class, 'index'])->name('categories');
Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('category.show');

Route::get('products', [ProductController::class, 'index'])->name('products');
Route::get('search/suggest', [ProductController::class, 'suggest'])
    ->middleware('throttle:60,1')->name('search.suggest');
Route::get('product/{slug}', [ProductController::class, 'show'])->name('product.show');

Route::post('delivery/check', [DeliveryController::class, 'check'])
    ->middleware('throttle:30,1')->name('delivery.check');

/* ------------------------------------------------------ customer guard */

Route::middleware('guest.guard:customer')->group(function () {
    Route::get('login', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
    Route::post('login', [CustomerAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::get('register', [CustomerAuthController::class, 'showRegister'])->name('customer.register');
    Route::post('register', [CustomerAuthController::class, 'register'])->middleware('throttle:10,1');

    Route::get('forgot-password', [CustomerPasswordResetController::class, 'requestForm'])->name('customer.password.request');
    Route::post('forgot-password', [CustomerPasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')->name('customer.password.email');

    Route::get('reset-password/{token}', [CustomerPasswordResetController::class, 'resetForm'])->name('customer.password.reset');
    Route::post('reset-password', [CustomerPasswordResetController::class, 'reset'])->name('customer.password.update');
});

Route::post('logout', [CustomerAuthController::class, 'logout'])
    ->middleware('auth:customer')
    ->name('customer.logout');

/* --------------------------------------------------------- CMS pages --*/
// Declared last so it never shadows a named route above.
Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('page');

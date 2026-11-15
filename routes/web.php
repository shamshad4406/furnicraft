<?php

use App\Http\Controllers\Front\Auth\CustomerAuthController;
use App\Http\Controllers\Front\Auth\CustomerPasswordResetController;
use App\Http\Controllers\Front\AccountController;
use App\Http\Controllers\Front\AddressController;
use App\Http\Controllers\Front\CartController;
use App\Http\Controllers\Front\CategoryController;
use App\Http\Controllers\Front\CheckoutController;
use App\Http\Controllers\Front\DeliveryController;
use App\Http\Controllers\Front\EnquiryController;
use App\Http\Controllers\Front\PaymentController;
use App\Http\Controllers\Front\WishlistController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\NewsletterController;
use App\Http\Controllers\Front\OrderActionController;
use App\Http\Controllers\Front\OrderTrackingController;
use App\Http\Controllers\Front\PageController;
use App\Http\Controllers\Front\ProductController;
use App\Http\Controllers\Front\QuotationResponseController;
use App\Http\Controllers\Front\ReviewController;
use App\Http\Controllers\Front\SeoController;
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

/* ---------------------------------------------------- cart & wishlist */

Route::controller(CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('add', 'add')->name('add');
    Route::patch('items/{item}', 'update')->name('update');
    Route::delete('items/{item}', 'destroy')->name('remove');
    Route::post('pincode', 'setPincode')->name('pincode');
    Route::post('coupon', 'applyCoupon')->name('coupon.apply');
    Route::delete('coupon', 'removeCoupon')->name('coupon.remove');
});

Route::controller(WishlistController::class)->prefix('wishlist')->name('wishlist.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('toggle', 'toggle')->name('toggle');
    Route::delete('{product}', 'destroy')->name('remove');
    Route::post('{product}/move-to-cart', 'moveToCart')->name('move');
});

/* -------------------------------------------------------- checkout --*/

Route::controller(CheckoutController::class)->prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
    Route::get('{order}/pay', 'pay')->name('pay');
    Route::get('{order}/success', 'success')->name('success');
    Route::get('{order}/failed', 'failed')->name('failed');
});

/* ------------------------------------------------- search engines --*/
// Served by the app rather than written to disk: a sitemap built on request
// cannot go stale.

Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('b/{banner}', [SeoController::class, 'banner'])->name('banner.click');

/* ------------------------------------------------------- newsletter --*/

Route::controller(NewsletterController::class)->prefix('newsletter')->name('newsletter.')->group(function () {
    Route::post('subscribe', 'subscribe')->middleware('throttle:10,1')->name('subscribe');
    Route::get('confirm/{token}', 'confirm')->name('confirm');
    // One click, no sign-in: anything else produces spam complaints.
    Route::get('unsubscribe/{subscriber}', 'unsubscribe')->name('unsubscribe');
});

/* ---------------------------------------------------------- reviews --*/

Route::controller(ReviewController::class)->prefix('product/{slug}/review')->name('review.')->group(function () {
    Route::get('/', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:6,1')->name('store');
});

/* ------------------------------------------------------- enquiries --*/
// Open to everyone: most people asking about a sofa have no account.

Route::controller(EnquiryController::class)->prefix('enquiry')->name('enquiry.')->group(function () {
    Route::get('/', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
    Route::get('sent/{token}', 'sent')->name('sent');
});

/* ------------------------------------------------------ quotations --*/
// The token is the key: someone being quoted for custom furniture usually
// has no account, and accepting commits them to nothing irreversible.

Route::controller(QuotationResponseController::class)->prefix('quotation')->name('quotation.')->group(function () {
    Route::get('{token}', 'show')->name('show');
    Route::post('{token}/accept', 'accept')->middleware('throttle:10,1')->name('accept');
    Route::post('{token}/decline', 'decline')->middleware('throttle:10,1')->name('decline');
});

/* -------------------------------------------------- order tracking --*/
// Open to guests: the only way back to an order once the session that placed
// it is gone. Declared before the catch-all CMS page route.

Route::controller(OrderTrackingController::class)->prefix('track-order')->name('track.')->group(function () {
    Route::get('/', 'show')->name('form');
    Route::post('/', 'find')->middleware('throttle:20,1')->name('find');
    Route::get('{token}', 'status')->name('status');
});

Route::controller(PaymentController::class)->prefix('payment')->name('payment.')->group(function () {
    Route::post('{order}/create', 'create')->middleware('throttle:20,1')->name('create');
    Route::post('{order}/callback', 'callback')->name('callback');
    Route::post('{order}/cancelled', 'cancelled')->name('cancelled');
});

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

/* ----------------------------------------------------- my account --*/

Route::middleware(['auth:customer', 'account.active:customer'])
    ->prefix('account')->name('account.')->group(function () {
        Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
        Route::get('profile', [AccountController::class, 'profile'])->name('profile');
        Route::put('profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::put('password', [AccountController::class, 'updatePassword'])->name('password.update');

        Route::get('orders', [AccountController::class, 'orders'])->name('orders');
        Route::get('orders/{token}', [AccountController::class, 'order'])->name('order');

        // Acting on an order needs the account, not just the token.
        Route::patch('orders/{token}/cancel', [OrderActionController::class, 'cancel'])->name('order.cancel');
        Route::get('orders/{token}/return', [OrderActionController::class, 'returnForm'])->name('order.return');
        Route::post('orders/{token}/return', [OrderActionController::class, 'requestReturn'])
            ->middleware('throttle:10,1')->name('order.return.store');

        Route::get('addresses', [AddressController::class, 'index'])->name('addresses');
        Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
        Route::put('addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
        Route::patch('addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('addresses.default');
        Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    });

/* --------------------------------------------------------- CMS pages --*/
// Declared last so it never shadows a named route above.
Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('page');

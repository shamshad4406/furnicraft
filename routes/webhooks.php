<?php

use App\Http\Controllers\Front\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks — server-to-server, no session
|--------------------------------------------------------------------------
| These are called by payment providers, not by a browser. There is no
| session and no CSRF token; the request is authenticated by the signature
| in its header, checked inside the controller before anything is acted on.
*/

Route::post('razorpay', [PaymentController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('razorpay');

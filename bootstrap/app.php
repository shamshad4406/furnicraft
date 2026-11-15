<?php

use App\Exceptions\InvalidImageException;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Webhooks: no session, no CSRF — authenticated by signature.
            Illuminate\Support\Facades\Route::middleware('api')
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));

            Illuminate\Support\Facades\Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Security response headers on every web request.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'guest.guard' => RedirectIfAuthenticated::class,
            'account.active' => EnsureAccountIsActive::class,
        ]);

        // Unauthenticated requests go to the login screen of the matching guard.
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('admin') || $request->is('admin/*')
                ? route('admin.login')
                : route('customer.login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            return $request->is('admin') || $request->is('admin/*')
                ? route('admin.dashboard')
                : route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A file that passed the rules but is not a usable image comes back as
        // a form error, not a 500.
        $exceptions->render(function (InvalidImageException $e, Request $request) {
            // Attach it to whichever field actually carried a file, so the
            // message lands next to the input the person used. A hard-coded
            // list goes stale the moment a new form adds an upload, and the
            // error then renders against a field that is not on the page —
            // which looks to the person like nothing happened at all.
            $field = collect(array_keys($request->allFiles()))->first() ?? 'image';

            return back()->withInput()->withErrors([$field => $e->getMessage()]);
        });

        // A missing permission inside the admin panel shows a branded 403 page.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('admin/*') && $e->getStatusCode() === 403 && ! $request->expectsJson()) {
                return response()->view('admin.errors.403', [
                    'message' => $e->getMessage() ?: 'You do not have permission to open this page.',
                ], 403);
            }

            return null;
        });
    })->create();

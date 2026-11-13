<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers.
 *
 * HSTS is only sent over HTTPS, so it is harmless on a local HTTP dev server
 * and takes effect automatically once the site is behind TLS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Stop the browser guessing a different content type for an upload.
            'X-Content-Type-Options' => 'nosniff',
            // No framing — this app has no legitimate embedded use.
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            // Features the site never uses.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
        ];

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value, false);
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Signed-in pages must not sit in a shared or back-button cache.
        if ($request->is('admin', 'admin/*') && ! $request->is('admin/login', 'admin/forgot-password', 'admin/reset-password/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        $response->headers->set('Content-Security-Policy', $this->policy());

        return $response;
    }

    /**
     * Kept deliberately narrow: this app loads scripts and styles from its own
     * origin plus the CDNs the layouts actually reference.
     */
    protected function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            // Blade uses a few inline style attributes; fonts come from Google.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "script-src 'self'",
            "img-src 'self' data:",
            "connect-src 'self'",
        ]);
    }
}

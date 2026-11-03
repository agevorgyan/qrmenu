<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and attach hardening security headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Enforce anti-framing / clickjacking defense
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Enforce MIME-sniffing prevention
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Legacy XSS filter activation
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Protect referrer leakage on outbound navigation
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict sensitive browser APIs
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        // Content Security Policy baseline protection
        $response->headers->set('Content-Security-Policy', "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval'; img-src 'self' https: data: blob:; font-src 'self' https: data:; frame-ancestors 'self';");

        // Enforce Strict-Transport-Security on HTTPS connections
        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Prevent Back-Forward Cache (bfcache) leakage of authenticated and sensitive admin/pos pages
        if ($request->is('admin*') || $request->is('pos*') || $request->is('superadmin*') || $request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
        }

        // Remove server tech disclosure headers
        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->isProduction() && ! $request->isSecure()) {
            abort(400, 'Se requiere HTTPS.');
        }
        $nonce = base64_encode(random_bytes(18));
        $request->attributes->set('csp_nonce', $nonce);
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $scriptPolicy = "script-src 'self'";
        if ($request->is('admin/catalog/imports/*')) {
            $scriptPolicy .= " 'nonce-$nonce'";
        }
        $response->headers->set('Content-Security-Policy', "default-src 'self'; $scriptPolicy; style-src 'self' 'nonce-$nonce'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if (! app()->isProduction() || $request->is('admin*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($request->is('admin*') || str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}

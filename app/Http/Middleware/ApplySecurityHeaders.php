<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! config('nema.security_headers.enabled', true)) {
            return $response;
        }

        $headers = $response->headers;
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->set('Origin-Agent-Cluster', '?1');
        $headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set('X-XSS-Protection', '1; mode=block');

        if ($this->shouldEnforceHttps($request)) {
            $headers->set('Strict-Transport-Security', 'max-age=' . (int) config('nema.security_headers.hsts_max_age', 31536000) . '; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https:",
            "manifest-src 'self'",
            "style-src 'self' 'unsafe-inline' https:",
            "script-src 'self' 'unsafe-inline' https:",
            "connect-src 'self' https: wss:",
            "worker-src 'self' blob:",
        ];

        if ($this->shouldEnforceHttps($request)) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    private function shouldEnforceHttps(Request $request): bool
    {
        return $request->isSecure() || str_starts_with((string) config('app.url'), 'https://');
    }
}

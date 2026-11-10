<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections for every page: no framing by other sites (clickjacking), no MIME sniffing,
 * forms that can only post back to this site, and HSTS once the site runs on https.
 *
 * Responses that already set a header keep their own value, such as the stricter sandbox policy on file previews.
 */
class SecurityHeaders
{
    /**
     * @var array<string, string>
     */
    private const array HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Don't advertise the exact PHP version.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}

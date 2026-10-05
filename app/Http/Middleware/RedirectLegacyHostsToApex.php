<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyHostsToApex
{
    /**
     * Hosts that should always canonicalize to the real domain, never serve
     * the app directly.
     *
     * `www.dreamworldcirque.com`: serving both it and the bare domain with
     * no canonical redirect between them split search signals, and broke
     * Google OAuth outright for a visitor on the `www.` host (its
     * dynamically-generated callback URL didn't match any registered
     * redirect URI, since only the bare domain was ever registered).
     *
     * `www.dreamworldcircus.com`: the bare `dreamworldcircus.com` (an
     * alternate English spelling Adam also owns) deliberately serves the
     * app directly instead of redirecting -- see site.blade.php's
     * `<link rel="canonical">` and the GA4 host gate, which both treat it
     * as a second legitimate production host, not a legacy one. Its `www.`
     * variant still canonicalizes here, same as the real domain's.
     *
     * @var list<string>
     */
    private const LEGACY_HOSTS = [
        'www.dreamworldcirque.com',
        'www.dreamworldcircus.com',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), self::LEGACY_HOSTS, true)) {
            return redirect()->to(
                'https://dreamworldcirque.com'.$request->getRequestUri(),
                301,
            );
        }

        return $next($request);
    }
}

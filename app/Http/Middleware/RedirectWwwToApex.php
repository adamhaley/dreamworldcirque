<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWwwToApex
{
    /**
     * Handle an incoming request.
     *
     * Scoped specifically to the real domain, not the staging subdomain --
     * serving both `dreamworldcirque.com` and `www.dreamworldcirque.com`
     * with no canonical redirect between them split search signals, and
     * broke Google OAuth outright for a visitor on the `www.` host (its
     * dynamically-generated callback URL didn't match any registered
     * redirect URI, since only the bare domain was ever registered).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() === 'www.dreamworldcirque.com') {
            return redirect()->to(
                'https://dreamworldcirque.com'.$request->getRequestUri(),
                301,
            );
        }

        return $next($request);
    }
}

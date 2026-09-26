<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthorizedUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirectUrl($this->callbackUrl())->redirect();
    }

    public function callback(AuthorizedUserResolver $users): RedirectResponse
    {
        $user = $users->resolveFromGoogleUser(Socialite::driver('google')->redirectUrl($this->callbackUrl())->user());

        if ($user === null) {
            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors(['email' => 'This Google account is not authorized to manage Dream World Cirque.']);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/admin');
    }

    // Deliberately derived from the current request's own host rather than a
    // static GOOGLE_REDIRECT_URI: while both the staging subdomain and the
    // real domain are in active use, a request starting on one host but
    // told to come back on the other crosses origins -- the session cookie
    // that holds Socialite's CSRF "state" belongs to whichever host issued
    // it, so a cross-origin callback fails that check outright (a 500, not
    // a graceful error). Keeping the round trip on the same host it started
    // on avoids that regardless of which domain a visitor uses.
    private function callbackUrl(): string
    {
        return url('/auth/google/callback');
    }
}

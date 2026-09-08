<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AuthenticationSessionService
{
    public function rememberDestination(Request $request, mixed $destination): void
    {
        if ($safe = $this->safeLocalDestination($request, $destination)) {
            $request->session()->put('auth.redirect', $safe);
        }
    }

    public function complete(Request $request, User $user): RedirectResponse
    {
        $explicit = $this->safeLocalDestination($request, $request->input('redirect'));
        $remembered = $this->safeLocalDestination($request, $request->session()->pull('auth.redirect'));
        $intended = (string) $request->session()->pull('url.intended', '');
        $intendedPath = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $safeIntendedPaths = ['/account', '/account/tickets', '/account/tickets/create', '/checkout'];
        $destination = $explicit ?? $remembered;
        if (! $destination && in_array($intendedPath, $safeIntendedPaths, true)) {
            $destination = $this->safeLocalDestination($request, $intended);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->to($destination ?: route('home'));
    }

    private function safeLocalDestination(Request $request, mixed $destination): ?string
    {
        if (! is_string($destination) || ($destination = trim($destination)) === '' || preg_match('/[\x00-\x1F\x7F]/', $destination)) {
            return null;
        }
        if (str_starts_with($destination, '//')) {
            return null;
        }

        $parts = parse_url($destination);
        if ($parts === false) {
            return null;
        }
        if (isset($parts['host']) && mb_strtolower($parts['host']) !== mb_strtolower($request->getHost())) {
            return null;
        }
        if (isset($parts['scheme']) && ! in_array(mb_strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }
}

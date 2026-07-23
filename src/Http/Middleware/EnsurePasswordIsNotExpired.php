<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation\Http\Middleware;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsNotExpired
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('laravel-password-rotation.enabled')) {
            return $next($request);
        }

        $route = config('laravel-password-rotation.redirect_route');

        // Nowhere to send the user, or the feature is not wired to a screen:
        // stay out of the way rather than throwing on a missing route.
        if (! is_string($route) || $route === '') {
            return $next($request);
        }

        $user = auth()->user();

        if (! $user instanceof MustRotatePassword || ! $user->passwordHasExpired()) {
            return $next($request);
        }

        // Never trap the user on the redirect target itself (the redirect would
        // loop forever), nor on any route the app has explicitly exempted —
        // most importantly logout, so an expired user can always sign out.
        $except = config('laravel-password-rotation.except_routes');
        $except = is_array($except) ? array_values(array_filter($except, 'is_string')) : [];

        if ($request->routeIs($route, ...$except)) {
            return $next($request);
        }

        return redirect()->route($route);
    }
}

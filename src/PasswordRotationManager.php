<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\Facades\PasswordRotation;
use Closure;
use Illuminate\Http\Request;

/**
 * Runtime configuration point for the package, resolved as a container singleton
 * and reachable through the {@see PasswordRotation}
 * facade. Callbacks live here (never in the config file) so they survive
 * `config:cache`, which cannot serialise a Closure.
 */
class PasswordRotationManager
{
    /**
     * Callbacks that let an otherwise-expired user through untouched. The
     * request is bypassed as soon as any of them returns true, so several
     * independent reasons to skip rotation compose without clobbering.
     *
     * @var array<int, Closure(Request, MustRotatePassword): bool>
     */
    protected array $bypassCallbacks = [];

    /**
     * Register a callback that exempts a request from the forced rotation — for
     * instance SSO users whose password is owned by the identity provider and is
     * flagged on the session. The callback receives the current request and the
     * expired, authenticated user, and returns true to skip the redirect.
     *
     * @param  Closure(Request, MustRotatePassword): bool  $callback
     */
    public function bypass(Closure $callback): void
    {
        $this->bypassCallbacks[] = $callback;
    }

    /**
     * Whether any registered callback exempts this request/user from rotation.
     */
    public function shouldBypass(Request $request, MustRotatePassword $user): bool
    {
        foreach ($this->bypassCallbacks as $callback) {
            if ($callback($request, $user)) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation\Facades;

use BBSLab\LaravelPasswordRotation\PasswordRotationManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void bypass(\Closure $callback)
 * @method static bool shouldBypass(\Illuminate\Http\Request $request, \BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword $user)
 *
 * @see PasswordRotationManager
 */
class PasswordRotation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PasswordRotationManager::class;
    }
}

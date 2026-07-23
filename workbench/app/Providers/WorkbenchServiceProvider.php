<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Wire the package for the `composer serve` demo exactly the way a real
     * consumer would: point the middleware at the demo's change screen and let
     * an expired user still sign out.
     */
    public function boot(): void
    {
        config([
            'laravel-password-rotation.redirect_route' => 'password.rotate',
            'laravel-password-rotation.except_routes' => ['logout'],
        ]);

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'demo');
    }
}

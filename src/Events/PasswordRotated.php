<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class PasswordRotated
{
    use Dispatchable;

    public function __construct(
        public readonly Model $authenticatable,
    ) {}
}

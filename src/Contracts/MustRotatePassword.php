<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation\Contracts;

use Carbon\CarbonInterface;

interface MustRotatePassword
{
    /**
     * The timestamp column holding the moment the password was last changed.
     */
    public function passwordRotationColumn(): string;

    /**
     * When the password was last changed, or null if it never has been.
     */
    public function passwordLastChangedAt(): ?CarbonInterface;

    /**
     * When the current password expires, or null when it cannot be determined.
     */
    public function passwordExpiresAt(): ?CarbonInterface;

    /**
     * Whether the password must be rotated right now.
     */
    public function passwordHasExpired(): bool;

    /**
     * Whether the password is still valid but within the expiry warning window.
     */
    public function passwordIsExpiring(): bool;
}

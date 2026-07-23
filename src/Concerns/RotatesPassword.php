<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation\Concerns;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\Events\PasswordRotated;
use BBSLab\LaravelPasswordRotation\Models\PasswordHistory;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Implements {@see MustRotatePassword}. Use on an Eloquent model that is also
 * Authenticatable.
 *
 * @mixin Model
 */
trait RotatesPassword
{
    public function passwordRotationColumn(): string
    {
        return (string) config('laravel-password-rotation.column', 'password_changed_at');
    }

    public function initializeRotatesPassword(): void
    {
        $this->mergeCasts([$this->passwordRotationColumn() => 'datetime']);
    }

    public function passwordLastChangedAt(): ?CarbonInterface
    {
        $value = $this->getAttribute($this->passwordRotationColumn());

        return $value instanceof CarbonInterface ? $value : null;
    }

    public function passwordExpiresAt(): ?CarbonInterface
    {
        return $this->passwordLastChangedAt()
            ?->copy()
            ->addDays((int) config('laravel-password-rotation.days'));
    }

    public function passwordHasExpired(): bool
    {
        if (! config('laravel-password-rotation.enabled')) {
            return false;
        }

        $expiresAt = $this->passwordExpiresAt();

        if ($expiresAt === null) {
            return (bool) config('laravel-password-rotation.force_on_first_login');
        }

        return $expiresAt->isPast();
    }

    public function passwordIsExpiring(): bool
    {
        // passwordHasExpired() returns false when the feature is disabled (not
        // "expired"), so gate on enabled here too or the callout would show.
        if (! config('laravel-password-rotation.enabled') || $this->passwordHasExpired()) {
            return false;
        }

        $warnDays = (int) config('laravel-password-rotation.warn_days');

        if ($warnDays <= 0) {
            return false;
        }

        $expiresAt = $this->passwordExpiresAt();

        return $expiresAt !== null && now()->gte($expiresAt->copy()->subDays($warnDays));
    }

    public static function bootRotatesPassword(): void
    {
        static::creating(function (Model&MustRotatePassword $model): void {
            // When first-login rotation is forced, leave the column null so a
            // freshly provisioned account is treated as expired until it sets
            // its own password. Auto-stamping here would silently grant a full
            // rotation window and make force_on_first_login a no-op.
            if (config('laravel-password-rotation.force_on_first_login')) {
                return;
            }

            $column = $model->passwordRotationColumn();

            if (empty($model->getAttribute($column))) {
                $model->setAttribute($column, $model->freshTimestamp());
            }
        });

        static::updating(function (Model&MustRotatePassword&Authenticatable $model): void {
            if ($model->isDirty($model->getAuthPasswordName())) {
                $model->setAttribute($model->passwordRotationColumn(), $model->freshTimestamp());
            }
        });

        static::saved(function (Model&Authenticatable $model): void {
            $changed = $model->wasChanged($model->getAuthPasswordName()) || $model->wasRecentlyCreated;

            if (! $changed) {
                return;
            }

            if ((int) config('laravel-password-rotation.history_count') > 0) {
                PasswordHistory::recordFor($model);
            }

            if (! $model->wasRecentlyCreated) {
                PasswordRotated::dispatch($model);
            }
        });
    }
}

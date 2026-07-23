<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation\Console\Commands;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class PasswordRotationReport extends Command
{
    protected $signature = 'password-rotation:report {--all : List every account, not only those expired or expiring soon}';

    protected $description = 'Report the password rotation status of the configured authenticatable models.';

    public function handle(): int
    {
        $all = (bool) $this->option('all');

        /** @var array<int, string> $models */
        $models = (array) config('laravel-password-rotation.models', []);

        $rows = [];

        foreach ($models as $class) {
            if (! class_exists($class) || ! is_a($class, MustRotatePassword::class, true) || ! is_a($class, Model::class, true)) {
                continue;
            }

            foreach ($class::query()->lazy() as $model) {
                assert($model instanceof MustRotatePassword);

                $expired = $model->passwordHasExpired();
                $expiresAt = $model->passwordExpiresAt();
                $expiring = $model->passwordIsExpiring();

                if (! $all && ! $expired && ! $expiring) {
                    continue;
                }

                $rows[] = [
                    $class.'#'.((string) $model->getKey()),
                    $model->passwordLastChangedAt()?->format('Y-m-d H:i') ?? '—',
                    $expiresAt?->format('Y-m-d H:i') ?? '—',
                    $expired ? 'expired' : ($expiring ? 'expiring soon' : 'ok'),
                ];
            }
        }

        if ($rows === []) {
            $this->info('No accounts to report.');

            return self::SUCCESS;
        }

        $this->table(['Identifier', 'Last changed', 'Expires at', 'Status'], $rows);

        return self::SUCCESS;
    }
}

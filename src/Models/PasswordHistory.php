<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Hash;

/**
 * @property string $authenticatable_type
 * @property int|string $authenticatable_id
 * @property string $password
 */
class PasswordHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'password_histories';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'password',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Store the (already hashed) current password and prune old entries.
     */
    public static function recordFor(Model $user): void
    {
        assert($user instanceof Authenticatable);

        static::query()->create([
            'authenticatable_type' => $user->getMorphClass(),
            'authenticatable_id' => $user->getKey(),
            'password' => $user->getAuthPassword(),
        ]);

        $count = (int) config('laravel-password-rotation.history_count');

        if ($count <= 0) {
            return;
        }

        // Keep count+1 rows: the newest is always the current password, so
        // count genuinely-previous passwords remain to reject reuse against.
        $keep = static::forAuthenticatable($user)
            ->orderByDesc('id')
            ->take($count + 1)
            ->pluck('id');

        static::forAuthenticatable($user)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * Whether the given plain password matches one of the last $count hashes.
     */
    public static function isReused(Model $user, string $plain, ?int $count = null): bool
    {
        $count ??= (int) config('laravel-password-rotation.history_count');

        if ($count <= 0) {
            return false;
        }

        // Check the newest count+1 hashes (current password + count previous),
        // so all count previous passwords are rejected, not just count-1.
        return static::forAuthenticatable($user)
            ->orderByDesc('id')
            ->take($count + 1)
            ->pluck('password')
            ->contains(fn (string $hash): bool => Hash::check($plain, $hash));
    }

    /**
     * @return Builder<static>
     */
    protected static function forAuthenticatable(Model $user): Builder
    {
        return static::query()
            ->where('authenticatable_type', $user->getMorphClass())
            ->where('authenticatable_id', $user->getKey());
    }
}

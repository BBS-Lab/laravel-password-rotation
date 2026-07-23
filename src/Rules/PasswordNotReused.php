<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation\Rules;

use BBSLab\LaravelPasswordRotation\Models\PasswordHistory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Translation\PotentiallyTranslatedString;

class PasswordNotReused implements ValidationRule
{
    public function __construct(
        private Model $user,
    ) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && PasswordHistory::isReused($this->user, $value)) {
            $fail('laravel-password-rotation::validation.reused')->translate();
        }
    }
}

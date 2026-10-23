<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class UserActionRateLimiter
{
    public function ensureAllowed(User $user, string $action, int $maxAttempts, int $decaySeconds = 60): void
    {
        $key = "user-action:{$action}:{$user->id}";

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'rate_limit' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}

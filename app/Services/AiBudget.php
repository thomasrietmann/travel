<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class AiBudget
{
    public function consume(int $userId): void
    {
        $key = 'ai:'.$userId.':'.now()->format('Y-m-d');
        // attempt uses the shared cache; an additional process lock makes check+increment atomic.
        app(ProcessLock::class)->run('ai-budget-'.$userId, function () use ($key): void {
            if (RateLimiter::tooManyAttempts($key, config('ai.daily_limit', 60))) {
                throw new RuntimeException('Das tägliche AI-Limit wurde erreicht.');
            }
            RateLimiter::hit($key, 86400);
        });
    }
}

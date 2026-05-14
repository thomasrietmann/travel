<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Task;
use App\Models\Trip;
use App\Policies\BookingPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TripPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Trip::class, TripPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }
}

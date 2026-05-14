<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        return $trip->user_id === $user->id;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip);
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $this->view($user, $trip);
    }
}

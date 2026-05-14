<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function update(User $user, Booking $booking): bool
    {
        return $booking->trip->user_id === $user->id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking);
    }
}

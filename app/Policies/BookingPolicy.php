<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function update(User $user, Booking $booking): bool
    {
        return $booking->trip->isAccessibleBy($user);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking);
    }
}

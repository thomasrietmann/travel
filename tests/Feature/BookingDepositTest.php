<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Trip;
use Tests\TestCase;

class BookingDepositTest extends TestCase
{
    public function test_partial_payment_updates_the_trip_balance_through_status_changes(): void
    {
        config(['exchange.rates_to_chf.EUR' => 0.915]);
        $trip = Trip::factory()->create();
        $this->actingAs($trip->user);
        Booking::factory()->for($trip)->create(['currency' => 'CHF', 'amount' => 30, 'payment_status' => 'unpaid']);
        $data = [
            'title' => 'Hotel', 'category' => 'hotel', 'booking_status' => 'confirmed',
            'amount' => '160.00', 'currency' => 'EUR', 'payment_status' => 'partially_paid',
            'deposit_amount' => '40.00',
        ];
        $this->post(route('trips.bookings.store', $trip), $data)->assertRedirect(route('trips.show', $trip));
        $booking = $trip->bookings()->where('title', 'Hotel')->sole();
        $this->assertSame('40.00', $booking->deposit_amount);
        $this->assertSame(150.0, $trip->open_amount);
        $this->assertSame(40.0, $trip->paid_amount);
        $this->get(route('trips.show', $trip))->assertOk()->assertSeeText('Anzahlung 40.00 EUR')
            ->assertSeeText('Offen 120.00 EUR')->assertSeeText('139.80 CHF')->assertSeeText('36.60 CHF');
        $this->get(route('bookings.edit', $booking))->assertOk()->assertSee('value="40.00"', false);

        $this->put(route('bookings.update', $booking), [...$data, 'deposit_amount' => '70.00'])->assertSessionHasNoErrors();
        $this->assertSame(112.35, $trip->refresh()->open_amount_chf);
        $this->put(route('bookings.update', $booking), [...$data, 'payment_status' => 'paid'])->assertSessionHasNoErrors();
        $this->assertNull($booking->refresh()->deposit_amount);
        $this->assertSame(30.0, $trip->refresh()->open_amount_chf);
        $this->assertSame(146.4, $trip->paid_amount_chf);
        $this->put(route('bookings.update', $booking), [...$data, 'payment_status' => 'unpaid'])->assertSessionHasNoErrors();
        $this->assertNull($booking->refresh()->deposit_amount);
        $this->assertSame(176.4, $trip->refresh()->open_amount_chf);
    }

    public function test_partial_bookings_allow_an_unknown_deposit_and_validate_entered_amounts(): void
    {
        $trip = Trip::factory()->create();
        $this->actingAs($trip->user);
        $data = [
            'title' => 'Zimmer', 'category' => 'hotel', 'booking_status' => 'confirmed',
            'amount' => '75.50', 'currency' => 'CHF', 'payment_status' => 'partially_paid',
        ];
        $this->post(route('trips.bookings.store', $trip), $data)->assertSessionHasNoErrors();
        $booking = $trip->bookings()->sole();
        foreach (['-10', '0', '1.234', '75.50', '76', 'text'] as $invalid) {
            $this->put(route('bookings.update', $booking), [...$data, 'deposit_amount' => $invalid])
                ->assertSessionHasErrors('deposit_amount');
        }
        $this->assertNull($booking->refresh()->deposit_amount);
        $this->assertSame(75.50, $trip->open_amount_chf);
    }
}

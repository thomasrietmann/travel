<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Trip;
use App\Models\User;
use App\Services\StagingInventory;
use Tests\TestCase;

class TripItineraryTest extends TestCase
{
    private function booking(Trip $trip, string $category, string $title, ?string $from, ?string $to, array $extra = []): Booking
    {
        return Booking::factory()->for($trip)->create([
            'category' => $category, 'title' => $title, 'start_date' => $from, 'end_date' => $to,
            'booking_status' => 'confirmed', 'amount' => 100, 'currency' => 'CHF', ...$extra,
        ]);
    }

    public function test_existing_bookings_form_a_day_plan_without_materialising_or_combining_them(): void
    {
        $trip = Trip::factory()->create(['start_date' => '2027-06-06', 'end_date' => '2027-06-15']);
        $this->booking($trip, 'ticket', 'Jahrespass', '2027-01-01', '2027-12-31');
        $this->booking($trip, 'flight', 'Hin- und Rückflug', '2027-06-06', '2027-06-15');
        $this->booking($trip, 'hotel', 'Hotel am Flughafen', '2027-06-06', '2027-06-08');
        $first = $this->booking($trip, 'flight', 'Flug A', '2027-06-08', '2027-06-08', ['booking_reference' => 'SAMPLE']);
        $this->booking($trip, 'flight', 'Flug B', '2027-06-08', '2027-06-08', ['booking_reference' => 'SAMPLE']);
        $this->booking($trip, 'car', 'Mietwagen', '2027-06-08', '2027-06-14');
        $this->booking($trip, 'hotel', 'Hotel nahe Park', '2027-06-08', null);
        $this->booking($trip, 'activity', 'Offener Besuch', null, null);
        $this->booking($trip, 'hotel', 'Nur Abreisedatum', null, '2027-06-14');
        $this->booking($trip, 'flight', 'Stornierter Flug', '2027-05-01', null, ['booking_status' => 'cancelled']);
        $file = Document::factory()->for($trip)->for($first)->create(['title' => 'Flugticket']);

        $this->actingAs($trip->user);
        $response = $this->get(route('trips.show', [$trip, 'tag' => '2027-06-08']))->assertOk()
            ->assertViewHas('openDay', '2027-06-08')->assertSeeText('2 Buchungen mit dieser Referenz')
            ->assertSee(route('documents.download', $file))->assertSeeText('Buchungsliste (10)');
        $plan = $response->viewData('plan');
        $this->assertSame(['2027-06-06', '2027-06-08', '2027-06-14'], $plan['days']->keys()->all());
        $this->assertSame(['Check-out', 'Flug', 'Flug', 'Abholung', 'Check-in'], $plan['days']['2027-06-08']->pluck('label')->all());
        $this->assertSame(['Check-out', 'Rückgabe'], $plan['days']['2027-06-14']->pluck('label')->all());
        $this->assertEqualsCanonicalizing(['Jahrespass', 'Hin- und Rückflug', 'Offener Besuch'], collect($plan['unplaced'])->pluck('booking.title')->all());
        $flights = $this->get(route('trips.show', [$trip, 'plan' => 'fluege']))->assertOk()->viewData('plan');
        $this->assertSame(['2027-06-08'], $flights['days']->keys()->all());
        $this->assertSame(['Flug A', 'Flug B'], $flights['days']['2027-06-08']->pluck('title')->all());
        $this->assertSame(['Hin- und Rückflug'], collect($flights['unplaced'])->pluck('booking.title')->all());
        $this->assertDatabaseCount('itinerary_stops', 0);
        $this->assertSame(1000.0, $trip->total_amount_chf);
    }

    public function test_multiple_legs_of_one_booking_use_their_local_dates_times_and_one_price(): void
    {
        $trip = Trip::factory()->create(['start_date' => '2027-06-06', 'end_date' => '2027-06-15']);
        $flight = $this->booking($trip, 'flight', 'Flugreservation', '2027-06-06', '2027-06-15');
        $this->actingAs($trip->user);
        $url = route('trips.stops.store', $trip);
        $this->get(route('trips.stops.create', [$trip, 'booking' => $flight->id]))->assertOk()->assertSeeText('Flugnummer oder Bezeichnung');
        $this->post($url, [
            'booking_id' => $flight->id, 'title' => 'TC 101', 'start_date' => '2027-06-06',
            'start_time' => '09:05', 'start_location' => 'ZRH', 'start_timezone' => 'Europe/Zurich',
            'end_date' => '2027-06-06', 'end_time' => '12:10', 'end_location' => 'EWR', 'end_timezone' => 'America/New_York',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, [
            'booking_id' => $flight->id, 'title' => 'TC 102', 'start_date' => '2027-06-14',
            'start_time' => '19:00', 'start_location' => 'YYZ', 'start_timezone' => 'America/Toronto',
            'end_date' => '2027-06-15', 'end_time' => '08:00', 'end_location' => 'ZRH', 'end_timezone' => 'Europe/Zurich',
        ])->assertSessionHasNoErrors();
        $this->post($url, ['title' => 'Transfer zum Hotel', 'start_date' => '2027-06-06', 'start_time' => '12:30', 'start_timezone' => 'America/New_York'])->assertSessionHasNoErrors();
        $response = $this->get(route('trips.show', $trip))->assertOk()->assertSeeText('ZRH → EWR')->assertSeeText('Ankunft 12:10 America/New_York');
        $plan = $response->viewData('plan');
        $this->assertSame([], $plan['unplaced']);
        $this->assertSame(['TC 101', 'Transfer zum Hotel'], $plan['days']['2027-06-06']->pluck('title')->all());
        $this->assertSame(['Flug'], $plan['days']['2027-06-14']->pluck('label')->all());
        $this->assertSame(['Ankunft'], $plan['days']['2027-06-15']->pluck('label')->all());
        $this->assertSame(100.0, $trip->total_amount_chf);
        $this->assertSame(2, $flight->stops()->count());

        foreach ($flight->stops as $leg) {
            $this->delete(route('trips.stops.destroy', [$trip, $leg]))->assertRedirect();
        }
        $this->assertSame(['Flugreservation'], collect($this->get(route('trips.show', $trip))->viewData('plan')['unplaced'])->pluck('booking.title')->all());
        $this->assertDatabaseHas('bookings', ['id' => $flight->id, 'amount' => 100]);
    }

    public function test_stops_can_be_rescheduled_and_are_scoped_to_shared_trips(): void
    {
        $trip = Trip::factory()->create();
        $other = Trip::factory()->create();
        $foreign = $this->booking($other, 'flight', 'Andere Reise', null, null);
        $member = User::factory()->create();
        $trip->sharedUsers()->attach($member);
        $this->actingAs($member);
        $data = ['title' => 'Parkplatz am Westeingang', 'start_date' => '2027-06-08', 'start_location' => 'Tor West'];
        $this->post(route('trips.stops.store', $trip), $data)->assertSessionHasNoErrors();
        $stop = $trip->stops()->sole();
        $this->post(route('trips.stops.store', $trip), [...$data, 'booking_id' => $foreign->id])->assertSessionHasErrors('booking_id');
        $this->get(route('trips.stops.create', [$trip, 'booking' => $foreign->id]))->assertNotFound();
        $this->post(route('trips.stops.store', $other), $data)->assertForbidden();
        $this->get(route('trips.stops.edit', [$trip, $stop]))->assertOk()->assertSee('Tor West');
        $this->put(route('trips.stops.update', [$trip, $stop]), [...$data, 'start_date' => '2027-06-10'])
            ->assertRedirect(route('trips.show', [$trip, 'tag' => '2027-06-10']).'#tagesplan');
        $this->assertSame(['2027-06-10'], $this->get(route('trips.show', $trip))->viewData('plan')['days']->keys()->all());
        $this->put(route('trips.stops.update', [$trip, $stop]), [...$data, 'booking_id' => $foreign->id])->assertSessionHasErrors('booking_id');
        $this->actingAs($other->user)->delete(route('trips.stops.destroy', [$other, $stop]))->assertNotFound();
        $this->delete(route('trips.stops.destroy', [$trip, $stop]))->assertForbidden();
        $this->actingAs($member)->delete(route('trips.stops.destroy', [$trip, $stop]))->assertRedirect();
        $this->assertDatabaseCount('itinerary_stops', 0);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_local_dates_across_the_date_line_and_inventory_of_manual_entries(): void
    {
        $trip = Trip::factory()->create();
        $this->actingAs($trip->user);
        $url = route('trips.stops.store', $trip);
        $data = [
            'title' => 'Über die Datumsgrenze', 'start_date' => '2027-06-08', 'start_time' => '00:40', 'start_timezone' => 'Asia/Tokyo',
            'end_date' => '2027-06-07', 'end_time' => '14:00', 'end_timezone' => 'Pacific/Honolulu',
        ];
        $this->post($url, [...$data, 'end_timezone' => 'Bad/Zone'])->assertSessionHasErrors('end_timezone');
        $this->post($url, [...$data, 'end_time' => '04:00'])->assertSessionHasErrors('end_time');
        $this->post($url, [...$data, 'end_date' => null])->assertSessionHasErrors('end_date');
        $this->post($url, [...$data, 'end_date' => $data['start_date'], 'end_time' => '00:10', 'end_timezone' => null])->assertSessionHasErrors('end_time');
        $before = app(StagingInventory::class)->capture();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $after = app(StagingInventory::class)->capture($before);
        $this->assertSame(0, $before['tables']['itinerary_stops']['count']);
        $this->assertSame(1, $after['tables']['itinerary_stops']['count']);
        $this->assertSame(['2027-06-07', '2027-06-08'], $this->get(route('trips.show', $trip))->viewData('plan')['days']->keys()->all());
    }

    public function test_a_season_pass_can_be_used_on_two_visit_days_inside_its_validity_period(): void
    {
        $trip = Trip::factory()->create(['start_date' => '2027-06-06', 'end_date' => '2027-06-15']);
        $pass = $this->booking($trip, 'ticket', 'Saisonpass', '2027-01-01', '2027-12-31');
        $this->actingAs($trip->user)->get(route('trips.stops.create', [$trip, 'booking' => $pass->id]))
            ->assertOk()->assertViewHas('stop', fn ($stop) => $stop->start_date->toDateString() === '2027-06-06' && $stop->end_date === null);
        foreach (['2027-06-08', '2027-06-12'] as $date) {
            $this->post(route('trips.stops.store', $trip), ['booking_id' => $pass->id, 'title' => 'Parkbesuch', 'start_date' => $date])->assertSessionHasNoErrors();
        }
        $plan = $this->get(route('trips.show', $trip))->assertOk()->viewData('plan');
        $this->assertSame(['2027-06-08', '2027-06-12'], $plan['days']->keys()->all());
        $this->assertSame([], $plan['unplaced']);
        $this->assertSame(100.0, $trip->total_amount_chf);
        $this->assertSame('2027-12-31', $pass->fresh()->end_date->toDateString());
    }

    public function test_changing_a_hotel_reservation_moves_its_check_in_and_check_out_with_their_times(): void
    {
        $trip = Trip::factory()->create();
        $hotel = $this->booking($trip, 'hotel', 'Parkhotel', '2027-06-07', '2027-06-09');
        $this->actingAs($trip->user);
        $this->post(route('trips.stops.store', $trip), [
            'booking_id' => $hotel->id, 'title' => 'Parkhotel', 'start_date' => '2027-06-07', 'start_time' => '15:00',
            'end_date' => '2027-06-09', 'end_time' => '10:00', 'start_location' => 'Eingang West',
        ])->assertSessionHasNoErrors();
        $this->put(route('bookings.update', $hotel), [
            'title' => 'Parkhotel', 'category' => 'hotel', 'booking_status' => 'confirmed', 'amount' => 100, 'currency' => 'CHF',
            'payment_status' => 'paid', 'start_date' => '2027-06-08', 'end_date' => '2027-06-10',
        ])->assertSessionHasNoErrors();
        $days = $this->get(route('trips.show', $trip))->assertOk()->viewData('plan')['days'];
        $this->assertSame(['2027-06-08', '2027-06-10'], $days->keys()->all());
        $this->assertSame('15:00', $days->first()->sole()['time']);
        $this->assertSame('10:00', $days->last()->sole()['time']);
        $this->assertSame('Eingang West', $days->last()->sole()['location']);
    }
}

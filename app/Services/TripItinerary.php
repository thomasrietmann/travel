<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ItineraryStop;
use App\Models\Trip;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;

class TripItinerary
{
    public function forTrip(Trip $trip, bool $flightsOnly = false): array
    {
        $bookings = $trip->bookings->where('booking_status', '!=', 'cancelled');
        $flights = $bookings->where('category', 'flight');
        $stopsByBooking = $trip->stops->groupBy('booking_id');
        $events = [];
        $unplaced = [];

        foreach ($flightsOnly ? $flights : $bookings as $booking) {
            $stops = $stopsByBooking->get($booking->id);
            if ($stops) {
                foreach ($stops as $stop) {
                    array_push($events, ...$this->events($booking, $stop));
                }
            } elseif ($reason = $this->unplacedReason($booking)) {
                $unplaced[] = ['booking' => $booking, 'reason' => $reason];
            } else {
                array_push($events, ...$this->events($booking, null));
            }
        }
        if (! $flightsOnly) {
            foreach ($trip->stops->whereNull('booking_id') as $stop) {
                array_push($events, ...$this->events(null, $stop));
            }
        }

        return [
            'days' => collect($events)->groupBy('date')->sortKeys()->map($this->sortDay(...)),
            'unplaced' => $unplaced,
            'hasFlights' => $flights->isNotEmpty(),
            'flightReferences' => $flights->whereNotNull('booking_reference')->countBy('booking_reference'),
        ];
    }

    private function unplacedReason(Booking $booking): ?string
    {
        if (! $booking->start_date && ! $booking->end_date) {
            return 'Ohne Datum';
        }
        $spansDays = $booking->start_date && $booking->end_date && $booking->start_date->ne($booking->end_date);

        return match (true) {
            $spansDays && $booking->category === 'flight' => 'Flugbuchung ohne Teilstrecken',
            $spansDays && $booking->category === 'ticket' => 'Ticket mit Gültigkeitszeitraum',
            $booking->category === 'insurance' => 'Versicherungsschutz',
            default => null,
        };
    }

    /** Project existing booking dates, or the local dates of an explicitly planned stop. */
    private function events(?Booking $booking, ?ItineraryStop $stop): array
    {
        $source = $stop ?? $booking;
        $start = $source->start_date?->toDateString();
        $end = $source->end_date?->toDateString();
        $category = $booking?->category;
        $base = [
            'title' => $source->title, 'booking' => $booking, 'stop' => $stop,
            'endClock' => null, 'endZone' => null,
        ];
        $startEvent = [
            ...$base, 'date' => $start, 'time' => $this->clock($stop?->start_time),
            'zone' => $stop?->start_timezone, 'location' => $stop?->start_location,
        ];
        $endEvent = [
            ...$base, 'date' => $end, 'time' => $this->clock($stop?->end_time),
            'zone' => $stop?->end_timezone, 'location' => $stop?->end_location,
        ];
        [$begin, $finish, $startOrder, $endOrder] = match ($category) {
            'hotel' => ['Check-in', 'Check-out', 60, 10],
            'car', 'camper' => ['Abholung', 'Rückgabe', 40, 20],
            'flight' => ['Flug', 'Ankunft', 30, 30],
            default => [$booking?->category_label ?? 'Stopp', 'Ende', 50, 50],
        };

        $places = array_filter([$stop?->start_location, $stop?->end_location], fn ($place) => filled($place));
        if ($category === 'flight' || $end === $start || ! $end) {
            $startEvent['location'] = implode(' → ', $places);
        }
        if ($category === 'hotel') {
            $endEvent['location'] ??= $stop?->start_location;
        }
        if ($start && $start === $end) {
            return [[
                ...$startEvent, 'label' => $booking?->category_label ?? 'Stopp', 'order' => $startOrder,
                'endClock' => $endEvent['time'], 'endZone' => $endEvent['zone'],
            ]];
        }

        $events = [];
        if ($start) {
            $events[] = [...$startEvent, 'label' => $begin, 'order' => $startOrder];
        }
        if ($end) {
            $events[] = [...$endEvent, 'label' => $finish, 'order' => $endOrder];
        }

        return $events;
    }

    private function clock(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    private function sortDay(Collection $events): Collection
    {
        $zones = $events->whereNotNull('time')->pluck('zone')->filter()->unique();
        $localZone = $zones->count() === 1 ? $zones->first() : null;

        return $events->sortBy(function (array $event) use ($localZone): array {
            $time = $event['time'];
            $zone = $event['zone'] ?? $localZone;
            $instant = $time && $zone ? new DateTimeImmutable($event['date'].' '.$time, new DateTimeZone($zone)) : null;

            return [$time === null, $instant === null, $instant?->getTimestamp() ?? $time, $event['order'], $event['title'], $event['stop']?->id ?? $event['booking']?->id];
        })->values();
    }
}

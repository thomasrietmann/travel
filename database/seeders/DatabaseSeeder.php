<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Task;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->create([
            'name' => 'TripControl Demo',
            'email' => 'demo@tripcontrol.test',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        User::query()->create([
            'name' => 'TripControl Admin',
            'email' => 'admin@tripcontrol.test',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $florida = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Florida Coastertrip 2025',
            'type' => 'coastertrip',
            'destination' => 'Florida',
            'start_date' => '2025-10-03',
            'end_date' => '2025-10-17',
            'status' => 'planned',
            'notes' => 'Coasterfokus mit Orlando-Parks und flexiblen Zusatzoptionen.',
        ]);

        $this->booking($florida, 'flight', 'Flug', 'Airline', 2414, 'CHF', 'confirmed', 'paid');
        $this->booking($florida, 'ticket', 'SeaWorld + Busch Gardens Two-Park Ticket', 'SeaWorld Parks', 263.84, 'USD', 'confirmed', 'paid');
        $this->booking($florida, 'ticket', 'Universal Express Pass', 'Universal Orlando', 0, 'USD', 'open', 'unpaid');
        $this->booking($florida, 'activity', 'VIP Experience', 'Universal Orlando', 0, 'USD', 'open', 'unpaid');
        $this->task($florida, 'ESTA beantragen', 'high', '2025-08-15');
        $this->task($florida, 'Uber Budget festlegen', 'medium', '2025-09-01');

        $phantasialand = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Phantasialand September 2025',
            'type' => 'coastertrip',
            'destination' => 'Bruehl',
            'start_date' => '2025-09-24',
            'end_date' => '2025-09-26',
            'status' => 'booked',
            'notes' => 'Kurztrip mit Hotel Charles Lindbergh.',
        ]);

        $this->booking($phantasialand, 'hotel', 'Hotel Charles Lindbergh', 'Phantasialand', 0, 'EUR', 'confirmed', 'unpaid');
        $this->task($phantasialand, 'Tickets und QR-Codes speichern', 'high', '2025-09-10');

        $sweden = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Schwedenrundreise Sommer',
            'type' => 'family_camper',
            'destination' => 'Sued-Schweden',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-20',
            'status' => 'planned',
            'notes' => 'Start und Ende Arlandastad bei Stockholm, Stockholm wird ausgelassen, Fokus Natur und kinderfreundliche Abenteuer, taegliche Fahrzeit max. 2-3 Stunden.',
        ]);

        $this->booking($sweden, 'camper', 'Camper Touring Cars', 'Touring Cars', 0, 'SEK', 'requested', 'unpaid');
        $this->task($sweden, 'Campingplaetze pruefen', 'medium', '2026-05-30');
        $this->task($sweden, 'Packliste Familie erstellen', 'medium', '2026-06-15');

        $norway = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Norwegen Camperreise 2026',
            'type' => 'family_camper',
            'destination' => 'Norwegen',
            'start_date' => '2026-07-21',
            'end_date' => '2026-08-04',
            'status' => 'planned',
            'notes' => 'Start Trondheim, Abgabe Malvik, Fokus Natur, Kinder 4 und 6 Jahre.',
        ]);

        $this->task($norway, 'Restzahlung Camper pruefen', 'high', '2026-06-01');
        $this->task($norway, 'Etappen-Dokumente sammeln', 'medium', '2026-06-20');

        $switzerland = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Schweiz-Rundreise',
            'type' => 'roadtrip',
            'destination' => 'Schweiz',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-18',
            'status' => 'planned',
            'notes' => 'Morschach Swiss Holiday Park, Luzern Hotel Cassarate, Melchsee-Frutt Frutt Mountain Lodge.',
        ]);

        $this->task($switzerland, 'Buchungsbestaetigungen hochladen', 'medium', '2026-07-15');
    }

    private function booking(
        Trip $trip,
        string $category,
        string $title,
        ?string $provider,
        float $amount,
        string $currency,
        string $bookingStatus,
        string $paymentStatus
    ): Booking {
        return Booking::query()->create([
            'trip_id' => $trip->id,
            'category' => $category,
            'title' => $title,
            'provider' => $provider,
            'amount' => $amount,
            'currency' => $currency,
            'booking_status' => $bookingStatus,
            'payment_status' => $paymentStatus,
        ]);
    }

    private function task(Trip $trip, string $title, string $priority, ?string $dueDate = null): Task
    {
        return Task::query()->create([
            'trip_id' => $trip->id,
            'title' => $title,
            'priority' => $priority,
            'due_date' => $dueDate,
            'status' => 'open',
        ]);
    }
}

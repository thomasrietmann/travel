<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingFlightImporter;
use App\Services\ProcessLock;
use App\Services\TripSummaryGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class FlightImportTest extends TestCase
{
    private function flight(): Booking
    {
        $trip = Trip::factory()->create(['title' => 'CoasterTrip 2027', 'start_date' => '2027-06-06', 'end_date' => '2027-06-15']);

        return Booking::factory()->for($trip)->create([
            'category' => 'flight', 'title' => 'Swiss Hin- und Rückflug',
            'start_date' => '2027-06-06', 'end_date' => '2027-06-15', 'amount' => 8448.90, 'currency' => 'CHF',
            'booking_status' => 'confirmed', 'payment_status' => 'paid',
        ]);
    }

    private function document(Booking $booking, string $disk = 'local', string $contents = "%PDF-1.4\nSwiss booking"): Document
    {
        $document = Document::factory()->create([
            'trip_id' => $booking->trip_id, 'booking_id' => $booking->id, 'document_type' => 'confirmation',
            'file_path' => 'documents/'.$booking->trip_id.'/'.bin2hex(random_bytes(8)).'.pdf',
        ]);
        Storage::disk($disk)->put($document->file_path, $contents);

        return $document;
    }

    private function segments(): array
    {
        return [
            ['title' => 'LX 18', 'start_date' => '2027-06-06', 'start_time' => '09:40', 'start_location' => 'ZRH', 'start_timezone' => 'Europe/Zurich',
                'end_date' => '2027-06-06', 'end_time' => '12:10', 'end_location' => 'EWR', 'end_timezone' => 'America/New_York'],
            ['title' => 'LX 19', 'start_date' => '2027-06-14', 'start_time' => '20:30', 'start_location' => 'YYZ', 'start_timezone' => 'America/Toronto',
                'end_date' => '2027-06-15', 'end_time' => '10:20', 'end_location' => 'ZRH', 'end_timezone' => 'Europe/Zurich'],
        ];
    }

    private function fakeExtraction(array $segments): void
    {
        config(['ai.openai.api_key' => 'test-only']);
        Http::fake([
            'api.openai.com/v1/files' => Http::response(['id' => 'file-flight-test']),
            'api.openai.com/v1/files/file-flight-test' => Http::response(['deleted' => true]),
            'api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
                'title' => 'Swiss', 'category' => 'flight', 'flight_segments' => $segments,
            ])]),
        ]);
    }

    public function test_existing_swiss_booking_gets_local_outbound_and_overnight_return_once(): void
    {
        $booking = $this->flight();
        $this->document($booking, 'public');
        $this->fakeExtraction($this->segments());
        $this->actingAs($booking->trip->user);
        $url = route('bookings.flights.import', $booking);
        $this->post($url)->assertRedirect(route('trips.show', $booking->trip).'#tagesplan');
        $this->assertSame(8448.90, (float) $booking->fresh()->amount);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('itinerary_stops', 2);
        $this->assertDatabaseHas('booking_flight_extractions', ['status' => 'completed', 'booking_id' => $booking->id]);
        $this->get(route('trips.show', $booking->trip))->assertOk()->assertSeeText('LX 18')->assertSeeText('09:40')->assertSeeText('America/New_York');
        $plan = $this->get(route('trips.show', [$booking->trip, 'plan' => 'fluege']))->assertOk()->viewData('plan');
        $this->assertSame(['2027-06-06', '2027-06-14', '2027-06-15'], $plan['days']->keys()->all());
        $this->assertSame([], $plan['unplaced']);
        $this->get(route('bookings.edit', $booking))->assertOk()->assertSeeText('Neue Unterlagen prüfen');

        $booking->stops()->where('title', 'LX 18')->firstOrFail()->update(['start_time' => '10:05']);
        $booking->stops()->where('title', 'LX 19')->firstOrFail()->delete();
        Cache::flush();
        $this->post($url)->assertRedirect();
        $this->assertDatabaseCount('itinerary_stops', 1);
        $this->assertSame('10:05', substr($booking->stops()->sole()->start_time, 0, 5));
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/responses') && $request['store'] === false);
    }

    public function test_empty_results_are_persisted_without_repeated_ai_requests(): void
    {
        $booking = $this->flight();
        $this->document($booking);
        $this->fakeExtraction([]);
        $service = app(BookingFlightImporter::class);
        $this->assertSame(0, $service->extract($booking)['created']);
        $this->assertSame('completed', $booking->flightExtractions()->sole()->status);
        $this->assertSame(1, $service->extract($booking)['cached']);
        $this->assertDatabaseCount('itinerary_stops', 0);
        Http::assertSentCount(3);
    }

    public function test_invalid_results_require_review_without_another_ai_call(): void
    {
        $other = $this->flight();
        $this->document($other);
        $invalid = $this->segments();
        $invalid[0]['end_date'] = '2027-06-05';
        $this->fakeExtraction($invalid);
        $service = app(BookingFlightImporter::class);
        $this->assertSame(1, $service->extract($other)['review']);
        $this->assertSame(1, $service->extract($other)['review']);
        $this->assertDatabaseCount('itinerary_stops', 0);
        $this->assertSame('review', $other->flightExtractions()->sole()->status);
        Http::assertSentCount(3);
    }

    public function test_file_hashes_reuse_duplicates_and_only_new_content_calls_ai(): void
    {
        $booking = $this->flight();
        $this->document($booking);
        $this->document($booking);
        $this->fakeExtraction($this->segments());
        $service = app(BookingFlightImporter::class);
        $stats = $service->extract($booking);
        $this->assertSame(2, $stats['created']);
        $this->assertSame(1, $stats['cached']);
        Http::assertSentCount(3);
        $this->document($booking, 'local', "%PDF-1.4\nUpdated Swiss ticket");
        $this->assertSame(0, $service->extract($booking)['created']);
        $this->assertDatabaseCount('itinerary_stops', 2);
        $this->assertDatabaseCount('booking_flight_extractions', 2);
        Http::assertSentCount(6);
    }

    public function test_saved_response_resumes_without_ai_and_open_attempts_are_not_repeated(): void
    {
        $booking = $this->flight();
        $document = $this->document($booking);
        $record = $booking->flightExtractions()->create([
            'document_id' => $document->id, 'document_path' => $document->file_path,
            'content_hash' => hash('sha256', Storage::disk('local')->get($document->file_path)),
            'status' => 'extracted', 'segments' => $this->segments(),
        ]);
        $this->assertSame(2, app(BookingFlightImporter::class)->extract($booking)['created']);
        $this->assertSame('completed', $record->fresh()->status);
        $record->update(['status' => 'processing']);
        $this->assertSame(1, app(BookingFlightImporter::class)->extract($booking, true)['failed']);
        Http::assertNothingSent();
    }

    public function test_api_failures_require_an_explicit_retry(): void
    {
        $booking = $this->flight();
        $this->document($booking);
        config(['ai.openai.api_key' => 'test-only']);
        $attempts = 0;
        Http::fake(function ($request) use (&$attempts) {
            if (str_ends_with($request->url(), '/responses')) {
                $attempts++;

                return $attempts === 1 ? Http::response('private error', 500)
                    : Http::response(['output_text' => json_encode(['flight_segments' => $this->segments()])]);
            }

            return Http::response(['id' => 'file-flight-test', 'deleted' => true]);
        });
        $service = app(BookingFlightImporter::class);
        $this->assertSame(1, $service->extract($booking)['failed']);
        $this->assertSame(1, $service->extract($booking)['failed']);
        $this->assertSame(1, $attempts);
        $this->assertSame(2, $service->extract($booking, true)['created']);
        $this->assertSame(2, $attempts);
        $this->assertSame(1, $service->extract($booking, true)['cached']);
        $this->assertSame(2, $attempts);
    }

    public function test_concurrent_attempts_are_stopped_before_an_api_call(): void
    {
        $booking = $this->flight();
        $this->document($booking);
        app(ProcessLock::class)->run('booking-flights-'.$booking->id, function () use ($booking): void {
            try {
                app(BookingFlightImporter::class)->extract($booking);
                $this->fail('A concurrent extraction must be blocked.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Dieser Vorgang läuft bereits.', $exception->getMessage());
            }
        });
        Http::assertNothingSent();
    }

    public function test_only_authorized_flight_bookings_can_extract_their_attached_documents(): void
    {
        $booking = $this->flight();
        $this->document($booking);
        $url = route('bookings.flights.import', $booking);
        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post($url)->assertForbidden();
        $member = User::factory()->create();
        $booking->trip->sharedUsers()->attach($member);
        $this->actingAs($member);
        $booking->update(['category' => 'hotel']);
        $this->post($url)->assertUnprocessable();
        $booking->update(['category' => 'flight']);
        $booking->documents()->update(['document_type' => 'passport']);
        $this->post($url)->assertRedirect()->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_new_web_ai_import_already_contains_segments_and_needs_no_backfill_call(): void
    {
        $trip = $this->flight()->trip;
        $this->fakeExtraction($this->segments());
        $this->mock(TripSummaryGenerator::class)->shouldReceive('regenerate')->once()->andReturnNull();
        $this->actingAs($trip->user)->post(route('trips.bookings.import', $trip), [
            'booking_documents' => [UploadedFile::fake()->createWithContent('swiss.pdf', "%PDF-1.4\nSwiss ticket")],
        ])->assertRedirect(route('trips.show', $trip));
        $imported = $trip->bookings()->latest('id')->firstOrFail();
        $this->assertSame(2, $imported->stops()->count());
        $this->assertSame('completed', $imported->flightExtractions()->sole()->status);
        $this->assertSame(1, app(BookingFlightImporter::class)->extract($imported)['cached']);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/responses')
            && isset($request['text']['format']['schema']['properties']['flight_segments'])
            && in_array('flight_segments', $request['text']['format']['schema']['required'], true));
    }
}

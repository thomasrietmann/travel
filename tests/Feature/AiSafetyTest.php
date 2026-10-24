<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Trip;
use App\Services\AiBudget;
use App\Services\BookingAiExtractor;
use App\Services\TripSummaryGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AiSafetyTest extends TestCase
{
    public function test_ai_responses_are_not_stored_and_uploads_expire_and_are_deleted(): void
    {
        config(['ai.openai.api_key' => 'test-only']);
        Http::fake([
            'api.openai.com/v1/files' => Http::response(['id' => 'file-test']),
            'api.openai.com/v1/responses' => Http::response(['output_text' => '{"title":"Ticket"}']),
            'api.openai.com/v1/files/file-test' => Http::response(['deleted' => true]),
        ]);
        $trip = Trip::factory()->create();
        $result = app(BookingAiExtractor::class)->extract($trip, UploadedFile::fake()->createWithContent('ticket.pdf', "%PDF-1.4\nticket"));
        $this->assertSame('Ticket', $result['title']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/responses') && $request['store'] === false);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/files') && $request->hasFile('expires_after[seconds]', '3600') && $request->hasFile('expires_after[anchor]', 'created_at'));
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/file-test'));
    }

    public function test_api_error_details_are_hidden_and_file_cleanup_still_runs(): void
    {
        config(['ai.openai.api_key' => 'test-only']);
        Http::fake([
            'api.openai.com/v1/files' => Http::response(['id' => 'file-test']),
            'api.openai.com/v1/responses' => Http::response('private-data-secret', 500),
            'api.openai.com/v1/files/file-test' => Http::response(['deleted' => true]),
        ]);
        try {
            app(BookingAiExtractor::class)->extract(Trip::factory()->create(), UploadedFile::fake()->createWithContent('ticket.pdf', "%PDF-1.4\nticket"));
            $this->fail('API error must propagate as a generic failure');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('private-data-secret', $exception->getMessage());
        }
        Http::assertSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_daily_ai_limit_blocks_requests_before_upload(): void
    {
        config(['ai.openai.api_key' => 'test-only', 'ai.daily_limit' => 1]);
        $trip = Trip::factory()->create();
        app(AiBudget::class)->consume($trip->user_id);
        try {
            app(BookingAiExtractor::class)->extract($trip, UploadedFile::fake()->createWithContent('ticket.pdf', "%PDF-1.4\nticket"));
            $this->fail('Quota should block external requests');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public function test_summary_does_not_send_booking_references_or_private_notes(): void
    {
        config(['ai.openai.api_key' => 'test-only']);
        $trip = Trip::factory()->create(['notes' => 'private-note-123']);
        Booking::factory()->for($trip)->create(['booking_reference' => 'reservation-secret-789']);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output_text' => 'Eine Reise.'])]);
        $this->assertSame('Eine Reise.', app(TripSummaryGenerator::class)->regenerate($trip));
        Http::assertSent(fn ($request) => $request['store'] === false
            && ! str_contains($request->body(), 'private-note-123') && ! str_contains($request->body(), 'reservation-secret-789'));
    }
}

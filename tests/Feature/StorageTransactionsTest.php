<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Trip;
use App\Services\DocumentStorage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StorageTransactionsTest extends TestCase
{
    public function test_successful_replacement_keeps_original_until_cleanup(): void
    {
        $document = Document::factory()->create();
        $old = $document->file_path;
        Storage::disk('local')->put($old, 'old');
        Storage::disk('public')->put($old, 'old');
        $bytes = "%PDF-1.4\nnew";
        $this->actingAs($document->trip->user)->put(route('documents.update', $document), [
            'title' => 'Replacement', 'document_type' => 'confirmation',
            'file' => UploadedFile::fake()->createWithContent('new.pdf', $bytes),
        ])->assertRedirect();
        $new = $document->fresh()->file_path;
        $this->assertNotSame($old, $new);
        $this->assertSame($bytes, Storage::disk('local')->get($new));
        Storage::disk('local')->assertExists($old);
        Storage::disk('public')->assertExists($old);
        $this->artisan('documents:cleanup')->assertSuccessful();
        Storage::disk('local')->assertExists($new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_database_failure_after_upload_rolls_back_all_document_rows(): void
    {
        $trip = Trip::factory()->create();
        Document::creating(fn () => throw new RuntimeException('Simulated DB write failure'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($trip->user)->post(route('trips.documents.store', $trip), [
                'document_type' => 'confirmation', 'files' => [UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\nnew")],
            ]);
            $this->fail('DB failure must abort upload');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('documents', 0);
            $this->assertDatabaseCount('document_file_deletions', 1);
        } finally {
            Document::flushEventListeners();
        }
        $this->artisan('documents:cleanup')->assertSuccessful();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_booking_attachment_rolls_back_booking_changes(): void
    {
        $booking = Booking::factory()->create(['title' => 'Original']);
        $storage = Mockery::mock(DocumentStorage::class)->makePartial();
        $storage->shouldReceive('upload')->once()->andThrow(new RuntimeException('Storage unavailable'));
        $this->app->instance(DocumentStorage::class, $storage);
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($booking->trip->user)->put(route('bookings.update', $booking), [
                'title' => 'Changed', 'category' => 'flight', 'amount' => 25, 'currency' => 'CHF',
                'booking_status' => 'confirmed', 'payment_status' => 'unpaid',
                'document_file' => UploadedFile::fake()->createWithContent('ticket.pdf', "%PDF-1.4\nticket"),
            ]);
            $this->fail('Storage failure must abort booking change');
        } catch (RuntimeException) {
            $this->assertSame('Original', $booking->fresh()->title);
            $this->assertDatabaseCount('documents', 0);
        }
    }

    public function test_document_migration_reports_write_failure_and_keeps_public_bytes(): void
    {
        $document = Document::factory()->create();
        Storage::disk('public')->put($document->file_path, 'legacy bytes');
        $private = Storage::disk('local')->path('');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('path')->with('')->andReturn($private);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::set('local', $disk);
        $this->artisan('documents:migrate-private --apply')->assertFailed();
        $this->assertSame('legacy bytes', Storage::disk('public')->get($document->file_path));
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'file_path' => $document->file_path]);
    }

    public function test_foreign_booking_cannot_be_attached_to_own_trip(): void
    {
        $trip = Trip::factory()->create();
        $foreign = Booking::factory()->create();
        $this->actingAs($trip->user)->post(route('trips.documents.store', $trip), [
            'booking_id' => $foreign->id, 'document_type' => 'confirmation',
            'files' => [UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\nnew")],
        ])->assertStatus(422);
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentStorage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DocumentSafetyTest extends TestCase
{
    public function test_download_preserves_legacy_storage_and_access_boundaries(): void
    {
        $document = Document::factory()->create();
        Storage::disk('public')->put($document->file_path, 'legacy bytes');
        $this->actingAs(User::factory()->create())->get(route('documents.download', $document))->assertForbidden();
        $shared = User::factory()->create();
        $document->trip->sharedUsers()->attach($shared);
        $this->actingAs($shared)->get(route('documents.download', $document))->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($document->trip->user)->get(route('documents.download', $document))->assertDownload();
    }

    public function test_failed_replacement_preserves_row_and_original_file(): void
    {
        $document = Document::factory()->create();
        Storage::disk('public')->put($document->file_path, 'original');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::set('local', $disk);
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($document->trip->user)->put(route('documents.update', $document), [
                'title' => 'Replacement', 'document_type' => 'confirmation',
                'file' => UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\nreplacement"),
            ]);
            $this->fail('Storage failure must abort the update.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('gespeichert', $exception->getMessage());
        }
        $this->assertSame($document->file_path, $document->fresh()->file_path);
        $this->assertSame('original', Storage::disk('public')->get($document->file_path));
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_rollback_tracks_new_files_and_keeps_existing_documents(): void
    {
        $storage = app(DocumentStorage::class);
        $document = Document::factory()->create();
        Storage::disk('local')->put($document->file_path, 'original');
        try {
            $storage->transaction(function () use ($storage): void {
                $storage->writePrivate('documents/new.pdf', 'new');
                throw new RuntimeException('Database failure');
            });
        } catch (RuntimeException) {
        }
        $this->assertDatabaseHas('document_file_deletions', ['file_path' => 'documents/new.pdf']);
        $this->assertSame(0, $storage->cleanup());
        Storage::disk('local')->assertMissing('documents/new.pdf');
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_trip_deletion_cleans_all_copies_only_after_commit(): void
    {
        $document = Document::factory()->create();
        foreach (['local', 'public'] as $disk) {
            Storage::disk($disk)->put($document->file_path, 'copy');
        }
        $this->actingAs($document->trip->user)->delete(route('trips.destroy', $document->trip))->assertRedirect();
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk('public')->assertExists($document->file_path);
        $this->artisan('documents:cleanup')->assertSuccessful();
        Storage::disk('local')->assertMissing($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);
    }

    public function test_private_migration_is_dry_run_by_default_and_never_deletes_sources(): void
    {
        $document = Document::factory()->create();
        Storage::disk('public')->put($document->file_path, 'legacy');
        $this->artisan('documents:migrate-private')->assertSuccessful();
        Storage::disk('local')->assertMissing($document->file_path);
        $this->artisan('documents:migrate-private --apply')->assertSuccessful();
        $this->artisan('documents:migrate-private --apply')->assertSuccessful();
        $this->assertSame('legacy', Storage::disk('local')->get($document->file_path));
        Storage::disk('public')->assertExists($document->file_path);
        Storage::disk('local')->put($document->file_path, 'conflicting');
        $this->artisan('documents:migrate-private --apply')->assertFailed();
        $this->assertSame('legacy', Storage::disk('public')->get($document->file_path));
        $this->assertSame('conflicting', Storage::disk('local')->get($document->file_path));
    }

    public function test_cleanup_never_deletes_a_referenced_file(): void
    {
        $document = Document::factory()->create();
        Storage::disk('local')->put($document->file_path, 'kept');
        app(DocumentStorage::class)->scheduleDeletion($document->file_path);
        $this->artisan('documents:cleanup')->assertSuccessful();
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        app(DocumentStorage::class)->locate('documents/../../.env');
    }
}

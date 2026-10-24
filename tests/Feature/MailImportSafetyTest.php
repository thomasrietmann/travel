<?php

namespace Tests\Feature;

use App\Models\ImportedMail;
use App\Models\Trip;
use App\Models\User;
use App\Services\DocumentStorage;
use App\Services\ImapMailbox;
use App\Services\IncomingMailImporter;
use App\Services\MailAiExtractor;
use App\Services\MailContentReader;
use App\Services\ProcessLock;
use App\Services\TrustedMailSender;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\FakeMailbox;
use Tests\TestCase;

class MailImportSafetyTest extends TestCase
{
    private FakeMailbox $mailbox;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail_import.enabled' => true, 'mail_import.authentication_results_trusted' => true, 'mail_import.trusted_authserv_ids' => ['mx.example.test']]);
        $this->mailbox = new FakeMailbox;
        $this->app->instance(ImapMailbox::class, $this->mailbox);
        $user = User::factory()->create(['email' => $this->mailbox->sender]);
        $this->trip = Trip::factory()->for($user)->create();
    }

    private function extractor(): MockInterface
    {
        $mock = Mockery::mock(MailAiExtractor::class);
        $this->app->instance(MailAiExtractor::class, $mock);

        return $mock;
    }

    public function test_valid_import_is_idempotent_and_stores_one_private_document(): void
    {
        $this->extractor()->shouldReceive('extract')->once()->andReturn(['trip_id' => $this->trip->id, 'title' => 'Ticket']);
        $service = app(IncomingMailImporter::class);
        $this->assertSame(1, $service->import()['imported']);
        $this->assertSame(1, $service->import()['ignored']);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('documents', 1);
        $path = $this->trip->documents()->first()->file_path;
        $this->assertSame($this->mailbox->contents, \Storage::disk('local')->get($path));
        \Storage::disk('public')->assertMissing($path);
    }

    public function test_seen_failure_never_repeats_a_committed_booking(): void
    {
        $this->extractor()->shouldReceive('extract')->once()->andReturn(['trip_id' => $this->trip->id, 'title' => 'Ticket']);
        $this->mailbox->failSeen = true;
        $service = app(IncomingMailImporter::class);
        $this->assertSame(1, $service->import()['failed']);
        $this->assertDatabaseHas('imported_mails', ['status' => 'imported']);
        $this->mailbox->failSeen = false;
        $this->assertSame(1, $service->import()['ignored']);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_failure_keeps_mail_unread_and_supports_controlled_retry(): void
    {
        $this->extractor()->shouldReceive('extract')->once()->andThrow(new RuntimeException('secret payload'));
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['failed']);
        $record = ImportedMail::first();
        $this->assertSame(0, $this->mailbox->seen);
        $this->assertStringNotContainsString('secret', $record->notes);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['deferred']);
        $this->artisan('mail:retry', ['id' => $record->id])->assertSuccessful();
        $this->extractor()->shouldReceive('extract')->once()->andReturn(['trip_id' => $this->trip->id]);
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['imported']);
    }

    public function test_forged_or_unverified_sender_stays_blocked_without_reading_body(): void
    {
        $this->extractor()->shouldNotReceive('extract');
        $this->mailbox->auth = 'Authentication-Results: attacker.test; dmarc=pass header.from=example.com';
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['failed']);
        $this->assertDatabaseHas('imported_mails', ['status' => 'blocked']);
        $this->assertSame(0, $this->mailbox->reads);
        $this->assertSame(0, $this->mailbox->seen);
    }

    public function test_unverified_existing_user_remains_intact_but_cannot_import(): void
    {
        $this->trip->user->forceFill(['email_verified_at' => null])->save();
        $this->extractor()->shouldNotReceive('extract');
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['failed']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('trips', 1);
        $this->assertSame(0, $this->mailbox->seen);
    }

    public function test_oversize_mail_is_rejected_before_fetching_body(): void
    {
        $this->mailbox->size = 26 * 1024 * 1024;
        $this->extractor()->shouldNotReceive('extract');
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['failed']);
        $this->assertSame(0, $this->mailbox->reads);
    }

    public function test_claimed_pdf_with_html_content_is_rejected(): void
    {
        $this->mailbox->contents = '<html><script>alert(1)</script></html>';
        $this->expectException(RuntimeException::class);
        app(MailContentReader::class)->read(1);
    }

    public function test_nested_mime_limit_is_enforced_before_fetching_body(): void
    {
        $part = (object) ['type' => 0, 'subtype' => 'plain'];
        for ($i = 0; $i < 15; $i++) {
            $part = (object) ['type' => 1, 'parts' => [$part]];
        }
        $this->mailbox->mime = $part;
        try {
            app(MailContentReader::class)->read(1);
            $this->fail('Deep MIME trees must fail.');
        } catch (RuntimeException) {
            $this->assertSame(0, $this->mailbox->reads);
        }
    }

    public function test_reply_to_cannot_select_the_victim_and_ambiguous_auth_is_rejected(): void
    {
        $headers = $this->mailbox->headers(1);
        $headers->reply_to = [(object) ['mailbox' => 'victim', 'host' => 'example.com']];
        $verifier = app(TrustedMailSender::class);
        $raw = $this->mailbox->rawHeaders(1);
        $this->assertSame('traveler@example.com', $verifier->address($headers, $raw));
        $this->assertNull($verifier->address($headers, $raw.$this->mailbox->auth));
        $this->assertNull($verifier->address($headers, str_replace('dmarc=pass', 'dmarc=fail', $raw)));
        $this->assertNull($verifier->address($headers, str_replace('header.from=example.com', 'header.from=other.com', $raw)));
        $this->assertNull($verifier->address($headers, str_replace('dmarc=pass', '(dmarc=pass)', $raw)));
        $headers->from[] = (object) ['mailbox' => 'victim', 'host' => 'example.com'];
        $this->assertNull($verifier->address($headers, $raw));
    }

    public function test_all_entry_points_share_a_non_expiring_process_lock(): void
    {
        $lock = app(ProcessLock::class);
        $lock->run('mail-import-service', function () use ($lock): void {
            try {
                $lock->run('mail-import-service', fn () => $this->fail('Must not acquire second lock'));
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('bereits', $exception->getMessage());
            }
        });
        $this->assertTrue($lock->run('mail-import-service', fn () => true));
    }

    public function test_mail_command_exits_nonzero_when_messages_fail(): void
    {
        $this->mailbox->auth = '';
        $this->artisan('mail:import')->assertFailed();
    }

    public function test_dmarc_text_inside_reason_strings_or_comments_is_not_an_authentication_result(): void
    {
        $headers = $this->mailbox->headers(1);
        foreach ([
            'mx.example.test; spf=fail reason="fake; dmarc=pass header.from=example.com; text"',
            'mx.example.test; spf=fail (fake; dmarc=pass header.from=example.com; text)',
            'mx.example.test; spf=pass reason="unterminated; dmarc=pass header.from=example.com',
        ] as $value) {
            $this->assertNull(app(TrustedMailSender::class)->address($headers, "From: traveler@example.com\r\nAuthentication-Results: ".$value));
        }
    }

    public function test_lost_database_commit_acknowledgement_does_not_make_import_retryable(): void
    {
        $storage = Mockery::mock(DocumentStorage::class)->makePartial();
        $storage->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            DB::transaction($callback);
            throw new RuntimeException('Commit acknowledgement lost');
        });
        $this->app->instance(DocumentStorage::class, $storage);
        $this->extractor()->shouldReceive('extract')->once()->andReturn(['trip_id' => $this->trip->id, 'title' => 'Ticket']);
        $service = app(IncomingMailImporter::class);
        $this->assertSame(1, $service->import()['imported']);
        $this->assertDatabaseHas('imported_mails', ['status' => 'imported']);
        $this->assertSame(1, $service->import()['ignored']);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_quoted_header_reason_with_escaped_quote_cannot_inject_a_result(): void
    {
        $value = 'mx.example.test; spf=fail reason="ignored '.chr(92).'"; dmarc=pass header.from=example.com; still a reason"';
        $raw = "From: traveler@example.com\r\nAuthentication-Results: ".$value;
        $this->assertNull(app(TrustedMailSender::class)->address($this->mailbox->headers(1), $raw));
        $raw .= '; dmarc=pass header.from=example.com';
        $this->assertSame('traveler@example.com', app(TrustedMailSender::class)->address($this->mailbox->headers(1), $raw));
    }
}

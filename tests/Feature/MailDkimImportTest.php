<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Services\ImapMailbox;
use App\Services\IncomingMailImporter;
use App\Services\MailAiExtractor;
use App\Services\TrustedMailSender;
use Dachs\MailAuth\ArrayDkimDnsResolver;
use Dachs\MailAuth\DkimDnsResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeMailbox;
use Tests\TestCase;

class MailDkimImportTest extends TestCase
{
    private array $fixture;

    private FakeMailbox $mailbox;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail_import.auth_mode' => 'dkim', 'mail_import.enabled' => true,
            'mail_import.authentication_results_trusted' => false, 'mail_import.trusted_authserv_ids' => [],
            'mail_import.recipient' => 'trips@example.test',
        ]);
        $this->fixture = json_decode(file_get_contents(base_path('tests/Fixtures/dkim-forward.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->app->instance(DkimDnsResolver::class, new ArrayDkimDnsResolver($this->fixture['records']));
        $this->mailbox = new FakeMailbox;
        $this->mailbox->mime = (object) ['type' => 0, 'subtype' => 'plain'];
        $this->mailbox->contents = $this->fixture['body'];
        $this->app->instance(ImapMailbox::class, $this->mailbox);
        $this->trip = Trip::factory()->for(User::factory()->create(['email' => $this->mailbox->sender]))->create();
    }

    public static function signingKeys(): array
    {
        return [['rsa'], ['ed']];
    }

    #[DataProvider('signingKeys')]
    public function test_forward_imports_automatically_with_a_full_domain_aligned_signature(string $key): void
    {
        $this->mailbox->raw = $this->fixture['signatures'][$key].$this->fixture['headers'];
        $this->mock(MailAiExtractor::class)->shouldReceive('extract')->once()->andReturn(['trip_id' => $this->trip->id, 'title' => 'Ticket']);
        $this->artisan('mail:import', ['--limit' => 1])->assertSuccessful();
        $this->assertDatabaseHas('imported_mails', ['status' => 'imported', 'user_id' => $this->trip->user_id]);
        $this->assertDatabaseHas('bookings', ['trip_id' => $this->trip->id, 'title' => 'Ticket']);
        $this->assertSame(1, $this->mailbox->seen);
        $reads = $this->mailbox->reads;
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['ignored']);
        $this->assertSame($reads, $this->mailbox->reads);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_tampered_body_stays_unread_even_with_a_positive_authentication_results_header(): void
    {
        $this->mailbox->raw = $this->mailbox->auth."\r\n".$this->fixture['signatures']['ed'].$this->fixture['headers'];
        $this->mailbox->contents .= 'A different booking.';
        $this->mock(MailAiExtractor::class)->shouldNotReceive('extract');
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['failed']);
        $this->assertDatabaseHas('imported_mails', ['status' => 'blocked', 'user_id' => null]);
        $this->assertSame(0, $this->mailbox->seen);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_only_an_eligible_signature_authorizes_a_message(): void
    {
        $verifier = app(TrustedMailSender::class);
        $headers = $this->mailbox->headers(1);
        $raw = $this->fixture['headers'];
        $signature = $this->fixture['signatures']['rsa'];
        $body = fn () => $this->fail('Rejected signatures must not fetch the body.');
        foreach ([
            $this->mailbox->auth."\r\n".$raw,
            $signature.str_replace('Subject: Travel', 'Subject: Other', $raw),
            $signature."Subject: Ambiguous\r\n".$raw,
            $signature."From: another@example.test\r\n".$raw,
            str_replace('d=example.com', 'd=other.example', $signature).$raw,
            str_replace('d=example.com', 'd=example.com; d=example.com', $signature).$raw,
            $this->fixture['signatures']['unsigned_recipient'].$raw,
            $this->fixture['signatures']['body_length'].$raw,
        ] as $message) {
            $this->assertNull($verifier->address($headers, $message, $body));
        }

        // Subsequent signatures can pass even if the first one is unusable.
        $this->assertSame($this->mailbox->sender, $verifier->address($headers,
            $this->fixture['signatures']['unsigned_recipient'].$this->fixture['signatures']['ed'].$raw,
            fn () => $this->fixture['body']));
        config(['mail_import.recipient' => 'another@example.test']);
        $this->assertNull($verifier->address($headers, $signature.$raw, $body));
    }

    public function test_unverified_users_and_oversized_mail_never_trigger_dns_or_body_reads(): void
    {
        $this->mock(DkimDnsResolver::class)->shouldNotReceive('txt');
        $this->mailbox->raw = $this->fixture['signatures']['rsa'].$this->fixture['headers'];
        $this->trip->user->forceFill(['email_verified_at' => null])->save();
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['failed']);
        $this->trip->user->forceFill(['email_verified_at' => now()])->save();
        $this->artisan('mail:retry', ['id' => 1])->assertSuccessful();
        $this->mailbox->size = 26 * 1024 * 1024;
        $this->assertSame(1, app(IncomingMailImporter::class)->import()['failed']);
        $this->assertSame(0, $this->mailbox->reads);
        $this->assertSame(0, $this->mailbox->seen);
    }

    public function test_switching_back_restores_the_authserv_requirements_and_an_unknown_mode_blocks_startup(): void
    {
        $this->mock(ImapMailbox::class)->shouldNotReceive('open');
        config(['mail_import.enabled' => false]);
        $this->artisan('mail:import')->expectsOutputToContain('MAIL_IMPORT_ENABLED')->assertFailed();
        config(['mail_import.enabled' => true, 'mail_import.auth_mode' => 'authserv']);
        $this->artisan('mail:import')->expectsOutputToContain('MAIL_IMPORT_AUTHSERV_IDS')->assertFailed();
        config(['mail_import.auth_mode' => 'other']);
        $this->artisan('mail:import')->expectsOutputToContain('MAIL_IMPORT_AUTH_MODE')->assertFailed();
    }
}

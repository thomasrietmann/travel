<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Trip;
use App\Services\StagingInventory;
use App\Services\StagingMailProbe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StagingAcceptanceTest extends TestCase
{
    private string $inventoryFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFile = sys_get_temp_dir().'/tripcontrol-inventory-'.bin2hex(random_bytes(16)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->inventoryFile)) {
            unlink($this->inventoryFile);
        }
        parent::tearDown();
    }

    private function enableChecks(): void
    {
        $this->app->instance('env', 'staging');
        config(['staging.checks_enabled' => true]);
    }

    public function test_inventory_preserves_old_columns_across_upgrade_and_checks_document_bytes(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertSuccessful();
        $trip = Trip::factory()->create();
        $trip->user->emailAliases()->create(['email' => 'legacy@example.test']);
        $document = Document::factory()->for($trip)->create();
        Storage::disk('public')->put($document->file_path, 'original bytes');
        $inventory = app(StagingInventory::class);
        $before = $inventory->capture();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        Storage::disk('local')->put($document->file_path, 'original bytes');
        $this->assertSame($before, $inventory->capture($before));
        $this->assertStringNotContainsString($trip->user->email, json_encode($before));
        $this->assertStringNotContainsString($trip->user->password, json_encode($before));
        DB::table('users')->where('id', $trip->user_id)->update(['password' => 'changed']);
        $this->assertNotSame($before, $inventory->capture($before));
        Storage::disk('local')->put($document->file_path, 'changed bytes');
        $this->expectException(RuntimeException::class);
        $inventory->capture($before);
    }

    public function test_inventory_rejects_missing_files_and_wrong_application_key(): void
    {
        $document = Document::factory()->create();
        Storage::disk('local')->put($document->file_path, 'file');
        $inventory = app(StagingInventory::class);
        $before = $inventory->capture();
        config(['app.key' => 'different-key']);
        $this->assertNotSame($before, $inventory->capture($before));
        Storage::disk('local')->delete($document->file_path);
        $this->expectException(RuntimeException::class);
        $inventory->capture($before);
    }

    public function test_inventory_command_is_explicit_private_non_overwriting_and_read_only(): void
    {
        $this->artisan('staging:inventory', ['file' => $this->inventoryFile])->assertFailed();
        $this->assertFileDoesNotExist($this->inventoryFile);
        $this->enableChecks();
        $this->artisan('staging:inventory', ['file' => public_path('inventory.json')])->assertFailed();
        $this->artisan('staging:inventory', ['file' => $this->inventoryFile])->assertSuccessful();
        $this->assertSame(0600, fileperms($this->inventoryFile) & 0777);
        $bytes = file_get_contents($this->inventoryFile);
        $this->artisan('staging:inventory', ['file' => $this->inventoryFile])->assertFailed();
        $this->assertSame($bytes, file_get_contents($this->inventoryFile));
        $this->artisan('staging:inventory', ['file' => $this->inventoryFile, '--verify' => true])->assertSuccessful();
        Trip::factory()->create();
        $this->artisan('staging:inventory', ['file' => $this->inventoryFile, '--verify' => true])->assertFailed();
        $this->assertDatabaseCount('trips', 1);
    }

    public function test_mail_checks_cannot_run_in_production_or_without_opt_in(): void
    {
        $probe = Mockery::mock(StagingMailProbe::class);
        $probe->shouldNotReceive('smtp', 'imap', 'send');
        $this->app->instance(StagingMailProbe::class, $probe);
        config(['staging.checks_enabled' => true]);
        $this->app->instance('env', 'production');
        $this->artisan('staging:mail-check', ['--smtp' => true])->assertFailed();
        $this->artisan('staging:inventory', ['file' => $this->inventoryFile])->assertFailed();
        $this->artisan('deployment:check', ['--staging' => true, '--probe-storage' => true])->assertFailed();
        $this->app->instance('env', 'staging');
        config(['staging.checks_enabled' => false]);
        $this->artisan('staging:mail-check', ['--imap' => true])->assertFailed();
    }

    public function test_connection_checks_never_send_a_message_without_the_send_option(): void
    {
        $this->enableChecks();
        $probe = Mockery::mock(StagingMailProbe::class);
        $probe->shouldReceive('smtp')->once();
        $probe->shouldReceive('imap')->once();
        $probe->shouldNotReceive('send');
        $this->app->instance(StagingMailProbe::class, $probe);
        $this->artisan('staging:mail-check', ['--smtp' => true, '--imap' => true])->assertSuccessful();
        $this->assertDatabaseCount('imported_mails', 0);
    }

    public function test_testmail_requires_single_configured_recipient_and_explicit_smtp(): void
    {
        $this->enableChecks();
        $probe = Mockery::mock(StagingMailProbe::class);
        $probe->shouldReceive('smtp')->once();
        $probe->shouldReceive('send')->once()->with('own-test@example.test', Mockery::pattern('/^[0-9a-f-]{36}$/'));
        $this->app->instance(StagingMailProbe::class, $probe);
        $this->artisan('staging:mail-check', ['--send' => true])->assertFailed();
        config(['staging.mail_recipient' => 'first@example.test,second@example.test']);
        $this->artisan('staging:mail-check', ['--smtp' => true, '--send' => true])->assertFailed();
        config(['staging.mail_recipient' => 'own-test@example.test']);
        $this->artisan('staging:mail-check', ['--smtp' => true, '--send' => true])->assertSuccessful();
    }

    public function test_mail_errors_do_not_expose_credentials_or_server_replies(): void
    {
        $this->enableChecks();
        $probe = Mockery::mock(StagingMailProbe::class);
        $probe->shouldReceive('smtp')->once()->andThrow(new RuntimeException('secret-password/server-response'));
        $this->app->instance(StagingMailProbe::class, $probe);
        $this->artisan('staging:mail-check', ['--smtp' => true])
            ->doesntExpectOutputToContain('secret-password')->assertFailed();
    }

    public function test_smtp_requires_tls_even_when_using_starttls(): void
    {
        config(['mail.mailers.smtp.scheme' => 'smtp', 'mail.mailers.smtp.port' => 587]);
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $this->assertTrue($transport->isTlsRequired());
        $this->assertTrue($transport->isAutoTls());
    }

    public function test_imap_probe_rejects_insecure_configuration_before_connecting(): void
    {
        config(['mail_import.imap.mailbox' => '{imap.example.test:993/imap/ssl/novalidate-cert}INBOX']);
        $this->expectException(RuntimeException::class);
        app(StagingMailProbe::class)->imap();
    }

    public function test_staging_mail_blocks_customer_recipients_including_cc_and_bcc(): void
    {
        $this->enableChecks();
        config(['staging.mail_recipient' => 'own-test@example.test', 'mail.from.address' => 'sender@example.test']);
        foreach (['to', 'cc', 'bcc'] as $field) {
            try {
                Mail::raw('test', fn ($message) => $message->to('own-test@example.test')->{$field}('customer@example.test'));
                $this->fail('Customer mail must not leave staging');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('STAGING_MAIL_RECIPIENT', $exception->getMessage());
            }
        }
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
        Mail::raw('test', fn ($message) => $message->to('own-test@example.test'));
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
    }
}

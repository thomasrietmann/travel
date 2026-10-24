<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Trip;
use App\Services\ProcessLock;
use App\Services\StagingInventory;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MysqlUpgradeCommandTest extends TestCase
{
    private ?string $evidence = null;

    private bool $down = true;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Production upgrade command requires the MySQL test job.');
        }
        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertSuccessful();
        $this->app->instance('env', 'staging');
        config(['app.debug' => false]);
        $maintenance = Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturnUsing(fn () => $this->down);
        $this->app->instance(MaintenanceMode::class, $maintenance);
        $this->evidence = sys_get_temp_dir().'/tripcontrol-upgrade-'.bin2hex(random_bytes(16));
        mkdir($this->evidence, 0700);
    }

    protected function tearDown(): void
    {
        if ($this->evidence) {
            File::deleteDirectory($this->evidence);
        }
        parent::tearDown();
    }

    private function applyOptions(): array
    {
        return [
            '--apply' => true, '--expect-database' => DB::connection()->getDatabaseName(),
            '--evidence-dir' => $this->evidence, '--backup-confirmed' => true, '--writers-stopped' => true,
        ];
    }

    private function legacyDocument(): Document
    {
        $trip = Trip::factory()->create();
        $trip->user->emailAliases()->create(['email' => 'legacy@example.test']);
        $document = Document::factory()->for($trip)->create();
        Storage::disk('public')->put($document->file_path, 'existing bytes');

        return $document;
    }

    public function test_default_only_checks_without_migration_or_checkpoint(): void
    {
        $this->legacyDocument();
        $this->artisan('deployment:upgrade')->assertSuccessful();
        $this->assertFalse(Schema::hasTable('document_file_deletions'));
        $this->assertFileDoesNotExist($this->evidence.'/upgrade-before.json');
    }

    public function test_apply_and_repeat_preserve_legacy_rows_files_and_original_checkpoint(): void
    {
        $document = $this->legacyDocument();
        $before = app(StagingInventory::class)->capture();
        $options = [...$this->applyOptions(), '--copy-documents' => true];
        $this->artisan('deployment:upgrade', $options)->assertSuccessful();
        $checkpoint = file_get_contents($this->evidence.'/upgrade-before.json');
        $this->assertSame(0600, fileperms($this->evidence.'/upgrade-before.json') & 0777);
        $this->assertSame($before, app(StagingInventory::class)->capture($before));
        $this->assertSame('existing bytes', Storage::disk('local')->get($document->file_path));
        $this->assertSame('existing bytes', Storage::disk('public')->get($document->file_path));
        $this->assertTrue(app()->isDownForMaintenance());
        $this->artisan('deployment:upgrade', $options)->assertSuccessful();
        $this->assertSame($checkpoint, file_get_contents($this->evidence.'/upgrade-before.json'));
        $this->assertDatabaseHas('user_email_aliases', ['email' => 'legacy@example.test', 'verified_at' => null]);
    }

    public function test_apply_requires_exact_database_and_all_operational_guards(): void
    {
        $options = $this->applyOptions();
        foreach (['--expect-database', '--backup-confirmed', '--writers-stopped'] as $required) {
            $invalid = $options;
            unset($invalid[$required]);
            $this->artisan('deployment:upgrade', $invalid)->assertFailed();
        }
        $this->artisan('deployment:upgrade', [...$options, '--expect-database' => 'wrong'])->assertFailed();
        $this->down = false;
        $this->artisan('deployment:upgrade', $options)->assertFailed();
        $this->assertFalse(Schema::hasTable('document_file_deletions'));
        $this->assertFileDoesNotExist($this->evidence.'/upgrade-before.json');
    }

    public function test_unexpected_old_migration_or_foreign_history_is_rejected(): void
    {
        DB::table('migrations')->insert(['migration' => '2099_unknown_change', 'batch' => 9]);
        $this->artisan('deployment:upgrade', $this->applyOptions())->assertFailed();
        DB::table('migrations')->where('migration', '2099_unknown_change')->delete();
        DB::table('migrations')->where('migration', '2026_05_15_000001_create_trip_user_table')->delete();
        $this->artisan('deployment:upgrade', $this->applyOptions())->assertFailed();
        $this->assertFalse(Schema::hasTable('document_file_deletions'));
    }

    public function test_partial_mysql_ddl_is_rejected_before_any_new_migration(): void
    {
        Schema::create('email_identity_lock', fn (Blueprint $table) => $table->unsignedInteger('id')->primary());
        $this->artisan('deployment:upgrade', $this->applyOptions())->assertFailed();
        $this->assertFalse(Schema::hasTable('document_file_deletions'));
        $this->assertFalse(Schema::hasColumn('user_email_aliases', 'verified_at'));
    }

    public function test_changed_data_is_not_accepted_as_a_new_baseline_on_retry(): void
    {
        $document = $this->legacyDocument();
        $this->artisan('deployment:upgrade', $this->applyOptions())->assertSuccessful();
        $checkpoint = file_get_contents($this->evidence.'/upgrade-before.json');
        $document->trip->update(['title' => 'changed after upgrade']);
        $this->artisan('deployment:upgrade', $this->applyOptions())->assertFailed();
        $this->assertSame($checkpoint, file_get_contents($this->evidence.'/upgrade-before.json'));
    }

    public function test_missing_document_or_public_evidence_path_blocks_migration(): void
    {
        $document = $this->legacyDocument();
        $this->artisan('deployment:upgrade', [...$this->applyOptions(), '--evidence-dir' => public_path()])->assertFailed();
        Storage::disk('public')->delete($document->file_path);
        $this->artisan('deployment:upgrade', $this->applyOptions())->assertFailed();
        $this->assertFalse(Schema::hasTable('document_file_deletions'));
    }

    public function test_active_import_blocks_upgrade_and_production_rejects_staging_flag(): void
    {
        app(ProcessLock::class)->run('mail-import-service', function (): void {
            $this->artisan('deployment:upgrade', $this->applyOptions())->assertFailed();
        });
        $this->app->instance('env', 'production');
        config(['staging.checks_enabled' => true]);
        $this->artisan('deployment:upgrade', $this->applyOptions())->assertFailed();
        $this->assertFalse(Schema::hasTable('document_file_deletions'));
    }
}

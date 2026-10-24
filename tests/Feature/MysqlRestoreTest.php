<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Trip;
use App\Services\StagingInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PharData;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MysqlRestoreTest extends TestCase
{
    public function test_mysql_dump_and_file_archive_restore_preserve_legacy_data_through_upgrade(): void
    {
        $dump = getenv('TC_MYSQLDUMP_BIN');
        $client = getenv('TC_MYSQL_BIN');
        $target = getenv('TC_RESTORE_TEST_DATABASE');
        if (DB::connection()->getDriverName() !== 'mysql' || ! $dump || ! $client || ! $target) {
            $this->markTestSkipped('Optional restore rehearsal needs MySQL binaries and a separate empty *_test database.');
        }
        $source = config('database.connections.mysql');
        $this->assertStringEndsWith('_test', $source['database']);
        $this->assertStringEndsWith('_test', $target);
        $this->assertNotSame($source['database'], $target);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+$/D', $target);
        config(['database.connections.restore_test' => [...$source, 'database' => $target]]);
        $this->assertSame([], Schema::connection('restore_test')->getTableListing($target), 'Restore target must be empty.');

        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertSuccessful();
        $trip = Trip::factory()->create(['title' => 'Restore Zürich']);
        $trip->user->emailAliases()->create(['email' => 'restore-alias@example.test']);
        $public = Document::factory()->for($trip)->create(['file_path' => 'documents/legacy.pdf']);
        $private = Document::factory()->for($trip)->create(['file_path' => 'documents/private.pdf']);
        Storage::disk('public')->put($public->file_path, "legacy-public\x00bytes");
        Storage::disk('local')->put($private->file_path, "private\x00bytes");
        $before = app(StagingInventory::class)->capture();
        $directory = sys_get_temp_dir().'/tripcontrol-restore-'.bin2hex(random_bytes(16));
        mkdir($directory, 0700);
        $oldDefault = config('database.default');
        $oldDisks = config('filesystems.disks');
        try {
            // Client credentials never appear in argv or output; the parent directory is private.
            $quote = fn ($value) => '"'.str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '\\r', '\\n'], (string) $value).'"';
            $options = "[client]\nuser=".$quote($source['username'])."\npassword=".$quote($source['password'])."\n";
            $options .= filled($source['unix_socket'])
                ? "protocol=socket\nsocket=".$quote($source['unix_socket'])."\n"
                : "protocol=tcp\nhost=".$quote($source['host'])."\nport=".(int) $source['port']."\n";
            file_put_contents($directory.'/client.cnf', $options);
            chmod($directory.'/client.cnf', 0600);
            $export = new Process([$dump, '--defaults-file='.$directory.'/client.cnf', '--single-transaction', '--quick', '--hex-blob', '--no-tablespaces', '--set-gtid-purged=OFF', '--skip-add-drop-table', $source['database']]);
            $export->mustRun();
            $sql = $export->getOutput();
            $this->assertNotEmpty($sql);
            file_put_contents($directory.'/database.sql', $sql);

            $archive = new PharData($directory.'/documents.tar');
            $archive->addFile(Storage::disk('public')->path($public->file_path), 'public/'.$public->file_path);
            $archive->addFile(Storage::disk('local')->path($private->file_path), 'private/'.$private->file_path);
            $archive->addFromString('private/unreferenced.txt', 'also backed up');
            $archive->extractTo($directory.'/restored');
            $this->assertSame('also backed up', file_get_contents($directory.'/restored/private/unreferenced.txt'));
            $import = new Process([$client, '--defaults-file='.$directory.'/client.cnf', '--database='.$target]);
            $import->setInput(fopen($directory.'/database.sql', 'r'));
            $import->mustRun();

            config([
                'database.default' => 'restore_test',
                'filesystems.disks.local.root' => $directory.'/restored/private',
                'filesystems.disks.public.root' => $directory.'/restored/public',
            ]);
            Storage::forgetDisk(['local', 'public']);
            $this->assertSame($before, app(StagingInventory::class)->capture($before));
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $this->assertSame($before, app(StagingInventory::class)->capture($before));
            $this->actingAs($trip->user)->get(route('documents.download', $public))->assertDownload();
            $this->actingAs($trip->user)->get(route('documents.download', $private))->assertDownload();
        } finally {
            config(['database.default' => $oldDefault, 'filesystems.disks' => $oldDisks]);
            Storage::forgetDisk(['local', 'public']);
            DB::purge('restore_test');
            File::deleteDirectory($directory);
        }
        // Retain the restored database for inspection; a repeat must use a new empty target.
    }
}

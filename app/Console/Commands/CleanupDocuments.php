<?php

namespace App\Console\Commands;

use App\Services\DocumentStorage;
use App\Services\ProcessLock;
use Illuminate\Console\Command;

class CleanupDocuments extends Command
{
    protected $signature = 'documents:cleanup {--limit=100}';

    protected $description = 'Explizit zur Löschung vorgemerkte, nicht mehr referenzierte Dokumentdateien bereinigen';

    public function handle(DocumentStorage $storage): int
    {
        return app(ProcessLock::class)->run('documents-cleanup', fn () => $storage->cleanup(max(1, min(1000, (int) $this->option('limit'))))) ? self::FAILURE : self::SUCCESS;
    }
}

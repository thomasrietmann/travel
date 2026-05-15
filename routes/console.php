<?php

use App\Models\Document;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('documents:migrate-private', function () {
    $migrated = 0;
    $missing = 0;

    Document::query()->each(function (Document $document) use (&$migrated, &$missing) {
        if (Storage::disk('local')->exists($document->file_path)) {
            return;
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('local')->put($document->file_path, Storage::disk('public')->get($document->file_path));
            Storage::disk('public')->delete($document->file_path);
            $migrated++;

            return;
        }

        $legacyPath = base_path('storage/app/public/'.$document->file_path);

        if (File::exists($legacyPath)) {
            Storage::disk('local')->put($document->file_path, File::get($legacyPath));
            File::delete($legacyPath);
            $migrated++;

            return;
        }

        $missing++;
    });

    $this->info("{$migrated} Dokumente nach private Storage migriert.");

    if ($missing > 0) {
        $this->warn("{$missing} Dokumente hatten keine auffindbare Datei.");
    }
})->purpose('Move existing document uploads from public to private storage');

Artisan::command('mail:import {--limit= : Maximale Anzahl Mails fuer diesen Lauf}', function () {
    $stats = app(\App\Services\IncomingMailImporter::class)->import((int) ($this->option('limit') ?: 0));

    $this->info("{$stats['imported']} Mails importiert.");
    $this->info("{$stats['ignored']} Mails ignoriert.");

    if ($stats['failed'] > 0) {
        $this->warn("{$stats['failed']} Mails konnten nicht importiert werden.");
    }
})->purpose('Import forwarded travel mails from the configured mailbox');

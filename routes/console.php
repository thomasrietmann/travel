<?php

use App\Models\Document;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

        if (! Storage::disk('public')->exists($document->file_path)) {
            $missing++;

            return;
        }

        Storage::disk('local')->put($document->file_path, Storage::disk('public')->get($document->file_path));
        Storage::disk('public')->delete($document->file_path);
        $migrated++;
    });

    $this->info("{$migrated} Dokumente nach private Storage migriert.");

    if ($missing > 0) {
        $this->warn("{$missing} Dokumente hatten keine auffindbare Datei.");
    }
})->purpose('Move existing document uploads from public to private storage');

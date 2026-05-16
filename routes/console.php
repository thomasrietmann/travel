<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
    Log::channel('mail_import')->info('Email-Import per Artisan wurde gestartet.');

    $stats = app(\App\Services\IncomingMailImporter::class)->import((int) ($this->option('limit') ?: 0));

    Log::channel('mail_import')->info('Email-Import per Artisan wurde abgeschlossen.', $stats);

    $this->info("{$stats['imported']} Mails importiert.");
    $this->info("{$stats['ignored']} Mails ignoriert.");

    if ($stats['failed'] > 0) {
        $this->warn("{$stats['failed']} Mails konnten nicht importiert werden.");
    }
})->purpose('Import forwarded travel mails from the configured mailbox');

Artisan::command('admin:create {email} {--name=Administrator} {--password= : Passwort fuer den Admin}', function () {
    $password = $this->option('password') ?: $this->secret('Admin-Passwort');

    if (! $password) {
        $this->error('Es wurde kein Passwort gesetzt.');

        return 1;
    }

    $user = User::query()->updateOrCreate(
        ['email' => $this->argument('email')],
        [
            'name' => $this->option('name'),
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => now(),
        ],
    );

    $this->info("Admin {$user->email} wurde erstellt oder aktualisiert.");

    return 0;
})->purpose('Create or update an administrator account');

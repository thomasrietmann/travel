<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\IncomingMailImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class MailImportController extends Controller
{
    public function index(): View
    {
        return view('admin.mail_import.index', [
            'logLines' => $this->lastLogLines(),
            'logPath' => storage_path('logs/mail-import.log'),
        ]);
    }

    public function store(IncomingMailImporter $importer): RedirectResponse
    {
        Log::channel('mail_import')->info('Manueller Email-Import wurde gestartet.', [
            'admin_user_id' => request()->user()?->id,
        ]);

        try {
            $stats = $importer->import();

            Log::channel('mail_import')->info('Manueller Email-Import wurde abgeschlossen.', $stats);

            return redirect()
                ->route('admin.mail-import.index')
                ->with('status', "{$stats['imported']} Mails importiert, {$stats['ignored']} ignoriert, {$stats['failed']} fehlgeschlagen.");
        } catch (Throwable $exception) {
            Log::channel('mail_import')->error('Manueller Email-Import ist fehlgeschlagen.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.mail-import.index')
                ->with('error', 'Email-Import fehlgeschlagen: '.$exception->getMessage());
        }
    }

    private function lastLogLines(): array
    {
        $path = storage_path('logs/mail-import.log');

        if (! File::exists($path)) {
            return [];
        }

        return array_slice(file($path, FILE_IGNORE_NEW_LINES) ?: [], -50);
    }
}

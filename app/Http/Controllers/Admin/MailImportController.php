<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportedMail;
use App\Services\IncomingMailImporter;
use App\Services\MailImportLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class MailImportController extends Controller
{
    public function __construct(private readonly MailImportLogger $logger) {}

    public function index(): View
    {
        return view('admin.mail_import.index', [
            'imports' => ImportedMail::query()->latest('updated_at')->limit(50)->get(['id', 'status', 'attempts', 'updated_at']),
            'logLines' => $this->logger->lastLines(),
            'logPath' => $this->logger->path(),
        ]);
    }

    public function store(IncomingMailImporter $importer): RedirectResponse
    {
        $this->logger->info('Manueller Email-Import wurde gestartet.', [
            'admin_user_id' => request()->user()?->id,
        ]);

        try {
            $stats = $importer->import(1);

            $this->logger->info('Manueller Email-Import wurde abgeschlossen.', $stats);

            return redirect()
                ->route('admin.mail-import.index')
                ->with('status', "{$stats['imported']} Mails importiert, {$stats['ignored']} ignoriert, {$stats['failed']} fehlgeschlagen, {$stats['deferred']} zurückgestellt.");
        } catch (Throwable $exception) {
            $this->logger->error('Manueller Email-Import ist fehlgeschlagen.', [
                'exception_class' => $exception::class,
            ]);

            return redirect()
                ->route('admin.mail-import.index')
                ->with('error', 'Email-Import fehlgeschlagen. Bitte Konfiguration und Importprotokoll prüfen.');
        }
    }
}

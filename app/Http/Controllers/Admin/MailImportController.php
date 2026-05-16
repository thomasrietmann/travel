<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            $stats = $importer->import();

            $this->logger->info('Manueller Email-Import wurde abgeschlossen.', $stats);

            return redirect()
                ->route('admin.mail-import.index')
                ->with('status', "{$stats['imported']} Mails importiert, {$stats['ignored']} ignoriert, {$stats['failed']} fehlgeschlagen.");
        } catch (Throwable $exception) {
            $this->logger->error('Manueller Email-Import ist fehlgeschlagen.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.mail-import.index')
                ->with('error', 'Email-Import fehlgeschlagen: '.$exception->getMessage());
        }
    }
}

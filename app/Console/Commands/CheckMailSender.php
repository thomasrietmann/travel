<?php

namespace App\Console\Commands;

use App\Services\TrustedMailSender;
use Illuminate\Console\Command;

class CheckMailSender extends Command
{
    protected $signature = 'mail:check-sender {eml : Lokale EML-Datei aus dem echten Empfangspostfach}';

    protected $description = 'Absenderprüfung einer lokalen Beispielmail ohne Import oder AI-Aufruf testen';

    public function handle(TrustedMailSender $sender): int
    {
        if (! function_exists('imap_rfc822_parse_headers')) {
            $this->error('IMAP-Erweiterung fehlt.');

            return self::FAILURE;
        }
        $path = $this->argument('eml');
        if (! is_file($path) || filesize($path) > 30 * 1024 * 1024) {
            $this->error('Lokale EML-Datei fehlt oder ist zu gross.');

            return self::FAILURE;
        }
        $content = file_get_contents($path);
        $header = preg_split('/\r?\n\r?\n/', $content, 2)[0];
        $parsed = imap_rfc822_parse_headers($header);
        $accepted = $parsed && $sender->address($parsed, $header) !== null;
        $this->line($accepted ? 'Absenderauthentifizierung akzeptiert. Adressbesitz wird beim Import separat geprüft.' : 'Absenderauthentifizierung abgelehnt.');

        return $accepted ? self::SUCCESS : self::FAILURE;
    }
}

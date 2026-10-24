<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigratePrivateDocuments extends Command
{
    protected $signature = 'documents:migrate-private {--apply : Kopien nach Prüfung erstellen}';

    protected $description = 'Dokumente inventarisieren und optional geprüft kopieren; Quelldateien bleiben erhalten';

    public function handle(DocumentStorage $storage): int
    {
        $failed = 0;
        foreach (Document::query()->lazyById() as $document) {
            try {
                $paths = $storage->paths($document->file_path);
                if ($paths === []) {
                    throw new \RuntimeException('Datei fehlt');
                }
                $hashes = array_unique(array_map($storage->checksum(...), $paths));
                if (count($hashes) !== 1) {
                    throw new \RuntimeException('Abweichende Kopien; manuell prüfen');
                }
                $exists = Storage::disk('local')->exists($document->file_path);
                if (! $exists && $this->option('apply')) {
                    $contents = file_get_contents($paths[0]);
                    if ($contents === false) {
                        throw new \RuntimeException('Quelle nicht lesbar');
                    }
                    $storage->writePrivate($document->file_path, $contents);
                }
                $this->line(json_encode(['document_id' => $document->id, 'file_path' => $document->file_path, 'copies' => count($paths), 'sha256' => $hashes[0], 'private_exists' => $exists, 'applied' => (bool) $this->option('apply')], JSON_UNESCAPED_SLASHES));
            } catch (Throwable) {
                $this->error('Dokument '.$document->id.': Datei fehlt, Kopien widersprechen sich oder Kopieren fehlgeschlagen. Quellen wurden nicht gelöscht.');
                $failed++;
            }
        }
        $this->info('Quelldateien bleiben erhalten. Statischen Webzugriff separat sperren.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

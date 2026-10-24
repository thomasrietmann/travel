<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentStorage
{
    private array $written = [];

    /** Roll back newly written files when their database transaction fails. */
    public function transaction(callable $callback): mixed
    {
        $start = count($this->written);
        try {
            return DB::transaction($callback);
        } catch (Throwable $exception) {
            foreach (array_slice($this->written, $start) as $path) {
                try {
                    $this->scheduleDeletion($path);
                } catch (Throwable) {
                    // An unavailable DB can make commit status uncertain. Keep bytes for recovery.
                    Log::error('Dokumentbereinigung konnte nicht vorgemerkt werden. Speicherinventar prüfen.');
                }
            }
            throw $exception;
        } finally {
            $this->written = array_slice($this->written, 0, $start);
        }
    }

    public function upload(int $tripId, UploadedFile $file): string
    {
        $extension = $file->guessExtension();
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Nicht unterstützter Dateityp.');
        }
        $path = 'documents/'.$tripId.'/'.Str::random(40).'.'.$extension;
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Dokument konnte nicht gelesen werden.');
        }
        $this->writePrivate($path, $contents);

        return $path;
    }

    public function writePrivate(string $path, string $contents): void
    {
        $this->validatePath($path);
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            throw new RuntimeException('Die Zieldatei existiert bereits.');
        }
        $this->written[] = $path;
        if (! $disk->put($path, $contents) || ! $disk->exists($path)
            || ! hash_equals(hash('sha256', $contents), $this->checksum($disk->path($path)))) {
            $this->scheduleDeletion($path);
            throw new RuntimeException('Dokument konnte nicht vollständig gespeichert werden.');
        }
    }

    /** Existing paths remain readable across web/CLI chroots and legacy storage. */
    public function paths(string $path): array
    {
        $this->validatePath($path);
        $roots = [Storage::disk('local')->path(''), Storage::disk('public')->path('')];
        if (config('filesystems.legacy_default_roots', true)) {
            $roots = [...$roots, storage_path('app/private'), storage_path('app/public'), base_path('storage/app/private'), base_path('storage/app/public')];
        }
        $roots = [...$roots, ...config('filesystems.private_fallback_roots', []), ...config('filesystems.public_fallback_roots', [])];
        $paths = [];
        foreach (array_unique(array_filter($roots)) as $root) {
            $candidate = rtrim($root, '/').'/'.$path;
            if (! is_file($candidate)) {
                continue;
            }
            $realRoot = realpath($root);
            $realFile = realpath($candidate);
            if ($realRoot === false || $realFile === false || ! str_starts_with($realFile, rtrim($realRoot, '/').'/')) {
                throw new RuntimeException('Ungültiger Dokumentpfad.');
            }
            $paths[$realFile] = $realFile;
        }

        return array_values($paths);
    }

    public function locate(string $path): ?string
    {
        return $this->paths($path)[0] ?? null;
    }

    public function checksum(string $absolutePath): string
    {
        $hash = hash_file('sha256', $absolutePath);
        if ($hash === false) {
            throw new RuntimeException('Dokument konnte nicht geprüft werden.');
        }

        return $hash;
    }

    /** Cleanup is durable and only runs after the owning DB change has committed. */
    public function scheduleDeletion(string $path): void
    {
        $this->validatePath($path);
        DB::table('document_file_deletions')->insert([
            'file_path' => $path, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function cleanup(int $limit = 100): int
    {
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Dateibereinigung darf nicht in einer offenen Transaktion laufen.');
        }
        $failed = 0;
        foreach (DB::table('document_file_deletions')->orderBy('id')->limit($limit)->get() as $entry) {
            if (Document::query()->where('file_path', $entry->file_path)->exists()) {
                DB::table('document_file_deletions')->where('id', $entry->id)->delete();

                continue;
            }
            try {
                foreach ($this->paths($entry->file_path) as $absolutePath) {
                    if (! unlink($absolutePath)) {
                        throw new RuntimeException('Datei konnte nicht gelöscht werden.');
                    }
                }
                DB::table('document_file_deletions')->where('id', $entry->id)->delete();
            } catch (Throwable) {
                DB::table('document_file_deletions')->where('id', $entry->id)->increment('attempts');
                $failed++;
            }
        }

        return $failed;
    }

    private function validatePath(string $path): void
    {
        if (! str_starts_with($path, 'documents/') || str_contains($path, "\0") || str_contains($path, '\\')
            || preg_match('~(^|/)\.{1,2}(/|$)~', $path)) {
            throw new RuntimeException('Ungültiger Dokumentpfad.');
        }
    }
}

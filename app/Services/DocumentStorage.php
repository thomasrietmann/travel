<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentStorage
{
    public function writePrivate(string $path, string $contents): void
    {
        $stored = Storage::disk('local')->put($path, $contents) && Storage::disk('local')->exists($path);

        foreach ($this->privatePaths($path) as $absolutePath) {
            try {
                File::ensureDirectoryExists(dirname($absolutePath));

                if (File::put($absolutePath, $contents) !== false && File::exists($absolutePath)) {
                    $stored = true;
                }
            } catch (Throwable) {
                // Try all configured storage roots before failing.
            }
        }

        if (! $stored) {
            throw new RuntimeException('Dokument konnte nicht gespeichert werden.');
        }
    }

    /**
     * @return array<int, string>
     */
    public function privatePaths(string $path): array
    {
        $fallbackRoots = collect(config('filesystems.private_fallback_roots', []))
            ->filter()
            ->map(fn (string $root): string => rtrim($root, '/').'/'.$path)
            ->all();

        return array_values(array_unique([
            Storage::disk('local')->path($path),
            storage_path('app/private/'.$path),
            base_path('storage/app/private/'.$path),
            ...$fallbackRoots,
        ]));
    }
}

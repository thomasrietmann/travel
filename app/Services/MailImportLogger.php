<?php

namespace App\Services;

use Throwable;

class MailImportLogger
{
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function path(): string
    {
        return (string) config('mail_import.log_path');
    }

    public function lastLines(int $limit = 50): array
    {
        $path = $this->path();

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        return array_slice(file($path, FILE_IGNORE_NEW_LINES) ?: [], -$limit);
    }

    private function write(string $level, string $message, array $context): void
    {
        try {
            $path = $this->path();
            $directory = dirname($path);

            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return;
            }

            if (! is_writable($directory)) {
                return;
            }

            $contextJson = $context === []
                ? ''
                : ' '.json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            file_put_contents(
                $path,
                '['.now()->toDateTimeString()."] {$level}: {$message}{$contextJson}\n",
                FILE_APPEND | LOCK_EX,
            );
        } catch (Throwable) {
            //
        }
    }
}

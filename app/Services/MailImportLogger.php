<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class MailImportLogger
{
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    public function path(): string
    {
        $path = pathinfo((string) config('mail_import.log_path'));

        return $path['dirname'].'/'.$path['filename'].'-'.now()->format('Y-m-d').'.'.($path['extension'] ?? 'log');
    }

    public function lastLines(int $limit = 50): array
    {
        $handle = @fopen($this->path(), 'rb');
        if (! $handle) {
            return [];
        }
        try {
            $size = fstat($handle)['size'];
            $offset = max(0, $size - 65536);
            fseek($handle, $offset);
            $lines = explode("\n", rtrim(stream_get_contents($handle, 65536)));
            if ($offset > 0) {
                array_shift($lines);
            }

            return array_slice($lines, -max(1, min(100, $limit)));
        } finally {
            fclose($handle);
        }
    }

    private function write(string $level, string $message, array $context): void
    {
        try {
            Log::channel('mail_import')->log($level, $message, array_intersect_key($context, array_flip([
                'user_id', 'admin_user_id', 'trip_id', 'booking_id', 'imported_mail_id',
                'message_number', 'exception_class', 'imported', 'ignored', 'failed', 'deferred',
            ])));
        } catch (Throwable) {
            // Logging failure must not invalidate a committed import.
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class StagingInventory
{
    private const TABLES = ['users', 'user_email_aliases', 'user_birthdays', 'trips', 'trip_user', 'bookings', 'tasks', 'documents', 'imported_mails'];

    /** Read-only fingerprint of business rows and referenced document bytes. Pause all writers first. */
    public function capture(?array $baseline = null): array
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY fehlt.');
        }
        if ($baseline !== null && (($baseline['format'] ?? null) !== 1
            || array_keys($baseline['tables'] ?? []) !== self::TABLES)) {
            throw new RuntimeException('Ungültiges Inventarformat.');
        }
        $result = ['format' => 1, 'key_fingerprint' => hash_hmac('sha256', 'tripcontrol-staging-inventory-v1', $key), 'tables' => [], 'documents' => []];
        foreach (self::TABLES as $table) {
            $available = Schema::getColumnListing($table);
            $columns = $baseline === null ? $available : ($baseline['tables'][$table]['columns'] ?? []);
            if (! is_array($columns) || $columns === [] || ! in_array('id', $columns, true)
                || array_diff($columns, $available) !== []) {
                throw new RuntimeException('Bestandsschema fehlt oder wurde verändert: '.$table);
            }
            // A baseline selects its original columns, so additive migrations remain comparable.
            $digest = hash_init('sha256', HASH_HMAC, $key);
            $count = 0;
            foreach (DB::table($table)->select($columns)->orderBy('id')->cursor() as $row) {
                $values = array_map(fn ($value) => $value === null ? null : base64_encode((string) $value), array_values((array) $row));
                hash_update($digest, json_encode($values, JSON_THROW_ON_ERROR)."\n");
                $count++;
            }
            $result['tables'][$table] = ['columns' => $columns, 'count' => $count, 'hmac_sha256' => hash_final($digest)];
        }
        $storage = app(DocumentStorage::class);
        foreach (DB::table('documents')->orderBy('id')->cursor() as $document) {
            $paths = $storage->paths($document->file_path);
            $hashes = array_unique(array_map($storage->checksum(...), $paths));
            if (count($hashes) !== 1) {
                throw new RuntimeException('Dokument fehlt oder Kopien widersprechen sich; ID '.$document->id);
            }
            $result['documents'][(string) $document->id] = array_values($hashes)[0];
        }

        return $result;
    }
}

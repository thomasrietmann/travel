<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Trip;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BookingAiExtractor
{
    public function extract(Trip $trip, UploadedFile $file): array
    {
        if (! config('ai.openai.api_key')) {
            throw new RuntimeException('OPENAI_API_KEY ist nicht gesetzt.');
        }

        $uploadedFileId = null;

        try {
            $content = [
                ['type' => 'input_text', 'text' => $this->prompt($trip)],
            ];

            if (Str::startsWith((string) $file->getMimeType(), 'image/')) {
                $content[] = [
                    'type' => 'input_image',
                    'image_url' => sprintf(
                        'data:%s;base64,%s',
                        $file->getMimeType(),
                        base64_encode(file_get_contents($file->getRealPath()))
                    ),
                ];
            } else {
                $uploadedFileId = $this->uploadFile($file);
                $content[] = [
                    'type' => 'input_file',
                    'file_id' => $uploadedFileId,
                ];
            }

            $response = $this->client()->post('https://api.openai.com/v1/responses', [
                'model' => config('ai.openai.model'),
                'input' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'tripcontrol_booking_import',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

            if ($response->failed()) {
                throw new RuntimeException('OpenAI konnte das Dokument nicht auswerten: '.$response->body());
            }

            $json = $this->outputText($response->json());
            $data = json_decode($json, true);

            if (! is_array($data)) {
                throw new RuntimeException('OpenAI lieferte keine lesbaren Buchungsdaten.');
            }

            return $data;
        } finally {
            if ($uploadedFileId) {
                try {
                    $this->client()->delete("https://api.openai.com/v1/files/{$uploadedFileId}");
                } catch (Throwable) {
                    // Cleanup must not hide a successful import.
                }
            }
        }
    }

    private function uploadFile(UploadedFile $file): string
    {
        $response = $this->client()
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post('https://api.openai.com/v1/files', [
                'purpose' => 'user_data',
            ]);

        if ($response->failed() || ! $response->json('id')) {
            throw new RuntimeException('Upload zu OpenAI ist fehlgeschlagen: '.$response->body());
        }

        return $response->json('id');
    }

    private function client(): PendingRequest
    {
        return Http::withToken(config('ai.openai.api_key'))
            ->timeout(config('ai.openai.timeout'))
            ->acceptJson();
    }

    private function prompt(Trip $trip): string
    {
        return implode("\n", [
            'Extrahiere aus dem Dokument oder Screenshot eine Reisebuchung fuer TripControl.',
            'Antworte ausschliesslich mit JSON nach Schema.',
            'Nutze nur sichtbare oder klar ableitbare Daten. Unbekannte Textfelder als leeren String liefern, unbekannte Betraege als 0.',
            'Datumswerte immer im Format YYYY-MM-DD liefern. Wenn nur ein Buchungsdatum erkennbar ist, nutze es als start_date und end_date.',
            'Wenn kein Zeitraum erkennbar ist, nutze das Startdatum der Reise.',
            'Kategorie muss eine der erlaubten TripControl-Kategorien sein.',
            'Waehrung muss CHF, EUR, USD, SEK oder NOK sein. Wenn nicht erkennbar, CHF verwenden.',
            'payment_status nur auf paid setzen, wenn bezahlt oder Zahlung erledigt klar erkennbar ist.',
            'Reise-Kontext:',
            'Titel: '.$trip->title,
            'Destination: '.($trip->destination ?: ''),
            'Startdatum: '.$trip->start_date?->toDateString(),
            'Enddatum: '.$trip->end_date?->toDateString(),
        ]);
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'category' => ['type' => 'string', 'enum' => Booking::CATEGORIES],
                'title' => ['type' => 'string'],
                'provider' => ['type' => 'string'],
                'booking_reference' => ['type' => 'string'],
                'amount' => ['type' => 'number'],
                'currency' => ['type' => 'string', 'enum' => Booking::CURRENCIES],
                'start_date' => ['type' => 'string'],
                'end_date' => ['type' => 'string'],
                'booking_status' => ['type' => 'string', 'enum' => Booking::BOOKING_STATUSES],
                'payment_status' => ['type' => 'string', 'enum' => Booking::PAYMENT_STATUSES],
                'due_date' => ['type' => 'string'],
                'cancellation_deadline' => ['type' => 'string'],
                'notes' => ['type' => 'string'],
                'document_title' => ['type' => 'string'],
                'document_type' => ['type' => 'string', 'enum' => Document::TYPES],
            ],
            'required' => [
                'category',
                'title',
                'provider',
                'booking_reference',
                'amount',
                'currency',
                'start_date',
                'end_date',
                'booking_status',
                'payment_status',
                'due_date',
                'cancellation_deadline',
                'notes',
                'document_title',
                'document_type',
            ],
        ];
    }

    private function outputText(array $response): string
    {
        if (isset($response['output_text'])) {
            return $response['output_text'];
        }

        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI-Antwort enthaelt keinen Text.');
    }
}

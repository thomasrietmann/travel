<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class MailAiExtractor
{
    use UsesOpenAi;

    public function extract(User $user, string $subject, string $body, array $attachments): array
    {
        if (! config('ai.openai.api_key')) {
            throw new RuntimeException('OPENAI_API_KEY ist nicht gesetzt.');
        }

        app(AiBudget::class)->consume($user->id);

        $uploadedFileIds = [];

        try {
            $content = [
                ['type' => 'input_text', 'text' => $this->prompt($user, $subject, $body)],
            ];

            foreach (array_slice($attachments, 0, 5) as $attachment) {
                $mimeType = (string) ($attachment['mime_type'] ?? 'application/octet-stream');

                if (Str::startsWith($mimeType, 'image/')) {
                    $content[] = [
                        'type' => 'input_image',
                        'image_url' => sprintf('data:%s;base64,%s', $mimeType, base64_encode($attachment['contents'])),
                    ];

                    continue;
                }

                $uploadedFileId = $this->uploadFile($attachment);
                $uploadedFileIds[] = $uploadedFileId;
                $content[] = [
                    'type' => 'input_file',
                    'file_id' => $uploadedFileId,
                ];
            }

            $response = $this->client()->post('https://api.openai.com/v1/responses', [
                'store' => false,
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
                        'name' => 'tripcontrol_mail_import',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

            if ($response->failed()) {
                throw new RuntimeException('OpenAI konnte die Mail nicht auswerten.');
            }

            $data = json_decode($this->outputText($response->json()), true);

            if (! is_array($data)) {
                throw new RuntimeException('OpenAI lieferte keine lesbaren Maildaten.');
            }

            return $data;
        } finally {
            foreach ($uploadedFileIds as $uploadedFileId) {
                $this->deleteUploadedFile($uploadedFileId);
            }
        }
    }

    private function uploadFile(array $attachment): string
    {
        $response = $this->client()
            ->attach('file', $attachment['contents'], $attachment['filename'] ?? 'mail-attachment')
            ->post('https://api.openai.com/v1/files', [
                'purpose' => 'user_data',
                'expires_after[anchor]' => 'created_at',
                'expires_after[seconds]' => 3600,
            ]);

        if ($response->failed() || ! $response->json('id')) {
            throw new RuntimeException('Upload zu OpenAI ist fehlgeschlagen.');
        }

        return $response->json('id');
    }

    private function prompt(User $user, string $subject, string $body): string
    {
        $trips = $user->trips()
            ->get(['id', 'title', 'destination', 'start_date', 'end_date', 'type', 'status'])
            ->merge($user->sharedTrips()->get([
                'trips.id',
                'trips.title',
                'trips.destination',
                'trips.start_date',
                'trips.end_date',
                'trips.type',
                'trips.status',
            ]))
            ->unique('id')
            ->sortBy(fn (Trip $trip): string => $trip->start_date?->toDateString() ?? '9999-12-31')
            ->map(fn (Trip $trip): string => implode(' | ', [
                'ID '.$trip->id,
                $trip->title,
                $trip->destination ?: '-',
                $trip->start_date?->toDateString() ?: '-',
                $trip->end_date?->toDateString() ?: '-',
                $trip->type,
                $trip->status,
            ]))
            ->implode("\n");

        return implode("\n", [
            'Extrahiere aus dieser weitergeleiteten Mail eine TripControl-Reisebuchung.',
            'Antworte ausschliesslich mit JSON nach Schema.',
            'Erkenne zuerst, fuer wann die Buchung gilt. Der Zeitraum der Buchung ist fuer die spaetere Reisezuordnung wichtiger als Titel, Ort oder Anbieter.',
            'Datumswerte muessen das korrekte Jahr enthalten. Wenn ein Zeitraum erkennbar ist, fuelle start_date und end_date der Buchung entsprechend.',
            'TripControl ordnet die Buchung danach serverseitig einer bestehenden Reise zu, wenn der Buchungszeitraum in eine bestehende Reise faellt oder sich klar mit ihr ueberschneidet.',
            'Setze trip_id nur, wenn der erkannte Buchungszeitraum klar zur bestehenden Reise passt.',
            'Wenn Ort oder Titel passen, das Jahr oder der Zeitraum aber nicht zur bestehenden Reise passt, setze trip_id auf 0 und liefere Daten fuer eine neue Reise.',
            'Wenn in der Mail ein explizites Jahr steht, darf dieses nicht durch ein aehnliches bestehendes Reiseziel aus einem anderen Jahr ersetzt werden.',
            'Wenn keine bestehende Reise passt, setze trip_id auf 0 und liefere saubere Daten fuer eine neue Reise.',
            'Nutze nur sichtbare oder klar ableitbare Daten. Unbekannte Textfelder als leeren String liefern, unbekannte Betraege als 0.',
            'Datumswerte immer als YYYY-MM-DD. Wenn nur ein Datum erkennbar ist, nutze es als Start- und Enddatum.',
            'Waehrung muss CHF, EUR, USD, SEK oder NOK sein. Wenn nicht erkennbar, CHF verwenden.',
            'payment_status nur auf paid setzen, wenn bezahlt oder Zahlung erledigt klar erkennbar ist.',
            'Empfangsadresse: '.config('mail_import.recipient'),
            'Bestehende Reisen:',
            $trips ?: 'Keine bestehenden Reisen vorhanden.',
            'Mail-Betreff: '.$subject,
            'Mail-Inhalt:',
            Str::limit($body, 12000, ''),
        ]);
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'trip_id' => ['type' => 'integer'],
                'trip_title' => ['type' => 'string'],
                'trip_type' => ['type' => 'string', 'enum' => Trip::TYPES],
                'trip_destination' => ['type' => 'string'],
                'trip_start_date' => ['type' => 'string'],
                'trip_end_date' => ['type' => 'string'],
                'trip_status' => ['type' => 'string', 'enum' => Trip::STATUSES],
                'trip_notes' => ['type' => 'string'],
                'create_booking' => ['type' => 'boolean'],
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
                'trip_id',
                'trip_title',
                'trip_type',
                'trip_destination',
                'trip_start_date',
                'trip_end_date',
                'trip_status',
                'trip_notes',
                'create_booking',
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
}

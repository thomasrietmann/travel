<?php

namespace App\Services;

use App\Models\Trip;
use RuntimeException;

class TripSummaryGenerator
{
    use UsesOpenAi;

    public function regenerate(Trip $trip): ?string
    {
        if (! config('ai.openai.api_key')) {
            return null;
        }

        app(AiBudget::class)->consume(request()->user()?->id ?? $trip->user_id);
        $trip->load(['bookings', 'tasks', 'documents.booking']);

        $response = $this->client()->post('https://api.openai.com/v1/responses', [
            'store' => false,
            'model' => config('ai.openai.summary_model') ?: config('ai.openai.model'),
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $this->prompt($trip)],
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI konnte die Reise-Zusammenfassung nicht erstellen.');
        }

        $summary = trim($this->outputText($response->json()));

        if ($summary === '') {
            return null;
        }

        $trip->forceFill(['summary' => $summary])->save();

        return $summary;
    }

    private function prompt(Trip $trip): string
    {
        $bookings = $trip->bookings
            ->map(fn ($booking) => implode(' | ', array_filter([
                $booking->title,
                $booking->provider,
                $booking->date_range_label !== '-' ? 'Zeitraum: '.$booking->date_range_label : null,
                'Status: '.$booking->booking_status_label,
                'Zahlung: '.$booking->payment_status_label,
                number_format((float) $booking->amount, 2).' '.$booking->currency,
                $booking->due_date ? 'Faellig: '.$booking->due_date->format('d.m.Y') : null,
            ])))
            ->implode("\n");

        $documents = $trip->documents
            ->map(fn ($document) => implode(' | ', array_filter([
                $document->title,
                $document->document_type_label,
                $document->booking ? 'Buchung: '.$document->booking->title : null,
            ])))
            ->implode("\n");

        $tasks = $trip->tasks
            ->where('status', 'open')
            ->map(fn ($task) => implode(' | ', array_filter([
                $task->title,
                $task->priority_label,
                $task->due_date ? 'Faellig: '.$task->due_date->format('d.m.Y') : null,
            ])))
            ->implode("\n");

        return implode("\n", [
            'Erstelle eine kurze, hilfreiche Zusammenfassung fuer diese Reise in Deutsch.',
            'Fokus: Reiseinhalt. Beschreibe was diese Reise ist, wohin sie geht, wie gereist wird, welche Orte, Unterkuenfte, Tickets oder Aktivitaeten erkennbar sind und was den Charakter der Reise ausmacht.',
            'Buchungsstatus, Zahlungsstand, fehlende Dokumente und Tasks nur kurz erwaehnen, wenn sie fuer die Reise praktisch wichtig sind. Sie sollen nicht der Mittelpunkt sein.',
            'Schreibe 3 bis 5 kurze Saetze. Keine Markdown-Liste, keine Ueberschrift. Der Text soll sich wie eine kompakte Reisebeschreibung lesen, nicht wie ein Projektstatus.',
            '',
            'Reise:',
            'Titel: '.$trip->title,
            'Typ: '.$trip->type_label,
            'Destination: '.($trip->destination ?: '-'),
            'Zeitraum: '.($trip->start_date?->format('d.m.Y') ?? '-').' - '.($trip->end_date?->format('d.m.Y') ?? '-'),
            'Status: '.$trip->status_label,
            'Buchungen bestätigt: '.$trip->booking_completion_percentage.'%',
            'Zahlungen erledigt: '.$trip->payment_completion_percentage.'%',
            'Total CHF: '.number_format($trip->total_amount_chf, 2),
            'Bezahlt CHF: '.number_format($trip->paid_amount_chf, 2),
            'Offen CHF: '.number_format($trip->open_amount_chf, 2),
            'Offene Tasks: '.$trip->open_tasks_count,
            'Naechste Deadline: '.($trip->next_due_date?->format('d.m.Y') ?? '-'),
            '',
            'Buchungen:',
            $bookings ?: '-',
            '',
            'Dokumente:',
            $documents ?: '-',
            '',
            'Offene Tasks:',
            $tasks ?: '-',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingImportRequest;
use App\Models\Document;
use App\Models\Trip;
use App\Services\BookingAiExtractor;
use App\Services\BookingData;
use App\Services\DocumentStorage;
use App\Services\TripSummaryGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Throwable;

class BookingImportController extends Controller
{
    public function store(BookingImportRequest $request, Trip $trip, BookingAiExtractor $extractor, TripSummaryGenerator $summaryGenerator, DocumentStorage $documentStorage): RedirectResponse
    {
        $this->authorize('update', $trip);

        $created = 0;
        $failed = [];
        $files = $this->uploadedFiles($request->file('booking_documents', []));

        foreach ($files as $file) {
            try {
                $extracted = $extractor->extract($trip, $file);
                $validator = Validator::make(BookingData::normalize($extracted, $trip), BookingData::rules());

                if ($validator->fails()) {
                    $failed[] = $file->getClientOriginalName().': Die AI-Auswertung konnte nicht in eine gueltige Buchung umgewandelt werden.';

                    continue;
                }

                $documentStorage->transaction(function () use ($trip, $validator, $file, $extracted, $documentStorage): void {
                    $booking = $trip->bookings()->create($validator->validated());
                    $path = $documentStorage->upload($trip->id, $file);

                    $trip->documents()->create([
                        'booking_id' => $booking->id,
                        'title' => BookingData::clean($extracted['document_title'] ?? '') ?: $booking->title,
                        'file_path' => $path,
                        'document_type' => in_array($extracted['document_type'] ?? '', Document::TYPES, true) ? $extracted['document_type'] : 'confirmation',
                        'notes' => 'Automatisch aus Buchungsimport erstellt.',
                    ]);
                });

                $created++;
            } catch (Throwable $exception) {
                $failed[] = $file->getClientOriginalName().': Import fehlgeschlagen. Bitte später erneut versuchen.';
            }
        }

        if ($created === 0) {
            return back()
                ->withInput()
                ->withErrors(['booking_documents' => implode(' ', $failed)]);
        }

        $this->regenerateSummary($summaryGenerator, $trip);

        $message = $created === 1
            ? '1 Buchung wurde per AI-Import erstellt. Bitte pruefe die erkannten Daten kurz.'
            : "{$created} Buchungen wurden per AI-Import erstellt. Bitte pruefe die erkannten Daten kurz.";

        if ($failed !== []) {
            $message .= ' Nicht importiert: '.implode(' ', $failed);
        }

        return redirect()
            ->route('trips.show', $trip)
            ->with('status', $message);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedFiles(array|UploadedFile|null $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter($files ?? [], fn ($file) => $file instanceof UploadedFile));
    }

    private function regenerateSummary(TripSummaryGenerator $summaryGenerator, Trip $trip): void
    {
        try {
            $summaryGenerator->regenerate($trip->refresh());
        } catch (Throwable) {
            // Erfolgreich importierte Buchungen bleiben bestehen, auch wenn die AI-Summary fehlschlaegt.
        }
    }
}

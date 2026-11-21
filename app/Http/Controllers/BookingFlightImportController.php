<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingFlightImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BookingFlightImportController extends Controller
{
    public function __invoke(Request $request, Booking $booking, BookingFlightImporter $importer): RedirectResponse
    {
        $this->authorize('update', $booking);
        abort_unless($booking->category === 'flight', 422);
        $request->validate(['retry_failed' => ['sometimes', 'boolean']]);
        $redirect = redirect()->to(route('trips.show', $booking->trip).'#tagesplan');
        try {
            $stats = $importer->extract($booking, $request->boolean('retry_failed'));
        } catch (Throwable) {
            return $redirect->with('error', 'Die Auswertung konnte nicht gestartet werden oder läuft bereits. Bitte versuche es später erneut.');
        }
        if ($stats['documents'] === 0) {
            return $redirect->with('error', 'Hinterlege zuerst eine Flugbestätigung oder ein Ticket bei dieser Buchung.');
        }
        $message = $stats['created'].' Flugstrecken zum Tagesplan hinzugefügt.';
        if ($stats['cached'] > 0) {
            $message .= ' Bereits ausgewertete Unterlagen bleiben gespeichert.';
        }
        if ($stats['review'] > 0) {
            $message .= ' Einige Angaben benötigen eine manuelle Prüfung bei der Buchung.';
        }
        if ($stats['failed'] > 0) {
            $message .= ' Einige Dokumente konnten nicht ausgewertet werden. Prüfe die Buchung für eine gezielte Wiederholung.';
        }
        $message .= ' Bitte prüfe die Flugtage, Ortszeiten und Zeitzonen.';

        return $redirect->with('status', $message);
    }
}

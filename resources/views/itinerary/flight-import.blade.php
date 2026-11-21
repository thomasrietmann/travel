@php
    $extractions = $booking->flightExtractions;
    $completed = $extractions->where('status', 'completed')->count();
    $failed = $extractions->where('status', 'failed')->isNotEmpty();
@endphp
@if ($booking->documents->whereNotIn('document_type', ['passport', 'insurance'])->isEmpty())
    <a href="{{ route('bookings.edit', $booking) }}#document_file" class="inline-block py-1 text-sm font-medium text-slate-700 underline">Flugbestätigung hinzufügen</a>
@else
    <div class="space-y-2 text-sm">
        @if ($completed > 0)
            <p class="text-xs text-slate-600">Die Auswertung der Unterlagen ist gespeichert. Bereits gelesene Dateien werden beim erneuten Prüfen wiederverwendet.</p>
            @if ($extractions->where('status', 'completed')->every(fn ($extraction) => $extraction->segments === []))
                <p class="text-xs text-slate-600">Die ausgewerteten Unterlagen enthalten keine erkennbaren einzelnen Flugtage. Du kannst die Teilstrecken manuell ergänzen oder eine ausführlichere Flugbestätigung hinterlegen.</p>
            @endif
        @else
            <p class="text-xs text-slate-600">AI liest die einzelnen Flugtage, Abflug- und Ankunftszeiten aus den hinterlegten Unterlagen. Die Ergebnisse werden gespeichert.</p>
        @endif
        @if ($extractions->where('status', 'review')->isNotEmpty())
            <p class="text-xs text-amber-800">Einige Flugangaben benötigen eine manuelle Prüfung. Bitte ergänze die Teilstrecken anhand der Flugbestätigung.</p>
        @endif
        @if ($extractions->where('status', 'processing')->isNotEmpty())
            <p class="text-xs text-amber-800">Eine Auswertung ist noch offen. Warte auf den Abschluss oder ergänze die Teilstrecken manuell.</p>
        @endif
        <form method="POST" action="{{ route('bookings.flights.import', $booking) }}" data-flight-import-form>
            @csrf
            <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ $completed ? 'Neue Unterlagen prüfen' : 'Flugstrecken aus Dokumenten lesen' }}</button>
        </form>
        @if ($failed)
            <form method="POST" action="{{ route('bookings.flights.import', $booking) }}" data-flight-import-form>
                @csrf
                <input type="hidden" name="retry_failed" value="1">
                <button class="py-1 text-sm font-medium text-slate-700 underline">Fehlgeschlagene Auswertung erneut versuchen</button>
            </form>
        @endif
    </div>
@endif

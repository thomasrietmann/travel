<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function create(Trip $trip): View
    {
        $this->authorize('update', $trip);

        return view('documents.create', [
            'trip' => $trip->load('bookings'),
            'document' => new Document(),
        ]);
    }

    public function store(DocumentRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validated();
        abort_if(
            isset($validated['booking_id']) && ! $trip->bookings()->whereKey($validated['booking_id'])->exists(),
            422,
            'Die ausgewaehlte Buchung gehoert nicht zu dieser Reise.'
        );

        $validated['file_path'] = $request->file('file')->store("documents/{$trip->id}", 'public');
        unset($validated['file']);

        $trip->documents()->create($validated);

        return redirect()->route('trips.show', $trip)->with('status', 'Dokument wurde hochgeladen.');
    }

    public function edit(Document $document): View
    {
        $this->authorize('update', $document);

        return view('documents.edit', [
            'trip' => $document->trip->load('bookings'),
            'document' => $document,
        ]);
    }

    public function update(DocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validated();
        abort_if(
            isset($validated['booking_id']) && ! $document->trip->bookings()->whereKey($validated['booking_id'])->exists(),
            422,
            'Die ausgewaehlte Buchung gehoert nicht zu dieser Reise.'
        );

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($document->file_path);
            $validated['file_path'] = $request->file('file')->store("documents/{$document->trip_id}", 'public');
        }

        unset($validated['file']);
        $document->update($validated);

        return redirect()->route('trips.show', $document->trip)->with('status', 'Dokument wurde aktualisiert.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $trip = $document->trip;
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return redirect()->route('trips.show', $trip)->with('status', 'Dokument wurde geloescht.');
    }
}

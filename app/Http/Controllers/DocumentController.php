<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const DOCUMENT_DISK = 'local';
    private const LEGACY_DOCUMENT_DISK = 'public';

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

        $files = $request->file('files', []);
        $uploaded = 0;

        foreach ($files as $file) {
            $trip->documents()->create([
                'booking_id' => $validated['booking_id'] ?? null,
                'title' => $this->documentTitle($validated['title'] ?? null, $file->getClientOriginalName(), count($files)),
                'file_path' => $file->store("documents/{$trip->id}", self::DOCUMENT_DISK),
                'document_type' => $validated['document_type'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $uploaded++;
        }

        $message = $uploaded === 1 ? 'Dokument wurde hochgeladen.' : "{$uploaded} Dokumente wurden hochgeladen.";

        return redirect()->route('trips.show', $trip)->with('status', $message);
    }

    public function edit(Document $document): View
    {
        $this->authorize('update', $document);

        return view('documents.edit', [
            'trip' => $document->trip->load('bookings'),
            'document' => $document,
        ]);
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        $disk = $this->documentDisk($document);
        abort_unless($disk, 404);

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $fileName = Str::slug($document->title) ?: pathinfo($document->file_path, PATHINFO_FILENAME);

        if ($extension) {
            $fileName .= ".{$extension}";
        }

        return Storage::disk($disk)->download($document->file_path, $fileName);
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
            Storage::disk($this->documentDisk($document) ?? self::DOCUMENT_DISK)->delete($document->file_path);
            $validated['file_path'] = $request->file('file')->store("documents/{$document->trip_id}", self::DOCUMENT_DISK);
        }

        unset($validated['file']);
        $document->update($validated);

        return redirect()->route('trips.show', $document->trip)->with('status', 'Dokument wurde aktualisiert.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $trip = $document->trip;
        Storage::disk($this->documentDisk($document) ?? self::DOCUMENT_DISK)->delete($document->file_path);
        $document->delete();

        return redirect()->route('trips.show', $trip)->with('status', 'Dokument wurde geloescht.');
    }

    private function documentTitle(?string $title, string $originalName, int $fileCount): string
    {
        if ($fileCount === 1 && filled($title)) {
            return $title;
        }

        return pathinfo($originalName, PATHINFO_FILENAME) ?: 'Dokument';
    }

    private function documentDisk(Document $document): ?string
    {
        if (Storage::disk(self::DOCUMENT_DISK)->exists($document->file_path)) {
            return self::DOCUMENT_DISK;
        }

        if (Storage::disk(self::LEGACY_DOCUMENT_DISK)->exists($document->file_path)) {
            return self::LEGACY_DOCUMENT_DISK;
        }

        return null;
    }
}

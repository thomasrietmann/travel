<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use App\Models\Trip;
use App\Services\DocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentStorage $storage) {}

    public function create(Trip $trip): View
    {
        $this->authorize('update', $trip);

        return view('documents.create', [
            'trip' => $trip->load('bookings'),
            'document' => new Document,
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

        $this->storage->transaction(function () use ($files, $trip, $validated, &$uploaded): void {
            foreach ($files as $file) {
                $trip->documents()->create([
                    'booking_id' => $validated['booking_id'] ?? null,
                    'title' => $this->documentTitle($validated['title'] ?? null, $file->getClientOriginalName(), count($files)),
                    'file_path' => $this->storage->upload($trip->id, $file),
                    'document_type' => $validated['document_type'],
                    'notes' => $validated['notes'] ?? null,
                ]);
                $uploaded++;
            }
        });

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

    public function download(Document $document): BinaryFileResponse|RedirectResponse|StreamedResponse
    {
        $this->authorize('view', $document);

        $location = $this->storage->locate($document->file_path);

        if (! $location) {
            return back()->with('error', 'Die Datei wurde nicht gefunden. Bitte lade das Dokument erneut hoch.');
        }

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $fileName = Str::slug($document->title) ?: pathinfo($document->file_path, PATHINFO_FILENAME);

        if ($extension) {
            $fileName .= ".{$extension}";
        }

        return response()->download($location, $fileName, ['X-Content-Type-Options' => 'nosniff']);
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

        $this->storage->transaction(function () use ($request, $document, $validated): void {
            // Lock the row so two replacements cannot lose track of the previous file.
            $current = Document::query()->lockForUpdate()->findOrFail($document->id);
            if ($request->hasFile('file')) {
                $validated['file_path'] = $this->storage->upload($current->trip_id, $request->file('file'));
                $this->storage->scheduleDeletion($current->file_path);
            }
            unset($validated['file']);
            $current->update($validated);
        });

        return redirect()->route('trips.show', $document->trip)->with('status', 'Dokument wurde aktualisiert.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $trip = $document->trip;
        $this->storage->transaction(function () use ($document): void {
            $current = Document::query()->lockForUpdate()->findOrFail($document->id);
            $this->storage->scheduleDeletion($current->file_path);
            $current->delete();
        });

        return redirect()->route('trips.show', $trip)->with('status', 'Dokument wurde geloescht.');
    }

    private function documentTitle(?string $title, string $originalName, int $fileCount): string
    {
        if ($fileCount === 1 && filled($title)) {
            return $title;
        }

        return pathinfo($originalName, PATHINFO_FILENAME) ?: 'Dokument';
    }
}

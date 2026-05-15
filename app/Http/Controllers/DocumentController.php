<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    public function download(Document $document): BinaryFileResponse|RedirectResponse|StreamedResponse
    {
        $this->authorize('view', $document);

        $location = $this->documentLocation($document);

        if (! $location) {
            return back()->with('error', 'Datei wurde nicht gefunden. Geprüft: '.$this->checkedPathsLabel($document));
        }

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $fileName = Str::slug($document->title) ?: pathinfo($document->file_path, PATHINFO_FILENAME);

        if ($extension) {
            $fileName .= ".{$extension}";
        }

        if (isset($location['disk'])) {
            return Storage::disk($location['disk'])->download($document->file_path, $fileName);
        }

        return response()->download($location['path'], $fileName);
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
            $this->deleteDocumentFile($document);
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
        $this->deleteDocumentFile($document);
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

    private function documentLocation(Document $document): ?array
    {
        if (Storage::disk(self::DOCUMENT_DISK)->exists($document->file_path)) {
            return ['disk' => self::DOCUMENT_DISK];
        }

        if (Storage::disk(self::LEGACY_DOCUMENT_DISK)->exists($document->file_path)) {
            return ['disk' => self::LEGACY_DOCUMENT_DISK];
        }

        foreach ($this->legacyAbsolutePaths($document) as $path) {
            if (File::exists($path)) {
                return ['path' => $path];
            }
        }

        return null;
    }

    private function deleteDocumentFile(Document $document): void
    {
        $location = $this->documentLocation($document);

        if (! $location) {
            return;
        }

        if (isset($location['disk'])) {
            Storage::disk($location['disk'])->delete($document->file_path);

            return;
        }

        File::delete($location['path']);
    }

    private function legacyAbsolutePaths(Document $document): array
    {
        return array_values(array_unique([
            Storage::disk(self::DOCUMENT_DISK)->path($document->file_path),
            Storage::disk(self::LEGACY_DOCUMENT_DISK)->path($document->file_path),
            storage_path('app/private/'.$document->file_path),
            storage_path('app/public/'.$document->file_path),
            base_path('storage/app/private/'.$document->file_path),
            base_path('storage/app/public/'.$document->file_path),
            '/travel.git/storage/app/private/'.$document->file_path,
            '/travel.git/storage/app/public/'.$document->file_path,
        ]));
    }

    private function checkedPathsLabel(Document $document): string
    {
        $checks = [
            'local disk' => [
                'path' => $document->file_path,
                'exists' => Storage::disk(self::DOCUMENT_DISK)->exists($document->file_path),
                'readable' => null,
            ],
            'public disk' => [
                'path' => $document->file_path,
                'exists' => Storage::disk(self::LEGACY_DOCUMENT_DISK)->exists($document->file_path),
                'readable' => null,
            ],
        ];

        foreach ($this->legacyAbsolutePaths($document) as $path) {
            $checks[$path] = [
                'path' => $path,
                'exists' => File::exists($path),
                'readable' => is_readable($path),
            ];
        }

        return collect($checks)
            ->map(fn (array $check, string $label): string => $label.': '.$check['path']
                .' (exists: '.($check['exists'] ? 'ja' : 'nein')
                .($check['readable'] === null ? '' : ', readable: '.($check['readable'] ? 'ja' : 'nein'))
                .')')
            ->implode(' | ');
    }
}

@php
    use App\Models\Document;
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="title" class="block text-sm font-medium text-slate-700">Titel</label>
        <input id="title" name="title" value="{{ old('title', $document->title) }}" @required($document->exists) class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @unless ($document->exists)
            <p class="mt-1 text-xs text-slate-500">Optional. Bei mehreren Dateien wird der Dateiname als Titel verwendet.</p>
        @endunless
        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="document_type" class="block text-sm font-medium text-slate-700">Dokumenttyp</label>
        <select id="document_type" name="document_type" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Document::TYPES as $type)
                <option value="{{ $type }}" @selected(old('document_type', $document->document_type ?? 'other') === $type)>{{ Document::TYPE_LABELS[$type] }}</option>
            @endforeach
        </select>
        @error('document_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="booking_id" class="block text-sm font-medium text-slate-700">Buchung optional</label>
        <select id="booking_id" name="booking_id" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            <option value="">Direkt zur Reise</option>
            @foreach ($trip->bookings as $booking)
                <option value="{{ $booking->id }}" @selected((int) old('booking_id', $document->booking_id) === $booking->id)>{{ $booking->title }}</option>
            @endforeach
        </select>
        @error('booking_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="{{ $document->exists ? 'file' : 'files' }}" class="block text-sm font-medium text-slate-700">{{ $document->exists ? 'Datei' : 'Dateien' }}</label>
        @if ($document->exists)
            <input id="file" name="file" type="file" class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800">
        @else
            <input id="files" name="files[]" type="file" multiple required class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800">
            <p class="mt-1 text-xs text-slate-500">Bis zu 10 Dateien gleichzeitig, maximal 10 MB pro Datei.</p>
        @endif
        @if ($document->exists)
            <p class="mt-1 text-xs text-slate-500">Leer lassen, um die bestehende Datei zu behalten.</p>
        @endif
        @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('files') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('files.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="block text-sm font-medium text-slate-700">Notizen</label>
        <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('notes', $document->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

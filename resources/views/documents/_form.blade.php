@php
    use App\Models\Document;
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="title" class="block text-sm font-medium text-slate-700">Titel</label>
        <input id="title" name="title" value="{{ old('title', $document->title) }}" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="document_type" class="block text-sm font-medium text-slate-700">Dokumenttyp</label>
        <select id="document_type" name="document_type" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Document::TYPES as $type)
                <option value="{{ $type }}" @selected(old('document_type', $document->document_type ?? 'other') === $type)>{{ $type }}</option>
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
        <label for="file" class="block text-sm font-medium text-slate-700">Datei</label>
        <input id="file" name="file" type="file" @required(! $document->exists) class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800">
        @if ($document->exists)
            <p class="mt-1 text-xs text-slate-500">Leer lassen, um die bestehende Datei zu behalten.</p>
        @endif
        @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="block text-sm font-medium text-slate-700">Notizen</label>
        <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('notes', $document->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

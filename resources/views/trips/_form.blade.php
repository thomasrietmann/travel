@php
    use App\Models\Trip;
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="title" class="block text-sm font-medium text-slate-700">Titel</label>
        <input id="title" name="title" value="{{ old('title', $trip->title) }}" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="type" class="block text-sm font-medium text-slate-700">Typ</label>
        <select id="type" name="type" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Trip::TYPES as $type)
                <option value="{{ $type }}" @selected(old('type', $trip->type) === $type)>{{ str_replace('_', ' ', $type) }}</option>
            @endforeach
        </select>
        @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Trip::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $trip->status) === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="destination" class="block text-sm font-medium text-slate-700">Destination</label>
        <input id="destination" name="destination" value="{{ old('destination', $trip->destination) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('destination') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="start_date" class="block text-sm font-medium text-slate-700">Startdatum</label>
        <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $trip->start_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="end_date" class="block text-sm font-medium text-slate-700">Enddatum</label>
        <input id="end_date" name="end_date" type="date" value="{{ old('end_date', $trip->end_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="block text-sm font-medium text-slate-700">Notizen</label>
        <textarea id="notes" name="notes" rows="5" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('notes', $trip->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

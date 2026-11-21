@extends('layouts.app')

@section('content')
    @php
        $flight = $booking?->category === 'flight';
        $name = $flight ? 'Teilstrecke' : 'Stopp';
        $returnUrl = route('trips.show', [$trip, 'tag' => $stop->start_date?->toDateString()]).'#tagesplan';
        $inputClass = 'mt-1 block w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-700';
        $headings = match ($booking?->category) {
            'flight' => ['Abflug', 'Ankunft'],
            'hotel' => ['Check-in', 'Check-out'],
            'car', 'camper' => ['Abholung', 'Rückgabe'],
            default => ['Start', 'Ende'],
        };
    @endphp
    <div class="mx-auto max-w-3xl">
        <a href="{{ $returnUrl }}" class="text-sm font-medium text-slate-600 hover:underline">Zurück zum Tagesplan</a>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight">{{ $name }} {{ $stop->exists ? 'bearbeiten' : 'hinzufügen' }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $trip->title }} @if ($booking) · {{ $booking->title }} @if ($booking->booking_reference) · Ref. {{ $booking->booking_reference }} @endif @if ($booking->start_date || $booking->end_date) · {{ $booking->date_range_label }} @endif @endif</p>
        @if ($flight)
            <p class="mt-2 text-sm text-slate-600">Erfasse jeden Flugabschnitt mit seinen Ortszeiten. Preis und Belege gehören zur gemeinsamen Flugbuchung.</p>
        @endif

        <form method="POST" action="{{ $stop->exists ? route('trips.stops.update', [$trip, $stop]) : route('trips.stops.store', $trip) }}" class="mt-5 space-y-5 rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
            @csrf
            @if ($stop->exists)
                @method('PUT')
            @elseif ($booking)
                <input name="booking_id" type="hidden" value="{{ $booking->id }}">
            @endif
            @error('booking_id') <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
            <div>
                <label for="title" class="text-sm font-medium">{{ $flight ? 'Flugnummer oder Bezeichnung' : 'Bezeichnung' }}</label>
                <input id="title" name="title" required maxlength="255" value="{{ old('title', $stop->title) }}" class="{{ $inputClass }}">
                @error('title') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            @foreach (['start' => $headings[0], 'end' => $headings[1]] as $prefix => $heading)
                <fieldset class="min-w-0 border-t border-slate-200">
                    <legend class="pr-3 font-semibold">{{ $heading }} @if ($prefix === 'end') <span class="text-xs font-normal text-slate-600">(optional)</span> @endif</legend>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="{{ $prefix }}_date" class="text-sm font-medium">Ortsdatum</label>
                            <input type="date" id="{{ $prefix }}_date" name="{{ $prefix }}_date" @required($prefix === 'start') value="{{ old($prefix.'_date', $stop->{$prefix.'_date'}?->toDateString()) }}" class="{{ $inputClass }}">
                            @error($prefix.'_date') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="{{ $prefix }}_time" class="text-sm font-medium">Ortszeit</label>
                            <input type="time" id="{{ $prefix }}_time" name="{{ $prefix }}_time" value="{{ old($prefix.'_time', substr((string) $stop->{$prefix.'_time'}, 0, 5)) }}" class="{{ $inputClass }}">
                            @error($prefix.'_time') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="{{ $prefix }}_location" class="text-sm font-medium">{{ $flight ? 'Flughafen' : 'Ort oder Adresse' }}</label>
                            <input id="{{ $prefix }}_location" name="{{ $prefix }}_location" maxlength="255" value="{{ old($prefix.'_location', $stop->{$prefix.'_location'}) }}" class="{{ $inputClass }}">
                            @error($prefix.'_location') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="{{ $prefix }}_timezone" class="text-sm font-medium">Zeitzone</label>
                            <input id="{{ $prefix }}_timezone" name="{{ $prefix }}_timezone" list="timezones" placeholder="z. B. Europe/Zurich" value="{{ old($prefix.'_timezone', $stop->{$prefix.'_timezone'}) }}" class="{{ $inputClass }}">
                            @error($prefix.'_timezone') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </fieldset>
            @endforeach
            <datalist id="timezones">
                @foreach (\DateTimeZone::listIdentifiers() as $zone)
                    <option value="{{ $zone }}"></option>
                @endforeach
            </datalist>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                @if ($stop->exists)
                    <button form="remove-stop" class="mr-auto rounded-md px-2 py-2 text-sm font-medium text-red-700 hover:underline">Eintrag entfernen</button>
                @endif
                <a href="{{ $returnUrl }}" class="px-2 py-2 text-sm font-medium text-slate-600">Abbrechen</a>
                <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Speichern</button>
            </div>
        </form>
        @if ($stop->exists)
            <form id="remove-stop" method="POST" action="{{ route('trips.stops.destroy', [$trip, $stop]) }}" data-confirm="Diesen Eintrag aus dem Tagesplan entfernen?">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
@endsection

@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ route('trips.show', $trip) }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Zurück zur Reise</a>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">Buchung bearbeiten</h1>
    </div>

    <form method="POST" action="{{ route('bookings.update', $booking) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        @include('bookings._form')
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between">
            <button form="delete-booking" class="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Löschen</button>
            <div class="flex justify-end gap-3">
                <a href="{{ route('trips.show', $trip) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Abbrechen</a>
                <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Aktualisieren</button>
            </div>
        </div>
    </form>

    <form id="delete-booking" method="POST" action="{{ route('bookings.destroy', $booking) }}" onsubmit="return confirm('Buchung wirklich loeschen?')">
        @csrf
        @method('DELETE')
    </form>
@endsection

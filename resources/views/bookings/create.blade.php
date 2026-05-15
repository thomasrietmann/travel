@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ route('trips.show', $trip) }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Zurück zur Reise</a>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">Buchung erstellen</h1>
    </div>

    <form method="POST" action="{{ route('trips.bookings.store', $trip) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @include('bookings._form')
        <div class="flex justify-end gap-3">
            <a href="{{ route('trips.show', $trip) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Abbrechen</a>
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Speichern</button>
        </div>
    </form>
@endsection

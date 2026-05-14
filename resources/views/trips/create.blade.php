@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Zurueck zum Dashboard</a>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">Reise erstellen</h1>
    </div>

    <form method="POST" action="{{ route('trips.store') }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('trips._form')
        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Abbrechen</a>
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Speichern</button>
        </div>
    </form>
@endsection

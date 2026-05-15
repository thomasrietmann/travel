@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Countdown</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Kommende Reisen</h1>
        </div>
        <a href="{{ route('trips.create') }}" class="inline-flex items-center justify-center rounded-md bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Reise erstellen</a>
    </div>

    @if ($trips->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
            <h2 class="text-lg font-semibold">Keine kommenden Reisen</h2>
            <p class="mt-2 text-sm text-slate-600">Alle Reisen sind gestartet oder abgeschlossen.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($trips as $trip)
                <a href="{{ route('trips.show', $trip) }}" class="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">{{ $trip->title }}</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $trip->destination ?: 'Keine Destination' }} -
                                {{ $trip->start_date?->format('d.m.Y') }}
                                @if ($trip->end_date)
                                    - {{ $trip->end_date->format('d.m.Y') }}
                                @endif
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $trip->type_label }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $trip->status_label }}</span>
                            </div>
                        </div>

                        <div class="rounded-lg bg-slate-950 px-5 py-4 text-center text-white">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-300">Start in</p>
                            <p class="mt-1 text-2xl font-semibold">{{ $trip->starts_in_label }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection

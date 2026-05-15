@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Dokumente</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Alle Dokumente</h1>
        </div>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Zur Übersicht</a>
    </div>

    @if ($documentsByTrip->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
            <h2 class="text-lg font-semibold">Keine Dokumente vorhanden</h2>
            <p class="mt-2 text-sm text-slate-600">Sobald Dokumente hochgeladen wurden, erscheinen sie hier gesammelt.</p>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($documentsByTrip as $documents)
                @php($trip = $documents->first()->trip)

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">{{ $trip->title }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $trip->destination ?: 'Keine Destination' }} · {{ $documents->count() }} Dokumente</p>
                        </div>
                        <a href="{{ route('trips.show', $trip) }}" class="text-sm font-medium text-slate-700 hover:text-slate-950 hover:underline">Reise öffnen</a>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach ($documents as $document)
                            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <a href="{{ route('documents.download', $document) }}" class="font-medium text-slate-950 hover:underline">{{ $document->title }}</a>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ $document->document_type_label }}
                                        @if ($document->booking)
                                            · {{ $document->booking->title }}
                                        @endif
                                        · {{ $document->created_at?->format('d.m.Y') ?? '-' }}
                                    </p>
                                    @if ($document->notes)
                                        <p class="mt-1 max-w-xl text-sm text-slate-500">{{ $document->notes }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection

@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Dokumente</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Alle Dokumente</h1>
        </div>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Zur Übersicht</a>
    </div>

    @if ($documents->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
            <h2 class="text-lg font-semibold">Keine Dokumente vorhanden</h2>
            <p class="mt-2 text-sm text-slate-600">Sobald Dokumente hochgeladen wurden, erscheinen sie hier gesammelt.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Dokument</th>
                            <th class="px-5 py-3">Reise</th>
                            <th class="px-5 py-3">Buchung</th>
                            <th class="px-5 py-3">Typ</th>
                            <th class="px-5 py-3">Hinzugefügt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($documents as $document)
                            <tr>
                                <td class="px-5 py-4">
                                    <a href="{{ route('documents.download', $document) }}" class="font-medium text-slate-950 hover:underline">{{ $document->title }}</a>
                                    @if ($document->notes)
                                        <div class="mt-1 max-w-xl text-slate-500">{{ $document->notes }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('trips.show', $document->trip) }}" class="font-medium text-slate-700 hover:text-slate-950 hover:underline">{{ $document->trip->title }}</a>
                                </td>
                                <td class="px-5 py-4">{{ $document->booking?->title ?? '-' }}</td>
                                <td class="px-5 py-4">{{ $document->document_type_label }}</td>
                                <td class="px-5 py-4">{{ $document->created_at?->format('d.m.Y') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Tasks</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Offene Aufgaben</h1>
        </div>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Zur Übersicht</a>
    </div>

    @if ($tasks->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
            <h2 class="text-lg font-semibold">Keine offenen Aufgaben</h2>
            <p class="mt-2 text-sm text-slate-600">Alles erledigt. Sehr angenehmer Zustand.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Aufgabe</th>
                            <th class="px-5 py-3">Reise</th>
                            <th class="px-5 py-3">Priorität</th>
                            <th class="px-5 py-3">Fällig</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tasks as $task)
                            @php
                                $dueClass = $task->due_date?->isBefore(today()) ? 'text-red-700' : 'text-slate-900';
                            @endphp
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-950">{{ $task->title }}</div>
                                    @if ($task->notes)
                                        <div class="mt-1 max-w-xl text-slate-500">{{ $task->notes }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('trips.show', $task->trip) }}" class="font-medium text-slate-700 hover:text-slate-950 hover:underline">{{ $task->trip->title }}</a>
                                </td>
                                <td class="px-5 py-4">{{ $task->priority_label }}</td>
                                <td class="px-5 py-4 font-medium {{ $dueClass }}">{{ $task->due_date?->format('d.m.Y') ?? 'ohne Datum' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('tasks.edit', $task) }}" class="font-medium text-slate-700 hover:text-slate-950">Bearbeiten</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Benutzer Details</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">{{ $managedUser->name }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $managedUser->email }}</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Zurueck</a>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-sm text-slate-500">Rolle</dt>
            <dd class="mt-2 text-2xl font-semibold">{{ $managedUser->is_admin ? 'Admin' : 'User' }}</dd>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-sm text-slate-500">Reisen</dt>
            <dd class="mt-2 text-2xl font-semibold">{{ $managedUser->trips_count }}</dd>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-sm text-slate-500">Buchungen</dt>
            <dd class="mt-2 text-2xl font-semibold">{{ $managedUser->bookings_count }}</dd>
        </div>
    </div>

    @if ($managedUser->emailAliases->isNotEmpty())
        <div class="mb-8 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Email Aliase</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($managedUser->emailAliases as $alias)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">{{ $alias->email }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Reise</th>
                    <th class="px-4 py-3">Zeitraum</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Buchungen</th>
                    <th class="px-4 py-3 text-right">Tasks</th>
                    <th class="px-4 py-3 text-right">Dokumente</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($managedUser->trips as $trip)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-950">{{ $trip->title }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $trip->start_date?->format('d.m.Y') ?? '-' }}
                            @if ($trip->end_date)
                                - {{ $trip->end_date->format('d.m.Y') }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $trip->status_label }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $trip->bookings_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $trip->tasks_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $trip->documents_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Keine Reisen vorhanden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

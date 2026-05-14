@extends('layouts.app')

@section('content')
    @php
        $lightClass = [
            'green' => 'bg-emerald-500',
            'yellow' => 'bg-amber-400',
            'red' => 'bg-red-500',
        ][$trip->traffic_light];
        $totalsByCurrency = $trip->bookings->groupBy('currency')->map(fn ($items) => $items->sum('amount'));
        $paidByCurrency = $trip->bookings->where('payment_status', 'paid')->groupBy('currency')->map(fn ($items) => $items->sum('amount'));
        $openByCurrency = $trip->bookings->whereIn('payment_status', ['unpaid', 'partially_paid'])->groupBy('currency')->map(fn ($items) => $items->sum('amount'));
    @endphp

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Zurueck zum Dashboard</a>
            <div class="mt-3 flex items-center gap-3">
                <span class="h-3 w-3 rounded-full {{ $lightClass }}"></span>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ $trip->title }}</h1>
            </div>
            <p class="mt-2 text-slate-600">
                {{ $trip->destination ?: 'Keine Destination' }} -
                {{ $trip->start_date?->format('d.m.Y') ?? 'ohne Start' }}
                @if ($trip->end_date)
                    - {{ $trip->end_date->format('d.m.Y') }}
                @endif
            </p>
            <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $trip->type_label }}</span>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $trip->status_label }}</span>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('trips.edit', $trip) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Bearbeiten</a>
            <form method="POST" action="{{ route('trips.destroy', $trip) }}" onsubmit="return confirm('Reise wirklich loeschen?')">
                @csrf
                @method('DELETE')
                <button class="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Loeschen</button>
            </form>
        </div>
    </div>

    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200 text-sm font-medium">
        <a href="#overview" class="px-3 py-2 text-slate-700 hover:text-slate-950">Uebersicht</a>
        <a href="#bookings" class="px-3 py-2 text-slate-700 hover:text-slate-950">Buchungen</a>
        <a href="#payments" class="px-3 py-2 text-slate-700 hover:text-slate-950">Zahlungen</a>
        <a href="#tasks" class="px-3 py-2 text-slate-700 hover:text-slate-950">Aufgaben</a>
        <a href="#documents" class="px-3 py-2 text-slate-700 hover:text-slate-950">Dokumente</a>
    </div>

    <section id="overview" class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Buchungen bestaetigt</p>
            <p class="mt-2 text-2xl font-semibold">{{ $trip->booking_completion_percentage }}%</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Zahlungen erledigt</p>
            <p class="mt-2 text-2xl font-semibold">{{ $trip->payment_completion_percentage }}%</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Offene Tasks</p>
            <p class="mt-2 text-2xl font-semibold">{{ $trip->open_tasks_count }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Naechste Deadline</p>
            <p class="mt-2 text-2xl font-semibold">{{ $trip->next_due_date?->format('d.m.') ?? '-' }}</p>
        </div>
    </section>

    @if ($trip->notes)
        <section class="mb-8 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Notizen</h2>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $trip->notes }}</p>
        </section>
    @endif

    <section id="bookings" class="mb-8 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold">Buchungen</h2>
            <a href="{{ route('trips.bookings.create', $trip) }}" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Neue Buchung</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Titel</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Zahlung</th>
                        <th class="px-5 py-3">Betrag</th>
                        <th class="px-5 py-3">Deadline</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($trip->bookings as $booking)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-950">{{ $booking->title }}</div>
                                <div class="text-slate-500">{{ $booking->provider ?: $booking->category_label }}</div>
                            </td>
                            <td class="px-5 py-4">{{ $booking->booking_status_label }}</td>
                            <td class="px-5 py-4">{{ $booking->payment_status_label }}</td>
                            <td class="px-5 py-4">{{ number_format((float) $booking->amount, 2) }} {{ $booking->currency }}</td>
                            <td class="px-5 py-4">{{ $booking->due_date?->format('d.m.Y') ?? '-' }}</td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('bookings.edit', $booking) }}" class="font-medium text-slate-700 hover:text-slate-950">Bearbeiten</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">Keine Buchungen vorhanden.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="payments" class="mb-8 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Zahlungen</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
                <p class="text-sm text-slate-500">Total</p>
                <p class="mt-1 font-semibold">@forelse ($totalsByCurrency as $currency => $amount){{ number_format($amount, 2) }} {{ $currency }}@if (! $loop->last), @endif @empty 0.00 @endforelse</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Bezahlt</p>
                <p class="mt-1 font-semibold">@forelse ($paidByCurrency as $currency => $amount){{ number_format($amount, 2) }} {{ $currency }}@if (! $loop->last), @endif @empty 0.00 @endforelse</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Offen</p>
                <p class="mt-1 font-semibold">@forelse ($openByCurrency as $currency => $amount){{ number_format($amount, 2) }} {{ $currency }}@if (! $loop->last), @endif @empty 0.00 @endforelse</p>
            </div>
        </div>
    </section>

    <section id="tasks" class="mb-8 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold">Aufgaben</h2>
            <a href="{{ route('trips.tasks.create', $trip) }}" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Neue Aufgabe</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($trip->tasks as $task)
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium text-slate-950">{{ $task->title }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $task->priority_label }} - {{ $task->status_label }} - {{ $task->due_date?->format('d.m.Y') ?? 'ohne Deadline' }}</p>
                    </div>
                    <a href="{{ route('tasks.edit', $task) }}" class="text-sm font-medium text-slate-700 hover:text-slate-950">Bearbeiten</a>
                </div>
            @empty
                <p class="p-5 text-sm text-slate-500">Keine Aufgaben vorhanden.</p>
            @endforelse
        </div>
    </section>

    <section id="documents" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold">Dokumente</h2>
            <a href="{{ route('trips.documents.create', $trip) }}" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Dokument hochladen</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($trip->documents as $document)
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <a href="{{ asset('storage/'.$document->file_path) }}" target="_blank" class="font-medium text-slate-950 hover:underline">{{ $document->title }}</a>
                        <p class="mt-1 text-sm text-slate-500">{{ $document->document_type_label }}@if ($document->booking) - {{ $document->booking->title }} @endif</p>
                    </div>
                    <a href="{{ route('documents.edit', $document) }}" class="text-sm font-medium text-slate-700 hover:text-slate-950">Bearbeiten</a>
                </div>
            @empty
                <p class="p-5 text-sm text-slate-500">Keine Dokumente vorhanden.</p>
            @endforelse
        </div>
    </section>
@endsection

@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Uebersicht</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Deine Reisen</h1>
        </div>
        <a href="{{ route('trips.create') }}" class="inline-flex items-center justify-center rounded-md bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Reise erstellen</a>
    </div>

    @if ($trips->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
            <h2 class="text-lg font-semibold">Noch keine Reisen</h2>
            <p class="mt-2 text-sm text-slate-600">Erstelle deine erste Reise und sammle Buchungen, Tasks und Dokumente an einem Ort.</p>
        </div>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($trips as $trip)
                @php
                    $lightClass = [
                        'green' => 'bg-emerald-500',
                        'yellow' => 'bg-amber-400',
                        'red' => 'bg-red-500',
                    ][$trip->traffic_light];
                    $paidByCurrency = $trip->bookings->where('payment_status', 'paid')->groupBy('currency')->map(fn ($items) => $items->sum('amount'));
                    $openByCurrency = $trip->bookings->whereIn('payment_status', ['unpaid', 'partially_paid'])->groupBy('currency')->map(fn ($items) => $items->sum('amount'));
                    $cardClass = $trip->is_past
                        ? 'border-slate-200 bg-slate-100 opacity-70 hover:opacity-85'
                        : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:shadow-md';
                @endphp
                <a href="{{ route('trips.show', $trip) }}" class="rounded-lg border p-5 shadow-sm transition {{ $cardClass }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">{{ $trip->title }}</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $trip->start_date?->format('d.m.Y') ?? 'ohne Start' }}
                                @if ($trip->end_date)
                                    - {{ $trip->end_date->format('d.m.Y') }}
                                @endif
                            </p>
                        </div>
                        <span class="mt-1 h-3 w-3 shrink-0 rounded-full {{ $lightClass }}"></span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $trip->type_label }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $trip->status_label }}</span>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-slate-500">Buchungen</dt>
                            <dd class="font-semibold">{{ $trip->bookings->count() }} / {{ $trip->bookings->where('booking_status', 'open')->count() }} offen</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Tasks offen</dt>
                            <dd class="font-semibold">{{ $trip->open_tasks_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Bis zum Start</dt>
                            <dd class="font-semibold">{{ $trip->starts_in_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Bezahlt</dt>
                            <dd class="font-semibold">
                                @forelse ($paidByCurrency as $currency => $amount)
                                    {{ number_format($amount, 2) }} {{ $currency }}@if (! $loop->last), @endif
                                @empty
                                    0.00
                                @endforelse
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Offen</dt>
                            <dd class="font-semibold">
                                @forelse ($openByCurrency as $currency => $amount)
                                    {{ number_format($amount, 2) }} {{ $currency }}@if (! $loop->last), @endif
                                @empty
                                    0.00
                                @endforelse
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-600">
                        Naechste Deadline:
                        <span class="font-medium text-slate-900">{{ $trip->next_due_date?->format('d.m.Y') ?? 'keine' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection

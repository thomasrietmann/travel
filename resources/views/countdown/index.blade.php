@extends(($public ?? false) ? 'layouts.public' : 'layouts.app')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-slate-500">Countdown</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Kommende Reisen & Geburtstage</h1>
        </div>
        @unless ($public ?? false)
            <a href="{{ route('trips.create') }}" class="inline-flex items-center justify-center rounded-md bg-slate-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800">Reise erstellen</a>
        @endunless
    </div>

    @if ($countdownItems->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
            <h2 class="text-lg font-semibold">Keine kommenden Countdowns</h2>
            <p class="mt-2 text-sm text-slate-600">Alle Reisen sind gestartet oder es sind noch keine Geburtstage erfasst.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($countdownItems as $item)
                @if ($item['type'] === 'birthday')
                    @php($birthday = $item['birthday'])
                    <div class="block rounded-lg border border-pink-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-pink-50 text-2xl" aria-hidden="true">🎉</div>
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-950">{{ $birthday->name }}</h2>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Geburtstag am {{ $item['date']->format('d.m.Y') }}
                                        <span class="text-slate-400">-</span>
                                        wird {{ $birthday->age_on_next_birthday }}
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-lg bg-pink-600 px-5 py-4 text-center text-white">
                                <p class="text-xs font-medium uppercase tracking-wide text-pink-100">Geburtstag in</p>
                                <p class="mt-1 text-2xl font-semibold">{{ $birthday->next_birthday_label }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    @php($trip = $item['trip'])
                    <div class="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                @if ($public ?? false)
                                    <h2 class="text-lg font-semibold text-slate-950">{{ $trip->title }}</h2>
                                @else
                                    <a href="{{ route('trips.show', $trip) }}" class="text-lg font-semibold text-slate-950 hover:underline">{{ $trip->title }}</a>
                                @endif
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ $trip->destination ?: 'Keine Destination' }} -
                                    {{ $trip->start_date?->format('d.m.Y') }}
                                    @if ($trip->end_date)
                                        - {{ $trip->end_date->format('d.m.Y') }}
                                    @endif
                                </p>
                                @unless ($public ?? false)
                                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $trip->type_label }}</span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $trip->status_label }}</span>
                                    </div>
                                @endunless
                            </div>

                            <div class="rounded-lg bg-slate-950 px-5 py-4 text-center text-white">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-300">Start in</p>
                                <p class="mt-1 text-2xl font-semibold">{{ $trip->starts_in_label }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
@endsection

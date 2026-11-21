<section id="tagesplan" aria-labelledby="plan-heading" class="mb-8 scroll-mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-3 px-4 pb-4 pt-5 sm:px-6">
        <h2 id="plan-heading" class="text-xl font-semibold tracking-tight">Tagesplan</h2>
        <a href="{{ route('trips.stops.create', $trip) }}" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Stopp hinzufügen</a>
    </header>

    @if ($plan['hasFlights'] || $flightsOnly)
        <nav aria-label="Tagesplan filtern" class="flex gap-1 px-4 pb-4 text-sm sm:px-6">
            @foreach (['alle' => 'Alle Einträge', 'fluege' => 'Nur Flüge'] as $filter => $label)
                @php
                    $active = ($filter === 'fluege') === $flightsOnly;
                @endphp
                <a href="{{ route('trips.show', [$trip, 'plan' => $filter === 'fluege' ? $filter : null]) }}#tagesplan"
                   @if ($active) aria-current="page" @endif
                   @class(['rounded-md px-3 py-1.5 font-medium', 'bg-slate-100 text-slate-950' => $active, 'text-slate-600 hover:bg-slate-50' => ! $active])>{{ $label }}</a>
            @endforeach
        </nav>
    @endif

    @foreach ($plan['days'] as $date => $events)
        @php
            $day = \Illuminate\Support\Carbon::parse($date)->locale('de');
            $hasTimedEntries = $events->contains(fn ($entry) => $entry['time'] !== null);
        @endphp
        <details class="itinerary-day border-t border-slate-200" @if ($flightsOnly || $openDay === $date) open @endif>
            <summary class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-slate-50 sm:px-6">
                <span class="min-w-0 flex-1">
                    <time datetime="{{ $date }}" class="block font-semibold text-slate-900">{{ $day->isoFormat('dddd, D. MMMM YYYY') }}</time>
                    <span class="day-preview mt-0.5 block text-xs text-slate-600">{{ $events->pluck('label')->unique()->implode(' · ') }}</span>
                </span>
                <span class="shrink-0 text-xs text-slate-600">{{ $events->count() }} {{ $events->count() === 1 ? 'Eintrag' : 'Einträge' }}</span>
            </summary>
            <ol class="divide-y divide-slate-100 px-4 pb-2 sm:px-6">
                @foreach ($events as $event)
                    @php
                        $booking = $event['booking'];
                        $stop = $event['stop'];
                    @endphp
                    <li class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 gap-y-1 py-3 text-sm sm:grid-cols-[8rem_minmax(0,1fr)_auto] sm:gap-4">
                        <div class="col-start-1 row-start-1 min-w-0 break-words text-xs leading-5 text-slate-600">
                            <span class="font-semibold text-slate-700">{{ $event['label'] }}</span>
                            @if ($event['time'])
                                <span class="ml-1 sm:ml-0 sm:block"><span class="font-medium tabular-nums">{{ $event['time'] }}</span> {{ $event['zone'] }}</span>
                            @elseif ($booking?->category === 'flight' || $hasTimedEntries)
                                <span class="ml-1 sm:ml-0 sm:block">Uhrzeit offen</span>
                            @endif
                        </div>
                        <div class="col-span-2 row-start-2 min-w-0 break-words sm:col-span-1 sm:col-start-2 sm:row-start-1">
                            <p class="font-semibold text-slate-950">
                                @if ($booking)
                                    <a href="{{ route('bookings.edit', $booking) }}" class="hover:underline">{{ $event['title'] }}</a>
                                @else
                                    {{ $event['title'] }}
                                @endif
                            </p>
                            @if ($event['location']) <p class="mt-0.5 text-slate-700">{{ $event['location'] }}</p> @endif
                            @if ($event['endClock'])
                                <p class="text-xs text-slate-600">{{ $booking?->category === 'flight' ? 'Ankunft' : 'Ende' }} {{ $event['endClock'] }} {{ $event['endZone'] }}</p>
                            @endif
                            @if ($booking)
                                @include('itinerary.booking-meta')
                                @foreach ($booking->documents as $document)
                                    <a href="{{ route('documents.download', $document) }}" class="mr-3 inline-block py-1 text-xs font-medium text-slate-700 underline">{{ $document->title }}</a>
                                @endforeach
                            @endif
                        </div>
                        <div class="col-start-2 row-start-1 flex flex-wrap items-center justify-end gap-x-2 text-xs sm:col-start-3 sm:flex-col sm:items-end sm:text-sm">
                            @if ($stop)
                                <a href="{{ route('trips.stops.edit', [$trip, $stop]) }}" class="py-1 font-medium text-slate-700 hover:underline">Bearbeiten</a>
                            @endif
                            @if ($booking && (! $stop || $booking->category === 'flight'))
                                <a href="{{ route('trips.stops.create', [$trip, 'booking' => $booking->id]) }}" class="py-1 font-medium text-slate-700 hover:underline">{{ $stop ? 'Weitere Teilstrecke' : 'Zeit und Ort ergänzen' }}</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </details>
    @endforeach

    @if ($plan['days']->isEmpty())
        <p class="border-t border-slate-200 px-4 py-5 text-sm text-slate-600 sm:px-6">{{ $flightsOnly ? 'Noch keine Flüge mit festem Reisetag.' : 'Noch keine Einträge mit festem Reisetag.' }}</p>
    @endif

    @if ($plan['unplaced'])
        <section class="border-t border-slate-200 bg-slate-50 px-4 py-4 sm:px-6">
            <h3 class="mb-2 font-semibold">Ohne Tageszuordnung</h3>
            <ul class="divide-y divide-slate-200">
                @foreach ($plan['unplaced'] as $entry)
                    @php
                        $booking = $entry['booking'];
                    @endphp
                    <li class="flex flex-col justify-between gap-1 py-2.5 text-sm sm:flex-row sm:items-center sm:gap-4">
                        <div class="min-w-0 break-words">
                            <a href="{{ route('bookings.edit', $booking) }}" class="font-medium text-slate-950 hover:underline">{{ $booking->title }}</a>
                            <p class="mt-0.5 text-xs text-slate-600">{{ $entry['reason'] }} @if ($booking->start_date || $booking->end_date) · {{ $booking->date_range_label }} @endif</p>
                            @include('itinerary.booking-meta')
                            @if ($booking->category === 'flight')
                                <div class="mt-3">
                                    @include('itinerary.flight-import')
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('trips.stops.create', [$trip, 'booking' => $booking->id]) }}" class="shrink-0 py-1 font-medium text-slate-700 hover:underline">{{ $booking->category === 'flight' ? 'Teilstrecke hinzufügen' : 'Tag zuweisen' }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</section>

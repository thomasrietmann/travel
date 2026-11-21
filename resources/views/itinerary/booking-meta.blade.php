@if ($booking->provider || $booking->booking_reference || $booking->booking_status !== 'confirmed')
    <p class="mt-1 flex flex-wrap gap-x-2 text-xs text-slate-600">
        @if ($booking->provider) <span>{{ $booking->provider }}</span> @endif
        @if ($booking->booking_reference) <span>Ref. {{ $booking->booking_reference }}</span> @endif
        @if ($booking->category === 'flight' && $plan['flightReferences']->get($booking->booking_reference, 0) > 1)
            <span class="text-amber-800">{{ $plan['flightReferences'][$booking->booking_reference] }} Buchungen mit dieser Referenz</span>
        @endif
        @if ($booking->booking_status !== 'confirmed') <span>{{ $booking->booking_status_label }}</span> @endif
    </p>
@endif

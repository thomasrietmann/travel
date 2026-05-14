@php
    use App\Models\Booking;
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="category" class="block text-sm font-medium text-slate-700">Kategorie</label>
        <select id="category" name="category" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Booking::CATEGORIES as $category)
                <option value="{{ $category }}" @selected(old('category', $booking->category) === $category)>{{ $category }}</option>
            @endforeach
        </select>
        @error('category') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="title" class="block text-sm font-medium text-slate-700">Titel</label>
        <input id="title" name="title" value="{{ old('title', $booking->title) }}" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="provider" class="block text-sm font-medium text-slate-700">Provider</label>
        <input id="provider" name="provider" value="{{ old('provider', $booking->provider) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('provider') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="booking_reference" class="block text-sm font-medium text-slate-700">Buchungsreferenz</label>
        <input id="booking_reference" name="booking_reference" value="{{ old('booking_reference', $booking->booking_reference) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('booking_reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="amount" class="block text-sm font-medium text-slate-700">Betrag</label>
        <input id="amount" name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $booking->amount ?? 0) }}" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="currency" class="block text-sm font-medium text-slate-700">Waehrung</label>
        <select id="currency" name="currency" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Booking::CURRENCIES as $currency)
                <option value="{{ $currency }}" @selected(old('currency', $booking->currency ?? 'CHF') === $currency)>{{ $currency }}</option>
            @endforeach
        </select>
        @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="booking_status" class="block text-sm font-medium text-slate-700">Buchungsstatus</label>
        <select id="booking_status" name="booking_status" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Booking::BOOKING_STATUSES as $status)
                <option value="{{ $status }}" @selected(old('booking_status', $booking->booking_status) === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('booking_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="payment_status" class="block text-sm font-medium text-slate-700">Zahlungsstatus</label>
        <select id="payment_status" name="payment_status" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
            @foreach (Booking::PAYMENT_STATUSES as $status)
                <option value="{{ $status }}" @selected(old('payment_status', $booking->payment_status) === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('payment_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="due_date" class="block text-sm font-medium text-slate-700">Faellig am</label>
        <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $booking->due_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="cancellation_deadline" class="block text-sm font-medium text-slate-700">Stornofrist</label>
        <input id="cancellation_deadline" name="cancellation_deadline" type="date" value="{{ old('cancellation_deadline', $booking->cancellation_deadline?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
        @error('cancellation_deadline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="block text-sm font-medium text-slate-700">Notizen</label>
        <textarea id="notes" name="notes" rows="4" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('notes', $booking->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

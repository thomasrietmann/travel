@php
    use App\Models\Booking;
    use App\Models\Document;
@endphp

<div class="space-y-6">
    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-950">Buchung</h2>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="category" class="block text-sm font-medium text-slate-700">Kategorie</label>
                <select id="category" name="category" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @foreach (Booking::CATEGORIES as $category)
                        <option value="{{ $category }}" @selected(old('category', $booking->category) === $category)>{{ Booking::CATEGORY_LABELS[$category] }}</option>
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
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-950">Zeitraum</h2>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="start_date" class="block text-sm font-medium text-slate-700">Von</label>
                <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $booking->start_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="end_date" class="block text-sm font-medium text-slate-700">Bis</label>
                <input id="end_date" name="end_date" type="date" value="{{ old('end_date', $booking->end_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-950">Status und Zahlung</h2>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="booking_status" class="block text-sm font-medium text-slate-700">Buchungsstatus</label>
                <select id="booking_status" name="booking_status" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @foreach (Booking::BOOKING_STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('booking_status', $booking->booking_status) === $status)>{{ Booking::BOOKING_STATUS_LABELS[$status] }}</option>
                    @endforeach
                </select>
                @error('booking_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="payment_status" class="block text-sm font-medium text-slate-700">Zahlungsstatus</label>
                <select id="payment_status" name="payment_status" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @foreach (Booking::PAYMENT_STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('payment_status', $booking->payment_status) === $status)>{{ Booking::PAYMENT_STATUS_LABELS[$status] }}</option>
                    @endforeach
                </select>
                @error('payment_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="amount" class="block text-sm font-medium text-slate-700">Betrag</label>
                <input id="amount" name="amount" type="number" step="0.01" min="0" value="{{ old('amount', $booking->amount ?? 0) }}" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="currency" class="block text-sm font-medium text-slate-700">Währung</label>
                <select id="currency" name="currency" required class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @foreach (Booking::CURRENCIES as $currency)
                        <option value="{{ $currency }}" @selected(old('currency', $booking->currency ?? 'CHF') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
                @error('currency') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-950">Fristen</h2>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="due_date" class="block text-sm font-medium text-slate-700">Fällig am</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $booking->due_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cancellation_deadline" class="block text-sm font-medium text-slate-700">Stornofrist</label>
                <input id="cancellation_deadline" name="cancellation_deadline" type="date" value="{{ old('cancellation_deadline', $booking->cancellation_deadline?->format('Y-m-d')) }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                @error('cancellation_deadline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-950">Notizen</h2>
        </div>

        <label for="notes" class="sr-only">Notizen</label>
        <textarea id="notes" name="notes" rows="4" class="w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('notes', $booking->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-slate-950">Dokument hinzufügen</h2>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="document_title" class="block text-sm font-medium text-slate-700">Dokumenttitel</label>
                <input id="document_title" name="document_title" value="{{ old('document_title') }}" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                @error('document_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="document_type" class="block text-sm font-medium text-slate-700">Dokumenttyp</label>
                <select id="document_type" name="document_type" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">
                    @foreach (Document::TYPES as $type)
                        <option value="{{ $type }}" @selected(old('document_type', 'confirmation') === $type)>{{ Document::TYPE_LABELS[$type] }}</option>
                    @endforeach
                </select>
                @error('document_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label for="document_file" class="block text-sm font-medium text-slate-700">Datei</label>
                <input id="document_file" name="document_file" type="file" class="mt-1 block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800">
                @error('document_file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label for="document_notes" class="block text-sm font-medium text-slate-700">Dokumentnotizen</label>
                <textarea id="document_notes" name="document_notes" rows="3" class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-900 focus:ring-slate-900">{{ old('document_notes') }}</textarea>
                @error('document_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($booking->exists && $booking->documents->isNotEmpty())
            <div class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-sm font-semibold text-slate-950">Vorhandene Dokumente</h3>
                <div class="mt-3 divide-y divide-slate-100 rounded-md border border-slate-200">
                    @foreach ($booking->documents as $document)
                        <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                            <a href="{{ route('documents.download', $document) }}" class="font-medium text-slate-700 hover:text-slate-950 hover:underline">{{ $document->title }}</a>
                            <span class="text-slate-500">{{ $document->document_type_label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');

        startDate?.addEventListener('change', () => {
            endDate.value = startDate.value;
        });
    });
</script>

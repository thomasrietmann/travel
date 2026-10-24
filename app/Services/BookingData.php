<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Validation\Rule;

class BookingData
{
    public static function rules(): array
    {
        return [
            'category' => ['required', Rule::in(Booking::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'booking_reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(Booking::CURRENCIES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'booking_status' => ['required', Rule::in(Booking::BOOKING_STATUSES)],
            'payment_status' => ['required', Rule::in(Booking::PAYMENT_STATUSES)],
            'due_date' => ['nullable', 'date'],
            'cancellation_deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public static function normalize(array $data, Trip $trip): array
    {
        $startDate = self::date($data['start_date'] ?? '') ?: $trip->start_date?->toDateString();

        return [
            'category' => in_array($data['category'] ?? '', Booking::CATEGORIES, true) ? $data['category'] : 'other',
            'title' => self::clean($data['title'] ?? '') ?: 'Importierte Buchung',
            'provider' => self::clean($data['provider'] ?? '') ?: null,
            'booking_reference' => self::clean($data['booking_reference'] ?? '') ?: null,
            'amount' => max(0, (float) ($data['amount'] ?? 0)),
            'currency' => in_array($data['currency'] ?? '', Booking::CURRENCIES, true) ? $data['currency'] : 'CHF',
            'start_date' => $startDate,
            'end_date' => self::date($data['end_date'] ?? '') ?: $startDate,
            'booking_status' => in_array($data['booking_status'] ?? '', Booking::BOOKING_STATUSES, true) ? $data['booking_status'] : 'confirmed',
            'payment_status' => in_array($data['payment_status'] ?? '', Booking::PAYMENT_STATUSES, true) ? $data['payment_status'] : 'unpaid',
            'due_date' => self::date($data['due_date'] ?? ''),
            'cancellation_deadline' => self::date($data['cancellation_deadline'] ?? ''),
            'notes' => self::clean($data['notes'] ?? '') ?: null,
        ];
    }

    public static function clean(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    public static function date(mixed $value): ?string
    {
        $value = self::clean($value);

        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }
}

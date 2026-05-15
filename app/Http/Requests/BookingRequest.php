<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
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
}

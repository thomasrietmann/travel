<?php

namespace App\Http\Requests;

use App\Services\ItineraryStopData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItineraryStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function rules(): array
    {
        return [
            'booking_id' => $this->isMethod('post')
                ? ['bail', 'nullable', 'integer', Rule::exists('bookings', 'id')->where('trip_id', $this->route('trip')->id)]
                : ['prohibited'],
            ...ItineraryStopData::rules(),
        ];
    }

    public function after(): array
    {
        return [ItineraryStopData::validateEnd(...)];
    }

    public function messages(): array
    {
        return [
            'booking_id.exists' => 'Wähle eine Buchung dieser Reise.',
            'title.required' => 'Gib eine Bezeichnung ein.',
            'start_date.required' => 'Wähle einen Reisetag.',
            '*.date_format' => 'Prüfe das eingegebene Datum oder die Uhrzeit.',
            '*.timezone' => 'Wähle eine gültige Zeitzone, z. B. Europe/Zurich.',
            'end_date.required_with' => 'Gib auch das Ortsdatum am Ziel ein.',
        ];
    }
}

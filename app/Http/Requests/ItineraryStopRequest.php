<?php

namespace App\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'start_location' => ['nullable', 'string', 'max:255'],
            'start_timezone' => ['nullable', 'timezone'],
            'end_date' => ['nullable', 'required_with:end_time,end_timezone', 'date_format:Y-m-d'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'end_location' => ['nullable', 'string', 'max:255'],
            'end_timezone' => ['nullable', 'timezone'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('end_date')) {
                return;
            }
            if ($this->filled(['start_time', 'end_time', 'start_timezone', 'end_timezone'])) {
                $start = new DateTimeImmutable($this->input('start_date').' '.$this->input('start_time'), new DateTimeZone($this->input('start_timezone')));
                $end = new DateTimeImmutable($this->input('end_date').' '.$this->input('end_time'), new DateTimeZone($this->input('end_timezone')));
                if ($end < $start) {
                    $validator->errors()->add('end_time', 'Das Ende liegt mit diesen Ortszeiten vor dem Start.');
                }
            } elseif ($this->input('end_date') < $this->input('start_date')) {
                $validator->errors()->add('end_date', 'Für ein früheres Ortsdatum am Ziel beide Uhrzeiten und Zeitzonen angeben.');
            } elseif ($this->input('end_date') === $this->input('start_date') && $this->filled(['start_time', 'end_time']) && $this->input('end_time') < $this->input('start_time')) {
                $validator->errors()->add('end_time', 'Prüfe die Uhrzeiten. Liegt das Ziel in einer anderen Zeitzone, gib beide Zeitzonen an.');
            }
        }];
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

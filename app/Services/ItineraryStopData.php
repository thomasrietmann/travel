<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\Validator;

class ItineraryStopData
{
    public static function rules(): array
    {
        return [
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

    public static function validateEnd(Validator $validator): void
    {
        $data = $validator->getData();
        if ($validator->errors()->isNotEmpty() || blank($data['end_date'] ?? null)) {
            return;
        }
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;
        $startZone = $data['start_timezone'] ?? null;
        $endZone = $data['end_timezone'] ?? null;
        if (filled($startTime) && filled($endTime) && filled($startZone) && filled($endZone)) {
            $start = new DateTimeImmutable($data['start_date'].' '.$startTime, new DateTimeZone($startZone));
            $end = new DateTimeImmutable($data['end_date'].' '.$endTime, new DateTimeZone($endZone));
            if ($end < $start) {
                $validator->errors()->add('end_time', 'Das Ende liegt mit diesen Ortszeiten vor dem Start.');
            }
        } elseif ($data['end_date'] < $data['start_date']) {
            $validator->errors()->add('end_date', 'Für ein früheres Ortsdatum am Ziel beide Uhrzeiten und Zeitzonen angeben.');
        } elseif ($data['end_date'] === $data['start_date'] && filled($startTime) && filled($endTime) && $endTime < $startTime) {
            $validator->errors()->add('end_time', 'Prüfe die Uhrzeiten. Liegt das Ziel in einer anderen Zeitzone, gib beide Zeitzonen an.');
        }
    }
}

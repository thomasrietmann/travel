<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_documents' => ['required', 'array', 'min:1', 'max:10'],
            'booking_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
        ];
    }

    public function messages(): array
    {
        return [
            'booking_documents.required' => 'Bitte mindestens eine Datei auswählen.',
            'booking_documents.array' => 'Bitte eine oder mehrere Dateien auswählen.',
            'booking_documents.max' => 'Es koennen maximal 10 Dateien gleichzeitig importiert werden.',
            'booking_documents.*.mimes' => 'Erlaubt sind PDF, JPG, PNG und WebP.',
            'booking_documents.*.max' => 'Eine Datei darf maximal 15 MB gross sein.',
        ];
    }
}

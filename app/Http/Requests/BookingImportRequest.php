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
            'booking_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
        ];
    }
}

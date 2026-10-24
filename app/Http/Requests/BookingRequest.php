<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Services\BookingData;
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
            ...BookingData::rules(),
            'document_title' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', Rule::in(Document::TYPES)],
            'document_file' => ['nullable', 'file', 'mimes:'.DocumentRequest::ALLOWED_FILE_TYPES, 'max:10240'],
            'document_notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_file.mimes' => 'Erlaubt sind PDF, JPG, PNG und WebP.',
        ];
    }
}

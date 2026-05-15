<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
                'title' => ['nullable', 'string', 'max:255'],
                'files' => ['required', 'array', 'min:1', 'max:10'],
                'files.*' => ['file', 'max:10240'],
                'document_type' => ['required', Rule::in(Document::TYPES)],
                'notes' => ['nullable', 'string'],
            ];
        }

        return [
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:10240'],
            'document_type' => ['required', Rule::in(Document::TYPES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}

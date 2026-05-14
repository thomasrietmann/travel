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
        $fileRule = $this->isMethod('post') ? 'required' : 'nullable';

        return [
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'title' => ['required', 'string', 'max:255'],
            'file' => [$fileRule, 'file', 'max:10240'],
            'document_type' => ['required', Rule::in(Document::TYPES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}

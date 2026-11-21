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

    protected function prepareForValidation(): void
    {
        if ($this->input('payment_status') !== 'partially_paid') {
            $this->merge(['deposit_amount' => null]);
        }
    }

    public function rules(): array
    {
        return [
            ...BookingData::rules(),
            'amount' => ['required', 'decimal:0,2', 'between:0,99999999.99'],
            'deposit_amount' => ['bail', 'nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'lt:amount'],
            'document_title' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', Rule::in(Document::TYPES)],
            'document_file' => ['nullable', 'file', 'mimes:'.DocumentRequest::ALLOWED_FILE_TYPES, 'max:10240'],
            'document_notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'deposit_amount.numeric' => 'Gib die Anzahlung als Zahl ein.',
            'deposit_amount.decimal' => 'Die Anzahlung darf höchstens zwei Nachkommastellen haben.',
            'deposit_amount.min' => 'Die Anzahlung muss grösser als null sein.',
            'deposit_amount.lt' => 'Die Anzahlung muss kleiner als der Buchungsbetrag sein.',
            'document_file.mimes' => 'Erlaubt sind PDF, JPG, PNG und WebP.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserEmailAliasRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower((string) $this->input('email')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('user_email_aliases', 'email'),
                Rule::notIn([$this->user()?->email]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.not_in' => 'Die Login-E-Mail ist bereits automatisch hinterlegt.',
            'email.unique' => 'Diese E-Mail-Adresse ist bereits in TripControl hinterlegt.',
        ];
    }
}

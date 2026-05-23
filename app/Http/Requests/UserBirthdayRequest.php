<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserBirthdayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'birth_date' => 'Geburtstag',
        ];
    }
}

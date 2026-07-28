<?php

namespace App\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254', 'exists:users,email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'max:72',
            ],
            'token' => ['required', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

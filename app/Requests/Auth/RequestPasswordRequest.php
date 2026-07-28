<?php

namespace App\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RequestPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'string', 'max:254'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ApiKeyLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CallerLabelRequest extends FormRequest
{
    /** The dashboard's two guards are the authorization, and both already ran. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            /*
             | Either 64 hex characters or the keyless sentinel. Narrow on
             | purpose: this value is written to a unique column and handed
             | straight back as a filter, and nothing else can ever be a caller.
             */
            'api_key_hash' => [
                'required',
                'string',
                'regex:/^([0-9a-f]{64}|'.ApiKeyLabel::ANONYMOUS.')$/',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('api_key_labels', 'name')->ignore($this->existing()?->id),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'api_key_hash.regex' => 'A caller is a 64-character sha256 hash, or “anonymous” for callers that send no key.',
            'name.unique' => 'Another caller already goes by that name.',
        ];
    }

    /** The label already on this hash, so renaming it is not a duplicate of itself. */
    public function existing(): ?ApiKeyLabel
    {
        $hash = $this->string('api_key_hash')->value();

        return ApiKeyLabel::query()->where('api_key_hash', $hash)->first();
    }
}

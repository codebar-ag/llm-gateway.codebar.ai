<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GatewayProtocol;
use App\Models\Gateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

/**
 * The only validated input in the application. The /v1 API deliberately does
 * not inspect what it is handed; this is an operator writing a routing table,
 * which is a different thing entirely.
 */
class GatewayRequest extends FormRequest
{
    /** The dashboard token is the authorization, and the middleware already checked it. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('gateways', 'name')->ignore($this->gateway())],
            'protocol' => ['required', Rule::enum(GatewayProtocol::class)],
            'base_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'path' => ['nullable', 'string', 'max:255', 'starts_with:/'],
            'default_model' => ['nullable', 'string', 'max:255'],
            /*
             | Capped rather than free: the queue's retry_after is one value for
             | the whole connection, and it is derived from this ceiling. A
             | gateway allowed past it could have a call still running when the
             | queue decides the job died and hands it to a second worker.
             */
            'timeout' => ['required', 'integer', 'min:1', 'max:'.Config::integer('gateway.upstream.max_timeout')],
            'connect_timeout' => ['required', 'integer', 'min:1', 'max:120'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'timeout.max' => 'A gateway cannot be slower than GATEWAY_MAX_TIMEOUT ('
                .Config::integer('gateway.upstream.max_timeout').'s). Raise the ceiling first.',
            'path.starts_with' => 'The path must start with a slash, for example /v1/chat/completions.',
        ];
    }

    /**
     * The validated input as the model wants it: typed, with blank optional
     * fields as null rather than empty strings so `path` falling back to the
     * protocol default stays a single check.
     *
     * @return array<string, mixed>
     */
    public function validatedAttributes(): array
    {
        return [
            'name' => $this->string('name')->value(),
            'protocol' => $this->string('protocol')->value(),
            'base_url' => $this->string('base_url')->value(),
            'path' => $this->optionalString('path'),
            'default_model' => $this->optionalString('default_model'),
            'timeout' => $this->integer('timeout'),
            'connect_timeout' => $this->integer('connect_timeout'),
            'description' => $this->optionalString('description'),
            /*
             | Only when it was actually sent. The form always submits this
             | alongside a hidden 0, but `is_active` is the one field here with
             | no `required` rule, and reading an absent checkbox as false would
             | make a script's partial PUT silently stop the gateway taking work.
             */
            ...($this->has('is_active') ? ['is_active' => $this->boolean('is_active')] : []),
        ];
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->value() : null;
    }

    private function gateway(): ?Gateway
    {
        $gateway = $this->route('gateway');

        return $gateway instanceof Gateway ? $gateway : null;
    }
}

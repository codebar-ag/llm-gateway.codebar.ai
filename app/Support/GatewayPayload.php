<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\GatewayProtocol;
use App\Models\Gateway;

/**
 * How a gateway is handed to the frontend.
 *
 * The create and edit pages render one component from one shape, so the shape
 * is stated here rather than twice in two controllers that must not drift.
 */
final class GatewayPayload
{
    /** The number of leading id characters the list shortens a caller URL to. */
    private const SHORT_ID = 8;

    /**
     * Both callers hand this a gateway whose protocol is set — the edit page
     * from the database, the create page from the enum's first case — so the
     * protocol is read directly rather than defended against.
     *
     * @return array<string, mixed>
     */
    public static function form(Gateway $gateway): array
    {
        return [
            'name' => $gateway->name ?? '',
            'protocol' => $gateway->protocol->value,
            'baseUrl' => $gateway->base_url ?? '',
            'path' => $gateway->path,
            'defaultPath' => $gateway->protocol->defaultPath(),
            'defaultModel' => $gateway->default_model,
            'timeout' => $gateway->timeout,
            'connectTimeout' => $gateway->connect_timeout,
            'description' => $gateway->description,
            'isActive' => (bool) ($gateway->is_active ?? true),
            'isDefault' => (bool) ($gateway->is_default ?? false),
            'callerBaseUrl' => $gateway->exists ? $gateway->callerBaseUrl() : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function row(Gateway $gateway): array
    {
        return [
            'id' => $gateway->id,
            'shortId' => substr($gateway->id, 0, self::SHORT_ID),
            'name' => $gateway->name,
            'isDefault' => $gateway->is_default,
            'isActive' => $gateway->is_active,
            'protocol' => [
                'label' => $gateway->protocol->label(),
                'tone' => $gateway->protocol->tone(),
            ],
            'defaultModel' => $gateway->default_model,
            'endpoint' => $gateway->endpoint(),
            'callerBaseUrl' => $gateway->callerBaseUrl(),
            'entries' => $gateway->entries_count,
            'entriesUrl' => route('backlog', ['gateway' => $gateway->id]),
            'editUrl' => route('gateways.edit', $gateway),
            'defaultUrl' => route('gateways.default', $gateway),
            'destroyUrl' => route('gateways.destroy', $gateway),
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function protocols(): array
    {
        return array_map(
            fn (GatewayProtocol $protocol): array => [
                'value' => $protocol->value,
                'label' => $protocol->label(),
            ],
            GatewayProtocol::cases(),
        );
    }
}

<?php

declare(strict_types=1);

use App\Models\ApiKeyLabel;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

it('generates a uuid key', function () {
    $label = ApiKeyLabel::factory()->create();

    expect($label->id)->toHaveLength(36)
        ->and($label->getIncrementing())->toBeFalse()
        ->and($label->fresh()->created_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('names the hash a caller queues under', function () {
    $label = ApiKeyLabel::factory()->forKey('sk-nightly')->create(['name' => 'Nightly batch']);

    expect($label->api_key_hash)->toBe(hash('sha256', 'sk-nightly'))
        ->and($label->name)->toBe('Nightly batch');
});

/*
 | The keyless bucket is a sentinel string, not NULL: a unique index treats
 | NULLs as distinct on both drivers, so a nullable column would accept two
 | labels for the same caller. No sha256 digest can collide with it.
 */

it('labels the keyless caller under a sentinel hash', function () {
    expect(ApiKeyLabel::factory()->anonymous()->create()->api_key_hash)->toBe('anonymous')
        ->and(ApiKeyLabel::ANONYMOUS)->toBe('anonymous')
        ->and(strlen(ApiKeyLabel::ANONYMOUS))->toBeLessThan(64);
});

it('refuses a second label for the same hash', function () {
    ApiKeyLabel::factory()->forKey('sk-one')->create();

    expect(fn () => ApiKeyLabel::factory()->forKey('sk-one')->create())->toThrow(QueryException::class);
});

it('refuses a second label for the keyless caller', function () {
    ApiKeyLabel::factory()->anonymous()->create();

    expect(fn () => ApiKeyLabel::factory()->anonymous()->create())->toThrow(QueryException::class);
});

it('refuses two callers sharing a name', function () {
    ApiKeyLabel::factory()->create(['name' => 'Nightly batch']);

    expect(fn () => ApiKeyLabel::factory()->create(['name' => 'Nightly batch']))->toThrow(QueryException::class);
});

<?php

use App\Enums\LocaleEnum;
use App\Enums\SessionKeyEnum;
use App\Http\Middleware\SetLanguage;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    Route::middleware(['web', SetLanguage::class])->get('/test', function () {
        return 'Middleware Test';
    });
});

it('sets locale based on authenticated user', function () {
    $user = User::factory()->create([
        'locale' => LocaleEnum::DE,
    ]);

    $this->actingAs($user)
        ->get('/test')
        ->assertOk();

    expect(App::getLocale())->toBe(LocaleEnum::DE->value);
});

it('sets locale based on session when user is not authenticated', function () {
    Session::put(SessionKeyEnum::LANGUAGE->value, LocaleEnum::FR->value);

    $this->get('/test')
        ->assertOk();

    expect(App::getLocale())->toBe(LocaleEnum::FR->value);
});

it('defaults to German locale when no user or session language is set', function () {
    $this->get('/test')
        ->assertOk();

    expect(App::getLocale())->toBe(LocaleEnum::DE->value);
});

<?php

use App\Enums\RoleEnum;
use App\Models\User;

it('renders the login page on the guest layout without dark-mode classes', function () {
    $response = $this->get(route('auth.login'));

    $response->assertOk()
        ->assertSee(__('auth.sign-in'), false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee('dark:', false);
})->group('frontend');

it('renders the forgot-password page without dark-mode classes', function () {
    $this->get(route('auth.request-password'))
        ->assertOk()
        ->assertSee(__('auth.request-password-reset'), false)
        ->assertDontSee('dark:', false);
})->group('frontend');

it('renders the dashboard on the app layout without dark-mode classes', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::USER);

    $this->actingAs($user)
        ->get(route('start.index'))
        ->assertOk()
        ->assertSee(__('dashboard.title'), false)
        ->assertDontSee('dark:', false);
})->group('frontend');

it('renders the error page without dark-mode classes', function () {
    config(['app.debug' => false]);

    $this->get('/nonexistent-route-for-frontend-render-test-2b9a')
        ->assertStatus(404)
        ->assertSee(__('errors.back_home'), false)
        ->assertDontSee('dark:', false);
})->group('frontend');

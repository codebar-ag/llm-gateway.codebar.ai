<?php

use App\Models\PasswordResetToken;
use App\Models\User;

test('getUpdatedAtColumn should return null', function () {
    $user = User::factory()->create();
    $model = PasswordResetToken::create([
        'email' => $user->email,
        'token' => 'token',
    ]);

    expect($model->getUpdatedAtColumn())->toBeNull();
})->group('unit', 'models');

test('setUpdatedAt should not update the updated_at column', function () {
    $user = User::factory()->create();
    $model = PasswordResetToken::create([
        'email' => $user->email,
        'token' => 'token',
    ]);

    $result = $model->setUpdatedAt(now());

    expect($result)->toBe($model);
    expect($model->getAttributes())->not->toHaveKey('updated_at');
})->group('unit', 'models');

<?php

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

it('is queueable verify email notification', function () {
    $notification = new VerifyEmailNotification;

    expect($notification)->toBeInstanceOf(ShouldQueue::class);

    $traits = class_uses_recursive($notification);
    expect($traits)->toContain(Queueable::class);
})->group('unit', 'notifications');

it('builds mail message for user', function () {
    $user = User::factory()->create();

    $notification = new VerifyEmailNotification;
    $mail = $notification->toMail($user);

    expect($mail->subject)->not->toBeEmpty();
})->group('unit', 'notifications');

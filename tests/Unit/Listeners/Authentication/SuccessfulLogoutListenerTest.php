<?php

use App\Listeners\Auth\SuccessfulLogoutListener;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

it('logs activity on successful web logout', function () {
    $user = User::factory()->create();

    $activitiesBefore = Activity::count();
    $request = Request::create('/logout', 'POST');
    app()->instance('request', $request);

    $event = new Logout('web', $user);
    $listener = new SuccessfulLogoutListener;
    $listener->handle($event);

    $logoutActivity = Activity::where('description', 'successful logout')
        ->where('causer_id', $user->id)
        ->where('subject_id', $user->id)
        ->first();

    expect($logoutActivity)->not->toBeNull();
    expect($logoutActivity->description)->toBe('successful logout');
});

it('logs activity on successful api logout', function () {
    $user = User::factory()->create();
    $activitiesBefore = Activity::count();

    $request = Request::create('api/v1/logout', 'POST');
    app()->instance('request', $request);

    $event = new Logout('api', $user);
    $listener = new SuccessfulLogoutListener;
    $listener->handle($event);

    $logoutActivity = Activity::where('description', 'successful logout')
        ->where('causer_id', $user->id)
        ->where('subject_id', $user->id)
        ->first();

    expect($logoutActivity)->not->toBeNull();
    expect($logoutActivity->description)->toBe('successful logout');
});

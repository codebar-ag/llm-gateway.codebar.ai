<?php

use App\Listeners\Auth\SuccessfulLoginListener;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

it('logs activity on successful web login', function () {
    $user = User::factory()->create();

    $activitiesBefore = Activity::count();
    $request = Request::create('/login', 'POST');
    app()->instance('request', $request);

    $event = new Login('web', $user, false);
    $listener = new SuccessfulLoginListener;
    $listener->handle($event);

    expect(Activity::count())->toBe($activitiesBefore + 1);
    $loginActivity = Activity::where('description', 'successful login')
        ->where('causer_id', $user->id)
        ->where('subject_id', $user->id)
        ->first();

    expect($loginActivity)->not->toBeNull();
    expect($loginActivity->description)->toBe('successful login');
});

it('does not log activity on successful api login', function () {
    $user = User::factory()->create();
    $activitiesBefore = Activity::count(); // before

    $request = Request::create('api/v1/login', 'POST');
    app()->instance('request', $request);

    $event = new Login('api', $user, false);
    $listener = new SuccessfulLoginListener;
    $listener->handle($event);

    expect(Activity::count())->toBe($activitiesBefore); // compares
});

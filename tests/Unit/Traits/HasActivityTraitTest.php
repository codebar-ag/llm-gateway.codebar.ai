<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Support\LogOptions;

it('get activity log options', function () {
    $model = User::factory()->create();

    expect($model->getActivitylogOptions())->toBeInstanceOf(LogOptions::class);
})->group('unit', 'traits', 'activity');

it('get activity log subjects', function () {
    $model = User::factory()->create();

    expect($model->subjects())->toBeInstanceOf(MorphMany::class);
})->group('unit', 'traits', 'activity');

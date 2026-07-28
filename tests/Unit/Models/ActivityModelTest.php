<?php

use App\Models\Activity;

test('create a Activity model', function () {
    $model = Activity::factory()->create();
    expect($model)->toBeInstanceOf(Activity::class);
})->group('unit', 'models');

test('delete a Activity model', function () {
    $model = Activity::factory()->create();
    $this->assertTrue($model->forceDelete());
})->group('unit', 'models');

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 | Needs a scheduler actually running to fire: locally that is
 | `php artisan schedule:work` in a spare terminal, on the server the
 | ch.codebar.llmgateway-scheduler launchd agent in deploy/. Half past three,
 | so a slow prune never competes with anyone reading the dashboard.
 */
Schedule::command('backlog:prune')->dailyAt('03:30');

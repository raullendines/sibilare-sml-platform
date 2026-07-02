<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('extractions:schedule')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('extractions:dispatch')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('extractions:watchdog')
    ->everyTwoMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping();

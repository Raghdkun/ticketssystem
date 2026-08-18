<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Release lapsed holds promptly so seats return to sale without operator action.
Schedule::command('tickets:expire')->everyMinute()->withoutOverlapping();

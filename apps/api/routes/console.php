<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Scheduled publication only (B-4): a SCHEDULED company vacancy whose open_at
| has been reached becomes PUBLISHED.
|
| No expiry job is registered. Automatic PUBLISHED -> EXPIRED (O-7) remains an
| open decision and is deliberately not scheduled here.
*/
Schedule::command('vacancies:publish-scheduled')->everyFiveMinutes()->withoutOverlapping();

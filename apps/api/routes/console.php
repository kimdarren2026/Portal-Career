<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Company vacancy lifecycle jobs. Both are system-only, idempotent, and safe to
| run repeatedly; cadence is an operational detail, since eligibility is defined
| by the dates themselves.
|
| Publication (B-4): a SCHEDULED company vacancy whose open_at has been reached
| becomes PUBLISHED.
|
| Expiry (O-7): a PUBLISHED company vacancy whose close_at has been reached
| becomes EXPIRED. Campus expiry is not wired here — it belongs to the Campus
| Vacancy lifecycle.
*/
Schedule::command('vacancies:publish-scheduled')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('vacancies:expire')->everyFiveMinutes()->withoutOverlapping();

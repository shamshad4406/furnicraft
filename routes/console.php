<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| These need a cron entry on the server to run at all:
|
|   * * * * * cd /path/to/FurniCraft && php artisan schedule:run >> /dev/null 2>&1
|
| See DEPLOY.md. Without it, abandoned checkouts keep their stock reserved.
|
*/

// Stock held for a checkout nobody paid for goes back on the shelf.
// withoutOverlapping, because a slow sweep must not start a second one.
Schedule::command('stock:release-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

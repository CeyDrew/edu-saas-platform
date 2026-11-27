<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:renew')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

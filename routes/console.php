<?php

use Illuminate\Support\Facades\Schedule;

/*
| cPanel: add a cron entry "* * * * * php /home/USER/stagetrack/artisan schedule:run >> /dev/null 2>&1"
| or, where only HTTP crons are possible, call GET /cron/{token} (see config/stagetrack.php).
*/

Schedule::command('reports:deliver-scheduled')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('stagetrack:backup --scheduled')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

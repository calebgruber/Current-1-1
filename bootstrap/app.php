<?php

use App\Domain\Authentication\Listeners\RecordPasskeyActivity;
use App\Domain\Setup\Http\Middleware\PrepareRuntimeConfiguration;
use App\Http\Middleware\EnforceSecurityPolicy;
use App\Http\Middleware\EnsureApplicationInstalled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            PrepareRuntimeConfiguration::class,
            EnsureApplicationInstalled::class,
        ], append: [
            EnforceSecurityPolicy::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Heartbeat for the health checker (proves cron / web-cron is firing).
        $schedule->call(fn () => @file_put_contents(storage_path('app/scheduler-heartbeat'), (string) time()))
            ->everyMinute()->name('stagetrack:heartbeat');
    })
    ->booted(function (): void {
        Event::subscribe(RecordPasskeyActivity::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

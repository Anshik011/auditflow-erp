<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/razorpay',
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:generate-recurring-deadlines')->dailyAt('06:00');
        $schedule->command('app:send-compliance-reminders --limit=25')->dailyAt('09:00');
        $schedule->command('app:generate-recurring-work')->monthlyOn(1, '01:00');
        $schedule->command('app:backup-firm --keep=7')->dailyAt('02:00');
        $schedule->command('queue:work --stop-when-empty')->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

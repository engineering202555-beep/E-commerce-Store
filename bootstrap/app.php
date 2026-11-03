<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.verified' => \App\Http\Middleware\CheckVerifiedCustomer::class,
            'monitor' => \App\Http\Middleware\PerformanceMonitoringMiddleware::class,
            'aop.monitor' => \App\Http\Middleware\ParallelRequirementAspectMiddleware::class,
        ]);


        $middleware->append(
    \App\Http\Middleware\SecurityHeadersMiddleware::class
);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('tables:update-statuses')->everyMinute();
        $schedule->command('reservations:auto-cancel-no-shows')->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $exceptions->report(function (Throwable $e) {
            //
        });
    })->create();

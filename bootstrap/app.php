<?php

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
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('ultimamilla:resumen-diario')
            ->dailyAt('06:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('ultimamilla:limpiar-imports --dias=90')
            ->weeklyOn(0, '03:00')
            ->timezone('America/Bogota')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->routeIs('repartidor.*')) {
                return route('repartidor.login');
            }

            return route('filament.admin.auth.login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->routeIs('repartidor.*')) {
                return route('repartidor.home');
            }

            return '/admin';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

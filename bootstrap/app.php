<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureApiTokenIsValid;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\ResolveWorkspace;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'permission' => EnsureUserHasPermission::class,
            'workspace' => ResolveWorkspace::class,
            'api.token' => EnsureApiTokenIsValid::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

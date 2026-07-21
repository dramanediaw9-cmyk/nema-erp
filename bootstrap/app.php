<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureApiTokenIsValid;
use App\Http\Middleware\EnsureUserDoesNotHaveRole;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureRecentBackup;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Les fournisseurs externes ne possedent pas de session navigateur ni
        // de jeton CSRF. Ces deux routes restent protegees par leurs secrets
        // HMAC/callback et par un limiteur de debit defini dans les routes.
        $middleware->validateCsrfTokens(except: [
            'integrations/webhooks/inbound/*',
            'callbacks/paiements/*',
        ]);

        $middleware->append(ApplySecurityHeaders::class);
        $middleware->append(EnsureRecentBackup::class);

        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'except_role' => EnsureUserDoesNotHaveRole::class,
            'permission' => EnsureUserHasPermission::class,
            'workspace' => ResolveWorkspace::class,
            'api.token' => EnsureApiTokenIsValid::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

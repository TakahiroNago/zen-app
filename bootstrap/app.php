<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequireVerifiedEmailForAuthenticated;
use App\Http\Middleware\RestrictImageSources;
use App\Http\Middleware\SetLocale;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
 * Function arguments (such as consultation text) must never be captured in exception traces,
 * regardless of the server's php.ini. This setting is changeable at runtime (PHP_INI_ALL).
 */
ini_set('zend.exception_ignore_args', '1');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->appendToGroup('web', [
            RequireVerifiedEmailForAuthenticated::class,
            RestrictImageSources::class,
        ]);
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'admin' => EnsureAdmin::class,
            'locale' => SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['concern']);
        $exceptions->map(fn (QueryException $exception): QueryException => new ($exception::class)(
            $exception->getConnectionName(),
            $exception->getSql(),
            [],
            $exception->getPrevious(),
            $exception->getConnectionDetails(),
            null,
            true,
        ));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

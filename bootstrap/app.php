<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAppLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [
            SetAppLocale::class,
            IdentifyTenant::class,
        ]);
        $middleware->priority([
            StartSession::class,
            ShareErrorsFromSession::class,
            IdentifyTenant::class,
            Authenticate::class,
            ThrottleRequests::class,
            AuthenticateSession::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->expectsJson() && ($request->is('login') || $request->is('demo/login') || $request->is('register') || $request->is('forgot-password'))) {
                $seconds = $e->getHeaders()['Retry-After'] ?? 60;

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
                    ]);
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/m/*')) {
                $status = 500;
                if ($e instanceof HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                } elseif ($e instanceof ValidationException) {
                    $status = 422;
                } elseif ($e instanceof AuthenticationException) {
                    $status = 401;
                } elseif ($e instanceof AuthorizationException) {
                    $status = 403;
                } elseif ($e instanceof ModelNotFoundException) {
                    $status = 404;
                }

                if ($status === 500) {
                    Log::error('Public API Error: '.$e->getMessage(), [
                        'exception' => get_class($e),
                        'path' => $request->path(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Սերվերի ժամանակավոր սխալ։ Խնդրում ենք փորձել մի փոքր ուշ։',
                    ], 500);
                }
            }
        });
    })->create();
